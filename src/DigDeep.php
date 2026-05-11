<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep;

use Closure;
use Illuminate\Http\Request;

final class DigDeep
{
    /**
     * The callback that should be used to authenticate DigDeep users.
     *
     * @var (Closure(Request): bool)|null
     */
    private static ?Closure $authUsing = null;

    /**
     * Dev fixture groups stored for the current request.
     *
     * @var array<string, array{label: string, actions: list<array{label: string, url: string}>, fields: list<array{field: string, label: string, value: mixed, variations: list<mixed>}>}>
     */
    private static array $fixtures = [];

    /**
     * Register the DigDeep authentication callback.
     */
    public static function auth(Closure $callback): void
    {
        self::$authUsing = $callback;
    }

    /**
     * Determine if the given request can access the DigDeep dashboard.
     */
    public static function check(Request $request): bool
    {
        // Always allow in local/testing
        if (app()->environment('local', 'testing')) {
            return true;
        }

        // Use custom auth callback if registered
        if (self::$authUsing) {
            return (self::$authUsing)($request);
        }

        return false;
    }

    /**
     * Register a dev fixture group that appears in the DigDeep "Dev" tab.
     *
     * Only active when DigDeep is enabled. Safe to call unconditionally —
     * in production DigDeep is disabled and this is a no-op.
     *
     * Fields support `variations` (alternate clickable values) and the group
     * supports `actions` (buttons that navigate to a URL, e.g. factory generators):
     *
     *   DigDeep::fixture('check', 'Preveri veljavnost', [
     *       ['field' => 'code', 'label' => 'Številka kartice', 'value' => $voucher->code,
     *        'variations' => [['label' => 'Neobstoječa', 'value' => 'EC-INVALID']]],
     *   ], actions: [
     *       ['label' => '+ ustvari registrirano', 'url' => '/dev/fixtures/voucher/registered'],
     *   ]);
     *
     * @param list<array{field: string, label: string, value: mixed, variations?: list<mixed>}> $fields
     * @param list<array{label: string, url: string}> $actions
     */
    public static function fixture(string $key, string $label, array $fields, array $actions = []): void
    {
        if (! app()->isProduction()) {
            self::$fixtures[$key] = [
                'label'   => $label,
                'actions' => $actions,
                'fields'  => array_map(function (array $f): array {
                    $value = $f['value'];

                    return [
                        'field'      => $f['field'],
                        'label'      => $f['label'],
                        'value'      => $value instanceof Closure ? $value() : $value,
                        'variations' => $f['variations'] ?? [],
                    ];
                }, $fields),
            ];
        }
    }

    /**
     * Return all registered fixture groups and reset for next request.
     *
     * @return array<string, mixed>
     */
    public static function flushFixtures(): array
    {
        $fixtures = self::$fixtures;
        self::$fixtures = [];

        return $fixtures;
    }
}
