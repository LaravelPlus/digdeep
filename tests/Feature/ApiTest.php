<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LaravelPlus\DigDeep\DigDeep;
use LaravelPlus\DigDeep\Models\DigDeepProfile;

beforeEach(function (): void {
    $this->id = $this->profile('/orders')->id;
});

it('lists profiles as json', function (): void {
    $this->getJson('/digdeep/api/profiles')->assertOk()->assertJsonFragment(['url' => '/orders']);
});

it('profiles a url on demand', function (): void {
    Route::get('/target', fn () => 'ok');

    $response = $this->postJson('/digdeep/api/trigger', ['url' => 'http://localhost/target'])
        ->assertOk()
        ->assertJsonPath('status_code', 200);

    expect(DigDeepProfile::query()->find($response->json('profile_id'))->url)->toBe('/target');
});

it('replays a profile into a comparison', function (): void {
    $this->postJson("/digdeep/api/profile/{$this->id}/replay")
        ->assertOk()
        ->assertJsonPath('original_id', $this->id);
});

it('updates tags and notes, stripping html', function (): void {
    $this->postJson("/digdeep/api/profile/{$this->id}/tags", ['tags' => 'slow, <b>x</b>'])->assertOk();
    $this->postJson("/digdeep/api/profile/{$this->id}/notes", ['notes' => '<script>n</script>check'])->assertOk();

    $row = DigDeepProfile::query()->find($this->id);

    expect($row->tags)->not->toContain('<b>')
        ->and($row->notes)->not->toContain('<script>');
});

it('exports one profile as json', function (): void {
    $json = json_decode($this->get("/digdeep/api/profile/{$this->id}/export")->streamedContent(), true);

    expect($json['profile']['id'])->toBe($this->id);
});

it('exports html for every template', function (string $template): void {
    $this->get("/digdeep/api/html-export?template={$template}&id={$this->id}")->assertOk();
})->with(['dashboard', 'performance', 'profile']);

it('bulk exports, tags and deletes', function (): void {
    $this->postJson('/digdeep/api/bulk-export', ['ids' => [$this->id]])->assertOk();
    $this->postJson('/digdeep/api/bulk', ['action' => 'tag', 'ids' => [$this->id], 'tag' => 'x'])->assertOk();
    $this->postJson('/digdeep/api/bulk', ['action' => 'delete', 'ids' => [$this->id]])->assertOk();

    expect(DigDeepProfile::query()->find($this->id))->toBeNull();
});

it('rejects bulk actions without ids', function (): void {
    $this->postJson('/digdeep/api/bulk', ['action' => 'delete', 'ids' => []])->assertStatus(400);
});

it('serves trends, performance and compare data', function (): void {
    $other = $this->profile('/other')->id;

    $this->getJson('/digdeep/api/trends')->assertOk();
    $this->getJson('/digdeep/api/performance')->assertOk();
    $this->getJson("/digdeep/api/compare?a={$this->id}&b={$other}")->assertOk()->assertJsonStructure(['a', 'b']);
});

it('deletes one profile and clears all', function (): void {
    $this->deleteJson("/digdeep/api/profile/{$this->id}")->assertOk();
    expect(DigDeepProfile::query()->find($this->id))->toBeNull();

    $this->profile('/again');
    $this->postJson('/digdeep/api/clear')->assertOk();
    expect(DigDeepProfile::query()->count())->toBe(0);
});

it('returns 501 from ai endpoints when nothing is configured', function (): void {
    DigDeep::$aiSdkAvailable = false;
    $this->postJson('/digdeep/api/ai-suggest', ['sql' => 'select 1', 'issue' => 'slow'])->assertStatus(501);
});

it('prefixes a missing leading slash and profiles failing routes', function (): void {
    Route::get('/boom', fn () => throw new RuntimeException('boom'));

    $this->postJson('/digdeep/api/trigger', ['url' => 'boom'])->assertOk()->assertJsonPath('status_code', 500);

    expect(DigDeepProfile::query()->where('url', '/boom')->first()->data['exception']['message'] ?? null)->toBe('boom');
});

it('records exceptions that escape the kernel while triggering and replaying', function (): void {
    $boom = $this->profile('/boom', fn () => throw new RuntimeException('boom'))->id;
    $this->withoutExceptionHandling();

    $this->postJson('/digdeep/api/trigger', ['url' => '/boom'])->assertOk()->assertJsonPath('status_code', 500);
    $this->postJson("/digdeep/api/profile/{$boom}/replay")->assertOk()->assertJsonPath('original_id', $boom);
});

it('replays a failing route', function (): void {
    $boom = $this->profile('/boom', fn () => throw new RuntimeException('boom'))->id;

    $this->postJson("/digdeep/api/profile/{$boom}/replay")->assertOk();
});

it('answers 404 for unknown profiles', function (string $method, string $uri): void {
    $this->{$method}("/digdeep/api/{$uri}")->assertNotFound();
})->with([
    ['get', 'profile/nope/export'],
    ['postJson', 'profile/nope/replay'],
    ['get', 'html-export?template=profile&id=nope'],
]);

