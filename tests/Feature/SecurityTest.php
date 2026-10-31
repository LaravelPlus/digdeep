<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use LaravelPlus\DigDeep\DigDeep;
use LaravelPlus\DigDeep\Models\DigDeepProfile;

function asProduction(): void
{
    app()->detectEnvironment(fn (): string => 'production');
}

it('hides the dashboard with a 404 outside local when no auth callback allows it', function (): void {
    asProduction();

    $this->get('/digdeep')->assertNotFound();
});

it('lets the auth callback open the dashboard outside local', function (): void {
    asProduction();
    DigDeep::auth(fn (): bool => true);

    $this->get('/digdeep')->assertOk();
});

it('blocks artisan commands and file rewrites outside local by default', function (string $uri, array $payload): void {
    asProduction();
    DigDeep::auth(fn (): bool => true);

    $this->withoutCsrf()->postJson($uri, $payload)->assertForbidden();
})->with([
    'run-command' => ['/digdeep/api/run-command', ['command' => 'about']],
    'ai-apply' => ['/digdeep/api/ai-apply', ['file_path' => 'app/X.php', 'old_code' => 'a', 'new_code' => 'b']],
]);

it('allows code actions when explicitly enabled', function (): void {
    asProduction();
    DigDeep::auth(fn (): bool => true);
    config(['digdeep.code_actions' => true]);

    $this->withoutCsrf()->postJson('/digdeep/api/run-command', ['command' => 'migrate:fresh'])
        ->assertOk()
        ->assertJsonPath('output', "Command 'migrate:fresh' is blocked for safety.");
});

it('requires a CSRF token on state-changing API calls', function (): void {
    asProduction();
    DigDeep::auth(fn (): bool => true);

    $this->postJson('/digdeep/api/clear')->assertStatus(419);
});

it('reports 501 instead of crashing when AI is used without laravel/ai', function (): void {
    config(['digdeep.code_actions' => true]);

    $this->postJson('/digdeep/api/ai-apply', ['file_path' => 'app/X.php', 'old_code' => 'a', 'new_code' => 'b'])
        ->assertStatus(501);
});

it('does not inject the debugbar for visitors the gate rejects', function (): void {
    Route::get('/page', fn () => response('<html><body>hi</body></html>')->header('Content-Type', 'text/html'));

    asProduction();
    expect($this->get('/page')->getContent())->not->toContain('__dd');

    DigDeep::auth(fn (): bool => true);
    expect($this->get('/page')->getContent())->toContain('__dd');
});

it('redacts secrets from the stored request', function (): void {
    Route::post('/login', fn () => response()->json(['ok' => true]));

    $this->postJson('/login', [
        'email' => 'a@example.test',
        'password' => 'hunter2',
        'nested' => ['api_key' => 'sk-live-123'],
    ], ['X-Api-Key' => 'abc', 'Authorization' => 'Bearer xyz']);

    $stored = json_encode(DigDeepProfile::query()->latest()->firstOrFail()->data);

    expect($stored)
        ->not->toContain('hunter2')
        ->not->toContain('sk-live-123')
        ->not->toContain('Bearer xyz')
        ->not->toContain('"abc"')
        ->toContain('a@example.test');
});

it('escapes profile data in the html export', function (): void {
    Route::get('/xss', fn () => 'ok');
    $this->get('/xss?q=</script><script>alert(1)</script>');
    $id = DigDeepProfile::query()->latest()->value('id');

    $html = $this->get('/digdeep/api/html-export?template=profile&id='.$id)->streamedContent();

    expect($html)->not->toContain('<script>alert(1)');
});

it('falls back to the default model when DIGDEEP_AI_MODEL is unset', function (): void {
    config(['digdeep.ai_key' => 'test-key', 'digdeep.ai_provider' => 'anthropic', 'digdeep.ai_model' => null]);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => 'PROBLEM: x']]])]);

    $this->postJson('/digdeep/api/ai-suggest', ['sql' => 'select * from users', 'issue' => 'select_star'])->assertOk();

    Http::assertSent(fn ($request): bool => $request['model'] === 'claude-haiku-4-5-20251001');
});
