<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use LaravelPlus\DigDeep\DigDeep;
use LaravelPlus\DigDeep\DigDeepCollector;
use LaravelPlus\DigDeep\Models\DigDeepProfile;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Response;

it('records request, response and performance', function (): void {
    $data = $this->profile('/hello', fn () => response('hi', 201))->data;

    expect($data['request'])->toMatchArray(['method' => 'GET', 'url' => '/hello'])
        ->and($data['response']['status_code'])->toBe(201)
        ->and($data['performance'])->toHaveKeys(['duration_ms', 'memory_peak_mb', 'query_count']);
});

it('collects queries with their caller', function (): void {
    $data = $this->profile('/q', function () {
        DB::select('select 1 as one');

        return 'ok';
    })->data;

    expect(collect($data['queries'])->pluck('sql'))->toContain('select 1 as one')
        ->and($data['performance']['query_count'])->toBeGreaterThanOrEqual(1);
});

it('collects cache, logs, mail and http client calls', function (): void {
    Http::fake(['example.test/*' => Http::response(['ok' => true])]);
    Mail::fake();

    $data = $this->profile('/all', function () {
        Cache::put('k', 'v', 60);
        Cache::get('k');
        Log::warning('careful', ['a' => 1]);
        Http::get('https://example.test/ping');

        return 'ok';
    })->data;

    expect($data['cache'])->not->toBeEmpty()
        ->and(collect($data['logs'])->pluck('message'))->toContain('careful')
        ->and(collect($data['http_client'])->pluck('url')->implode(' '))->toContain('example.test/ping');
});

it('records exceptions rendered by the handler', function (): void {
    $data = $this->profile('/boom', fn () => throw new RuntimeException('kaput'))->data;

    expect($data['response']['status_code'])->toBe(500)
        ->and($data['exception'])->toMatchArray(['class' => RuntimeException::class, 'message' => 'kaput']);
});

it('handles plain Symfony error responses', function (): void {
    $data = $this->profile('/sym', fn () => new Response('nope', 500))->data;

    expect($data['response']['status_code'])->toBe(500);
});

it('skips ignored paths', function (): void {
    config(['digdeep.ignored_paths' => ['health']]);
    Route::get('/health', fn () => 'ok');

    $this->get('/health')->assertOk();

    expect(DigDeepProfile::query()->count())->toBe(0);
});

it('skips sub-requests marked as already profiled', function (): void {
    Route::get('/inner', fn () => 'ok');

    $this->get('/inner', ['X-DigDeep-Profile' => '1']);

    expect(DigDeepProfile::query()->count())->toBe(0);
});

it('injects the debugbar into html pages only', function (): void {
    Route::get('/page', fn () => response('<html><body>x</body></html>')->header('Content-Type', 'text/html'));
    Route::get('/json', fn () => ['x' => 1]);

    expect($this->get('/page')->getContent())->toContain('__dd')
        ->and($this->get('/json')->getContent())->not->toContain('__dd')
        ->and($this->get('/page', ['X-Requested-With' => 'XMLHttpRequest'])->getContent())->not->toContain('__dd');
});

it('respects show_debugbar=false', function (): void {
    config(['digdeep.show_debugbar' => false]);
    Route::get('/page', fn () => response('<html><body>x</body></html>')->header('Content-Type', 'text/html'));

    expect($this->get('/page')->getContent())->not->toContain('__dd');
});

it('stores dev fixtures and resets them per request', function (): void {
    $data = $this->profile('/fx', function () {
        DigDeep::fixture('login', 'Login', [['field' => 'email', 'label' => 'Email', 'value' => fn () => 'a@b.test']]);

        return 'ok';
    })->data;

    expect($data['dev_fixtures']['login']['fields'][0]['value'])->toBe('a@b.test')
        ->and(DigDeep::flushFixtures())->toBe([]);
});

it('registers listeners once and resets state between requests in long-lived workers', function (): void {
    $collector = app(DigDeepCollector::class);

    $collector->startRequest();
    DB::select('select 1');
    $collector->setException(new RuntimeException('first request'));
    $listeners = count(Event::getListeners(QueryExecuted::class));

    $collector->startRequest();
    DB::select('select 2');
    $data = $collector->finishRequest();

    expect($data['queries'])->toHaveCount(1)
        ->and($data['queries'][0]['sql'])->toBe('select 2')
        ->and($data['exception'])->toBeNull()
        ->and(Event::getListeners(QueryExecuted::class))->toHaveCount($listeners);
});

it('caps entries per collector and shortens long strings, but counts everything', function (): void {
    config(['digdeep.limits.max_entries' => 3, 'digdeep.limits.max_string_length' => 20]);

    $data = $this->profile('/many', function () {
        foreach (range(1, 5) as $i) {
            DB::select('select '.$i);
        }
        Log::info(str_repeat('x', 100));

        return 'ok';
    })->data;

    expect($data['queries'])->toHaveCount(3)
        ->and($data['performance']['query_count'])->toBe(5)
        ->and($data['_dropped']['queries'])->toBe(2)
        ->and($data['logs'][0]['message'])->toBe(str_repeat('x', 20).'... [truncated]');
});

