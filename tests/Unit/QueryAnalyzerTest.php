<?php

declare(strict_types=1);

use LaravelPlus\DigDeep\Analyzers\QueryAnalyzer;

function q(string $sql, string $caller = 'app/Http/Controllers/PostController.php:12', float $ms = 1.0): array
{
    return ['sql' => $sql, 'caller' => $caller, 'time_ms' => $ms, 'bindings' => []];
}

it('normalizes literals so repeated queries share a pattern', function (): void {
    expect(QueryAnalyzer::normalize('select * from users where id = 1'))
        ->toBe(QueryAnalyzer::normalize('select * from users where id = 42'));
});

it('detects N+1 from the same caller', function (): void {
    $queries = array_map(fn (int $i) => q("select * from comments where post_id = {$i}"), range(1, 6));

    $found = QueryAnalyzer::detectNPlusOne($queries);

    expect($found)->toHaveCount(1)
        ->and($found[0]['count'])->toBe(6)
        ->and($found[0]['table'])->toBe('comments');
});

it('ignores queries that run once', function (): void {
    expect(QueryAnalyzer::detectNPlusOne([q('select * from users where id = 1')]))->toBe([]);
});

it('flags select star', function (): void {
    $found = QueryAnalyzer::detectSelectStar([q('select * from "users"'), q('select id from users')]);

    expect($found)->toHaveCount(1)->and($found[0]['table'])->toBe('users');
});

it('flags where columns without an index', function (): void {
    $schema = [[
        'name' => 'users',
        'columns' => [['name' => 'id', 'pk' => true], ['name' => 'email', 'pk' => false]],
        'indexes' => [],
    ]];

    $found = QueryAnalyzer::detectMissingIndexes([q('select id from users where email = ?')], $schema);

    expect(collect($found)->pluck('column')->all())->toContain('email');
});

it('does not flag indexed or primary key columns', function (): void {
    $schema = [[
        'name' => 'users',
        'columns' => [['name' => 'id', 'pk' => true], ['name' => 'email', 'pk' => false]],
        'indexes' => [['name' => 'users_email_unique', 'unique' => true, 'columns' => ['email']]],
    ]];

    expect(QueryAnalyzer::detectMissingIndexes([q('select id from users where email = ? and id = ?')], $schema))->toBe([]);
});

it('combines findings into typed hints', function (): void {
    $queries = array_map(fn (int $i) => q("select * from comments where post_id = {$i}"), range(1, 6));

    $types = collect(QueryAnalyzer::generateHints($queries))->pluck('type')->unique()->values()->all();

    expect($types)->toContain('n_plus_one', 'select_star');
});
