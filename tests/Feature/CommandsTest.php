<?php

declare(strict_types=1);

use LaravelPlus\DigDeep\Models\DigDeepProfile;

beforeEach(function (): void {
    foreach (range(1, 4) as $i) {
        $this->profile("/c{$i}");
    }
});

it('shows status', function (): void {
    $this->artisan('digdeep:status')->assertSuccessful()->expectsOutputToContain('Profiles stored');
});

it('prunes keeping the latest n', function (): void {
    $this->artisan('digdeep:prune', ['--keep' => 2])->assertSuccessful();

    expect(DigDeepProfile::query()->count())->toBe(2);
});

it('clears everything', function (): void {
    $this->artisan('digdeep:clear')->assertSuccessful();

    expect(DigDeepProfile::query()->count())->toBe(0);
});
