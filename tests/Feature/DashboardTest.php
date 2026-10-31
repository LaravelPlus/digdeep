<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->profile = $this->profile('/orders', function () {
        foreach (range(1, 5) as $i) {
            DB::select("select * from digdeep_profiles where id = '{$i}'");
        }

        return response('<html><body>orders</body></html>')->header('Content-Type', 'text/html');
    });
});

it('renders every dashboard page', function (string $uri): void {
    $this->get($uri)->assertOk();
})->with([
    '/digdeep', '/digdeep/pipeline', '/digdeep/security', '/digdeep/audits', '/digdeep/urls',
    '/digdeep/database', '/digdeep/errors', '/digdeep/compare', '/digdeep/profiler', '/digdeep/export',
    '/digdeep/trends', '/digdeep/performance', '/digdeep/cache', '/digdeep/logs',
]);

it('renders the dashboard with no profiles', function (): void {
    $this->post('/digdeep/api/clear');

    $this->get('/digdeep')->assertOk();
});

it('renders a profile and 404s for unknown ones', function (): void {
    $this->get('/digdeep/profile/'.$this->profile->id)->assertOk()->assertSee('/orders');
    $this->get('/digdeep/profile/does-not-exist')->assertNotFound();
});

it('rejects log path traversal', function (): void {
    $this->get('/digdeep/logs?file=../../.env')->assertOk()->assertDontSee('APP_KEY');
});
