<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
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
    $this->postJson('/digdeep/api/ai-suggest', ['sql' => 'select 1', 'issue' => 'slow'])->assertStatus(501);
});