it('does not store streamed or binary responses', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'dd');
    file_put_contents($file, 'data');
    Route::get('/stream', fn () => response()->stream(fn () => print 'x'));
    Route::get('/download', fn () => response()->download($file));

    $this->get('/stream')->assertOk();
    $this->get('/download')->assertOk();

    expect(DigDeepProfile::query()->whereIn('url', ['/stream', '/download'])->count())->toBe(0);
});

it('profiles nothing at sample_rate 0 and everything at 1', function (): void {
    Route::get('/sampled', fn () => 'ok');

    config(['digdeep.sample_rate' => 0.0]);
    $this->get('/sampled');
    expect(DigDeepProfile::query()->where('url', '/sampled')->count())->toBe(0);

    config(['digdeep.sample_rate' => 1.0]);
    $this->get('/sampled');
    expect(DigDeepProfile::query()->where('url', '/sampled')->count())->toBe(1);
});

it('records the exception and rethrows when the pipeline throws', function (): void {
    Route::get('/kaboom', fn () => throw new RuntimeException('kaput'));
    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/kaboom'))->toThrow(RuntimeException::class, 'kaput');
});

it('captures request bodies without leaking unscrubbable ones', function (string $type, string $body, string $expected): void {
    Route::post('/b2', fn () => 'ok');
    $this->call('POST', '/b2', $type === 'application/x-www-form-urlencoded' ? ['a' => 1, 'password' => 'x'] : [], server: ['CONTENT_TYPE' => $type], content: $body);

    expect(DigDeepProfile::query()->where('url', '/b2')->first()->data['request']['body'])->toContain($expected);
})->with([
    'json' => ['application/json', '{"a":1,"password":"x"}', '"a":1'],
    'json scalar' => ['application/json', '"str"', '[body omitted]'],
    'text' => ['text/plain', 'hello', '[body omitted]'],
    'form' => ['application/x-www-form-urlencoded', 'a=1&password=x', '"a":1'],
]);

it('records middleware and session data on web routes', function (): void {
    Route::get('/web', function () {
        session(['cart' => ['a' => 1], 'token' => 'abc', 'name' => 'n', 'obj' => new ArrayObject([1])]);

        return 'ok';
    })->middleware(['web', 'throttle:60,1']);
    $this->get('/web');

    $data = DigDeepProfile::query()->where('url', '/web')->first()->data;

    expect($data['session']['token']['value'])->toBe('[redacted]')
        ->and($data['session']['name']['value'])->toBe('n')
        ->and($data['session'])->toHaveKeys(['cart', 'obj'])
        ->and($data['route']['middleware'])->toContain('web');
});

it('never breaks the response when the debugbar fails to render', function (): void {
    View::composer('digdeep::debugbar', fn () => throw new RuntimeException('view down'));
    Route::get('/page', fn () => response('<html><body>x</body></html>')->header('Content-Type', 'text/html'));

    $this->get('/page')->assertOk()->assertDontSee('__dd');
});

it('leaves html without a body tag untouched', function (): void {
    Route::get('/frag', fn () => response('<p>x</p>')->header('Content-Type', 'text/html'));

    $this->get('/frag')->assertOk()->assertDontSee('__dd');
});

