<?php

declare(strict_types=1);

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request as ToolRequest;
use LaravelPlus\DigDeep\Ai\Agents\ExceptionInvestigatorAgent;
use LaravelPlus\DigDeep\Ai\Agents\QueryFixerAgent;
use LaravelPlus\DigDeep\Ai\Tools\ListSourceFilesTool;
use LaravelPlus\DigDeep\Ai\Tools\ReadSourceFileTool;
use LaravelPlus\DigDeep\Ai\Tools\WriteSourceFileTool;
use LaravelPlus\DigDeep\DigDeep;

/** Point base_path() at a throwaway app tree. */
function sandbox(): string
{
    $root = sys_get_temp_dir().'/digdeep-ai-'.uniqid();
    File::ensureDirectoryExists($root.'/app/Models');
    File::put($root.'/app/Models/Hotel.php', "<?php\nclass Hotel {}\n");
    File::put($root.'/app/Big.php', str_repeat('x', 51201));
    File::ensureDirectoryExists($root.'/app/Empty');
    app()->setBasePath($root);

    return $root;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/digdeep-ai-*') as $dir) {
        File::deleteDirectory($dir);
    }
});

$fix = ['analysis' => 'A', 'suggestion' => 'S', 'file_path' => 'app/Models/Hotel.php', 'old_code' => 'o', 'new_code' => 'n'];
$investigation = ['analysis' => 'A', 'root_cause' => 'R', 'suggestion' => 'S', 'file_path' => null, 'old_code' => null, 'new_code' => null];

it('suggests a query fix through the agent', function (?string $key) use ($fix): void {
    config(['digdeep.ai_key' => $key, 'digdeep.ai_provider' => 'anthropic']);
    QueryFixerAgent::fake([$fix]);

    $this->postJson('/digdeep/api/ai-suggest', ['sql' => 'select * from hotels', 'issue' => 'slow', 'time_ms' => 12.34, 'caller' => 'app/Models/Hotel.php:3'])
        ->assertOk()
        ->assertJson($fix);

    QueryFixerAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'took 12.3ms'));
    expect(config('ai.providers.anthropic.key'))->toBe($key);
})->with([null, 'secret-key']);

it('investigates an exception through the agent', function (?string $key) use ($investigation): void {
    config(['digdeep.ai_key' => $key]);
    ExceptionInvestigatorAgent::fake([$investigation]);

    $this->postJson('/digdeep/api/ai-investigate', [
        'class' => 'RuntimeException',
        'message' => 'boom',
        'file' => 'app/X.php',
        'line' => 4,
        'trace' => [['file' => 'app/X.php', 'line' => 4, 'class' => 'X', 'function' => 'run'], ['file' => 'app/Y.php']],
    ])->assertOk()->assertJson($investigation);

    ExceptionInvestigatorAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'app/X.php:4 → X::run()'));
})->with([null, 'secret-key']);

it('reports agent failures as 500', function (string $uri, array $payload, string $agent): void {
    $agent::fake(fn () => throw new RuntimeException('provider down'));

    $this->postJson($uri, $payload)->assertStatus(500)->assertJsonPath('error', 'provider down');
})->with([
    'suggest' => ['/digdeep/api/ai-suggest', ['sql' => 'select 1', 'issue' => 'n1'], QueryFixerAgent::class],
    'investigate' => ['/digdeep/api/ai-investigate', ['class' => 'E', 'message' => 'm'], ExceptionInvestigatorAgent::class],
]);

it('returns 501 from the investigate endpoint when nothing is configured', function (): void {
    DigDeep::$aiSdkAvailable = false;

    $this->postJson('/digdeep/api/ai-investigate', ['class' => 'E', 'message' => 'm'])->assertStatus(501);
});

it('calls the provider directly when laravel/ai is unavailable', function (string $uri, array $payload, string $provider, string $host, array $ok, string $text): void {
    DigDeep::$aiSdkAvailable = false;
    config(['digdeep.ai_key' => 'k', 'digdeep.ai_provider' => $provider, 'digdeep.ai_model' => null]);
    Http::fake(["{$host}/*" => Http::response($ok)]);

    $this->postJson($uri, $payload)->assertOk()->assertJsonPath('suggestion', $text);
})->with(function () {
    $suggest = ['/digdeep/api/ai-suggest', ['sql' => 'select 1', 'issue' => 'duplicate']];
    $investigate = ['/digdeep/api/ai-investigate', ['class' => 'E', 'message' => 'm']];
    $anthropic = ['anthropic', 'api.anthropic.com', ['content' => [['text' => 'hi']]], 'hi'];
    $openai = ['openai', 'api.openai.com', ['choices' => [['message' => ['content' => 'yo']]]], 'yo'];

    return [
        'suggest anthropic' => [...$suggest, ...$anthropic],
        'suggest openai' => [...$suggest, ...$openai],
        'investigate anthropic' => [...$investigate, ...$anthropic],
        'investigate openai' => [...$investigate, ...$openai],
    ];
});

