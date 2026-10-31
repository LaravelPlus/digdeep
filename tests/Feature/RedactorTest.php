<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LaravelPlus\DigDeep\Support\Redactor;

it('masks bindings bound to sensitive columns', function (string $sql, array $bindings, array $expected): void {
    expect(Redactor::bindings($sql, $bindings))->toBe($expected);
})->with([
    'where' => ['select * from users where email = ? and "users"."password" = ?', ['a@b.test', 'pw'], ['a@b.test', '[redacted]']],
    'update' => ['update users set remember_token = ?, name = ? where id = ?', ['t', 'Ann', 1], ['[redacted]', 'Ann', 1]],
    'insert' => ['insert into users (name, password) values (?, ?), (?, ?)', ['A', 'x', 'B', 'y'], ['A', '[redacted]', 'B', '[redacted]']],
    'hash value' => ['select ? as v', ['$2y$12$abcdefghijklmnopqrstuv'], ['[redacted]']],
    'named' => ['select :api_key', ['api_key' => 'k'], ['api_key' => '[redacted]']],
]);

it('leaves bindings alone when binding redaction is off', function (): void {
    config(['digdeep.redact.bindings' => false]);

    expect(Redactor::bindings('select * from users where password = ?', ['pw']))->toBe(['pw']);
});

it('redacts headers case-insensitively and scrubs json and form bodies', function (): void {
    expect(Redactor::headers(['Authorization' => ['Bearer x'], 'accept' => ['*/*']]))
        ->toBe(['Authorization' => ['[redacted]'], 'accept' => ['*/*']])
        ->and(Redactor::body('{"access_token":"abc","n":1}', 'application/json'))->toBe('{"access_token":"[redacted]","n":1}')
        ->and(Redactor::body('user=a&password=b', 'application/x-www-form-urlencoded'))->toBe('user=a&password=%5Bredacted%5D')
        ->and(Redactor::body('<p>hi</p>', 'text/html'))->toBe('<p>hi</p>');
});

it('keeps secrets out of stored bindings, log context and response bodies', function (): void {
    $profile = $this->profile('/redact', function () {
        DB::select('select ? as password', ['hunter2']);
        DB::select('select 1 where 1 = ?', ['$2y$12$abcdefghijklmnopqrstuvwx']);
        Log::info('login', ['user' => 'ann', 'token' => 'tok-123']);

        return response()->json(['access_token' => 'sk-live-456', 'ok' => true]);
    });

    expect(json_encode($profile->data))
        ->not->toContain('$2y$12$')
        ->not->toContain('tok-123')
        ->not->toContain('sk-live-456')
        ->toContain('ann');
});

it('can stop storing response bodies', function (): void {
    config(['digdeep.capture_response_bodies' => false]);

    $profile = $this->profile('/nobody', fn () => response()->json(['ok' => 'visible-marker']));

    expect(json_encode($profile->data))->not->toContain('visible-marker');
});
