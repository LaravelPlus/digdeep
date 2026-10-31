<?php

declare(strict_types=1);

use LaravelPlus\DigDeep\Analyzers\QueryAnalyzer;

it('reads the schema through the driver-agnostic Schema API', function (): void {
    $tables = collect(QueryAnalyzer::schema(withRowCounts: true))->keyBy('name');

    expect($tables)->toHaveKey('digdeep_profiles')
        ->and($tables['digdeep_profiles']['columns'])->not->toBeEmpty()
        ->and($tables['digdeep_profiles'])->toHaveKeys(['indexes', 'foreign_keys', 'row_count']);
});

it('explains select queries', function (): void {
    $this->postJson('/digdeep/api/explain', ['sql' => 'select * from digdeep_profiles'])
        ->assertOk()
        ->assertJsonStructure(['plan']);
});

it('refuses to explain writes', function (): void {
    $this->postJson('/digdeep/api/explain', ['sql' => 'delete from digdeep_profiles'])->assertStatus(400);
});

it('renders the database page', function (): void {
    $this->get('/digdeep/database')->assertOk();
});