it('surfaces direct provider errors', function (string $provider, string $host, array $body, string $message): void {
    DigDeep::$aiSdkAvailable = false;
    config(['digdeep.ai_key' => 'k', 'digdeep.ai_provider' => $provider]);
    Http::fake(["{$host}/*" => Http::response($body, 500)]);

    $this->postJson('/digdeep/api/ai-suggest', ['sql' => 'select 1', 'issue' => 'n1'])
        ->assertStatus(500)->assertJsonPath('error', $message);
})->with([
    ['anthropic', 'api.anthropic.com', ['error' => ['message' => 'bad key']], 'bad key'],
    ['anthropic', 'api.anthropic.com', [], 'Anthropic API request failed.'],
    ['openai', 'api.openai.com', ['error' => ['message' => 'bad key']], 'bad key'],
    ['openai', 'api.openai.com', [], 'OpenAI API request failed.'],
]);

it('applies a fix to an app file', function (): void {
    $root = sandbox();
    config(['digdeep.code_actions' => true]);

    $this->postJson('/digdeep/api/ai-apply', ['file_path' => 'app/Models/Hotel.php', 'old_code' => 'class Hotel', 'new_code' => 'final class Hotel'])
        ->assertOk()->assertJsonPath('status', 'ok');

    expect(File::get($root.'/app/Models/Hotel.php'))->toContain('final class Hotel');
});

it('rejects fixes it cannot apply', function (string $path, string $old): void {
    sandbox();
    config(['digdeep.code_actions' => true]);

    $this->postJson('/digdeep/api/ai-apply', ['file_path' => $path, 'old_code' => $old, 'new_code' => 'z'])
        ->assertStatus(422)->assertJsonStructure(['error']);
})->with([
    'outside app' => ['config/app.php', 'a'],
    'traversal' => ['app/../.env', 'a'],
    'missing file' => ['app/Nope.php', 'a'],
    'old code absent' => ['app/Models/Hotel.php', 'not there'],
]);

it('lists, reads and guards source files', function (): void {
    sandbox();
    $list = new ListSourceFilesTool;
    $read = new ReadSourceFileTool;

    expect((string) $list->handle(new ToolRequest(['directory' => '/app/Models/'])))->toBe('app/Models/Hotel.php')
        ->and((string) $list->handle(new ToolRequest(['directory' => 'app/Empty'])))->toBe('No PHP files found in app/Empty')
        ->and((string) $list->handle(new ToolRequest(['directory' => 'app/Nope'])))->toBe('Error: Directory not found: app/Nope')
        ->and((string) $list->handle(new ToolRequest(['directory' => 'config'])))->toStartWith('Error: Only app/')
        ->and((string) $read->handle(new ToolRequest(['path' => 'app/Models/Hotel.php'])))->toContain('2: class Hotel {}')
        ->and((string) $read->handle(new ToolRequest(['path' => 'app/Nope.php'])))->toBe('Error: File not found: app/Nope.php')
        ->and((string) $read->handle(new ToolRequest(['path' => 'app/Big.php'])))->toStartWith('Error: File is too large')
        ->and((string) $read->handle(new ToolRequest(['path' => '.env'])))->toStartWith('Error: Access denied');
});

it('describes agents and tools to the model', function (): void {
    $schema = new JsonSchemaTypeFactory;

    foreach ([new QueryFixerAgent, new ExceptionInvestigatorAgent] as $agent) {
        expect((string) $agent->instructions())->not->toBeEmpty()
            ->and([...$agent->tools()])->toHaveCount(2)
            ->and($agent->schema($schema))->toHaveKey('analysis');
    }

    foreach ([new ListSourceFilesTool, new ReadSourceFileTool, new WriteSourceFileTool] as $tool) {
        expect((string) $tool->description())->not->toBeEmpty()
            ->and($tool->schema($schema))->not->toBeEmpty();
    }
});