it('rejects invalid explain requests', function (string $sql, string $error): void {
    $this->postJson('/digdeep/api/explain', ['sql' => $sql])->assertStatus(400)->assertJsonPath('error', $error);
})->with([
    'empty' => ['', 'No SQL provided'],
    'too long' => ['select '.str_repeat('1,', 5001).'1', 'SQL query exceeds maximum length'],
    'not a select' => ['pragma table_info(x)', 'EXPLAIN only supports SELECT queries'],
    'dangerous' => ['select 1; drop table users', 'SQL query contains disallowed keywords'],
]);

it('explains a select, caches the plan and reports sql errors', function (): void {
    $first = $this->postJson('/digdeep/api/explain', ['sql' => 'select 1'])->assertOk();
    $second = $this->postJson('/digdeep/api/explain', ['sql' => 'select 1'])->assertOk();

    expect($first->json('plan'))->not->toBeEmpty()
        ->and($first->json('cached'))->toBeNull()
        ->and($second->json('cached'))->toBeTrue();

    $this->postJson('/digdeep/api/explain', ['sql' => 'select * from missing_table'])->assertStatus(400)->assertJsonStructure(['error']);
});

it('filters profiles by criteria, cursor and page', function (): void {
    $this->profile('/boom', fn () => throw new RuntimeException('boom'));

    $this->getJson('/digdeep/api/profiles?has_errors=1&status_min=500&per_page=1')
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('has_more', false);
    $this->getJson('/digdeep/api/profiles?after=2999-01-01')->assertJsonPath('profiles', []);
});

it('narrows trends and performance by route and range', function (string $range): void {
    $this->profile('/boom', fn () => throw new RuntimeException('boom'));

    $this->getJson("/digdeep/api/trends?route=/boom&range={$range}")->assertOk()->assertJsonPath('stats.count', 1);
    $this->getJson("/digdeep/api/performance?range={$range}")->assertOk()->assertJsonPath('global.total', 2);
})->with(['hour', 'day', 'week']);

it('validates bulk actions', function (array $payload, int $status): void {
    $this->postJson('/digdeep/api/bulk', $payload + ['ids' => [$this->id]])->assertStatus($status);
})->with([
    'blank tag' => [['action' => 'tag', 'tag' => '<b></b>'], 400],
    'unknown action' => [['action' => 'explode'], 400],
]);

it('rejects bulk export without ids or matches, and html export without an id', function (): void {
    $this->postJson('/digdeep/api/bulk-export', ['ids' => []])->assertStatus(400);
    $this->postJson('/digdeep/api/bulk-export', ['ids' => ['nope']])->assertNotFound();
    $this->get('/digdeep/api/html-export?template=profile')->assertStatus(400);
});

it('exports html from an empty store and with failing profiles', function (): void {
    $this->profile('/boom', fn () => throw new RuntimeException('boom'));
    $this->get('/digdeep/api/html-export?template=dashboard')->assertOk();
    $this->get('/digdeep/api/html-export?template=performance')->assertOk();

    DigDeepProfile::query()->delete();
    $this->get('/digdeep/api/html-export?template=dashboard')->assertOk();
});

it('records the matched route for on-demand and replayed profiles', function (): void {
    Route::get('/target', fn () => 'ok')->name('target.show');

    $new = $this->postJson('/digdeep/api/trigger', ['url' => '/target'])->json('profile_id');
    $replayed = $this->postJson('/digdeep/api/profile/'.$new.'/replay')->json('profile_id');

    foreach ([$new, $replayed] as $id) {
        expect(DigDeepProfile::query()->find($id)->data['route']['name'])->toBe('target.show');
    }
});

it('streams bulk exports', function (): void {
    $json = json_decode($this->postJson('/digdeep/api/bulk-export', ['ids' => [$this->id]])->streamedContent(), true);

    expect($json['count'])->toBe(1);
});

it('runs artisan commands, blocks dangerous ones and reports failures', function (): void {
    config(['digdeep.code_actions' => true]);

    $root = sys_get_temp_dir().'/digdeep-artisan-'.uniqid();
    mkdir($root);
    file_put_contents($root.'/artisan', '<?php echo implode(" ", array_slice($argv, 1));');
    $this->app->setBasePath($root);

    $this->postJson('/digdeep/api/run-command', ['command' => 'php artisan make:thing "two words"'])
        ->assertOk()->assertJson(['exit_code' => 0, 'output' => 'make:thing two words']);
    unlink($root.'/artisan');
    rmdir($root);

    $this->postJson('/digdeep/api/run-command', ['command' => 'tinker'])->assertJsonPath('exit_code', 1);

    $this->app->setBasePath('/nonexistent-digdeep-dir');
    $this->postJson('/digdeep/api/run-command', ['command' => 'list'])->assertJsonPath('exit_code', 1);
});