it('collects commands, mail, jobs, notifications, scheduled tasks and model deletes', function (): void {
    config(['mail.default' => 'array', 'queue.default' => 'sync']);

    $notifiable = new class extends Model
    {
        use Notifiable;

        protected $guarded = [];
    };
    $notifiable->id = 7;
    $notification = new class extends Notification
    {
        public function via(): array
        {
            return [CollectorTestChannel::class];
        }
    };

    $data = $this->profile('/everything', function () use ($notifiable, $notification) {
        $io = [new ArrayInput([]), new NullOutput];
        event(new CommandStarting('about', ...$io));
        event(new CommandFinished('about', ...[...$io, 0]));
        event(new CommandFinished('other', ...[...$io, 1]));
        Mail::raw('hello', fn ($m) => $m->to('a@example.test')->to('b@example.test'));
        event(new JobQueued('sync', 'emails', 1, new stdClass, '{}', null));
        event(new JobQueued('sync', null, 2, 'LegacyJob', '{}', null));
        $notifiable->notify($notification);
        NotificationFacade::route('collector-test', 'x')->notify($notification);

        $schedule = app(Schedule::class);
        $event = $schedule->command('about');
        event(new ScheduledTaskFinished($event, 0.1));
        event(new ScheduledTaskStarting($event));
        event(new ScheduledTaskFinished($event, 0.1));
        event(new ScheduledTaskStarting($schedule->call(fn () => null)));

        DigDeepProfile::query()->create(['id' => 'tmp', 'method' => 'GET', 'url' => '/tmp', 'data' => []])->delete();
        event('composing: nothing', ['not a view']);
        event('custom.event', [1, 'text', new stdClass]);
        event('custom.empty', [1]);
        Log::info('bad utf8', ['v' => "\xB1\x31"]);

        return 'ok';
    })->data;

    expect($data['commands'][0])->toMatchArray(['command' => 'about', 'exit_code' => 0])
        ->and($data['commands'][1])->toMatchArray(['command' => 'other', 'exit_code' => 1, 'duration_ms' => 0])
        ->and($data['mail'][0])->toMatchArray(['to' => 'a@example.test, b@example.test', 'subject' => '(no subject)'])
        ->and(collect($data['jobs'])->pluck('queue')->all())->toBe(['emails', 'default'])
        ->and($data['jobs'][1]['job'])->toBe('LegacyJob')
        ->and(collect($data['notifications'])->pluck('notifiable')->all())->toContain($notifiable::class.':7')
        ->and(collect($data['notifications'])->pluck('sent')->unique()->all())->toBe([true])
        ->and($data['scheduled_tasks'])->toHaveCount(2)
        ->and($data['scheduled_tasks'][0]['duration_s'])->not->toBeNull()
        ->and(collect($data['models'])->firstWhere('class', DigDeepProfile::class)['deleted'])->toBe(1);
});

class CollectorTestChannel
{
    public function send(): void {}
}

it('summarises inertia pages from json and html responses', function (): void {
    $page = ['component' => 'Users/Index', 'url' => '/users', 'version' => 'v1', 'props' => [
        'users' => array_fill(0, 12, ['name' => 'x']),
        'flags' => ['a' => 1],
        'rows' => [['a' => 1]],
        'name' => str_repeat('x', 600),
        'ok' => true,
        'nothing' => null,
        'count' => 3,
        'deep' => ['l1' => ['l2' => ['l3' => 1]]],
    ]];

    $json = $this->profile('/inertia-json', fn () => response()->json($page)->header('X-Inertia', 'true'))->data['inertia'];
    $html = $this->profile('/inertia-html', fn () => response('<div id="app" data-page="'.e(json_encode($page)).'"></div>'))->data['inertia'];

    expect($json['component'])->toBe('Users/Index')
        ->and($json['props'])->toMatchArray(['users' => 'array[12] of objects', 'flags' => 'array[1]', 'name' => 'string(600)', 'ok' => 'true', 'nothing' => 'null', 'count' => '3'])
        ->and($json['props_raw']['users'])->toHaveCount(11)
        ->and($json['props_raw']['name'])->toEndWith('(truncated)')
        ->and($json['props_raw']['deep']['l1']['l2'])->toBe(['...' => 'truncated'])
        ->and($html)->toBe($json);
});

it('ignores inertia-looking responses that are not pages', function (): void {
    $data = $this->profile('/inertia-bad', fn () => response('not json')->header('X-Inertia', 'true'))->data;
    $bad = $this->profile('/inertia-bad-html', fn () => response('<div data-page="nope"></div>'))->data;

    expect($data['inertia'])->toBe([])->and($bad['inertia'])->toBe([]);
});

it('records the authenticated user and ignores unknown lifecycle phases', function (mixed $user, mixed $expected): void {
    $collector = app(DigDeepCollector::class);
    $collector->startRequest();
    $collector->markLifecyclePhase('unknown');
    $collector->setAuthUser($user);

    expect($collector->finishRequest()['request']['auth_user'] ?? null)->toBe($expected);
})->with([
    'none' => [null, null],
    'scalar' => ['guest-7', 'guest-7'],
    'object' => [new stdClass, null],
    'authenticatable' => [new GenericUser(['id' => 3, 'name' => 'N', 'email' => 'e@example.test']), ['id' => 3, 'name' => 'N', 'email' => 'e@example.test']],
]);

it('exposes the ajax and pages scopes', function (): void {
    $this->profile('/page');
    $this->profile('/json', fn () => ['a' => 1]);
    $this->getJson('/json');

    expect(DigDeepProfile::query()->pages()->count())->toBe(2)
        ->and(DigDeepProfile::query()->ajax()->count())->toBe(1);
});

it('names the calling file for queries issued outside vendor and package code', function (): void {
    $file = sys_get_temp_dir().'/DigDeepCaller.php';
    file_put_contents($file, "<?php\n\nreturn fn () => Illuminate\\Support\\Facades\\DB::select('select 1');\n");

    $data = $this->profile('/caller', require $file)->data;
    unlink($file);

    expect($data['queries'][0]['caller'])->toBe('DigDeepCaller.php:3');
});
