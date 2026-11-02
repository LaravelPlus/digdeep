# Changelog

All notable changes are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [2.0.0] - 2026-11-02

### Upgrading from 1.x
- Outside `local`/`testing`, DigDeep is off unless `DIGDEEP_ENABLED=true`. Viewers rejected by the `DigDeep::auth()` gate get a 404 instead of a 403.
- `run-command` and `ai-apply` return 403 outside `local` unless `DIGDEEP_CODE_ACTIONS=true`.
- POST and DELETE API calls need a CSRF token.
- Text/XML request bodies are no longer stored. SQL bindings on sensitive columns, log context and JSON response bodies are now redacted.
- `Controllers`, `Middleware` and `Commands` moved to `Http\Controllers`, `Http\Middleware` and `Console\Commands`. Update any direct references to them.
- Pruning now runs on 1 in 10 writes (`prune_lottery`), so the table can briefly exceed `max_profiles`. Set `[1, 1]` for the old behaviour.

### Security
- Disabled by default outside `local`/`testing`. Dashboard routes use the `web` group (CSRF). Denied requests get a 404.
- Artisan runner and AI "apply fix" are limited to `local` unless `DIGDEEP_CODE_ACTIONS=true`.
- The debugbar is shown only to viewers who pass the `DigDeep::auth()` gate.
- XSS fixes in HTML exports and debugbar. CDN scripts pinned with SRI.
- Wider redaction (payload, bodies, session, auth headers).
- One config-driven redactor (`redact.keys`, `redact.headers`) now also covers SQL bindings, log context, response bodies and HTTP client bodies. `DIGDEEP_CAPTURE_RESPONSE_BODIES=false` turns response body capture off.

### Fixed
- On-demand profiles (trigger, replay, MCP `profile-url`) record the matched route; unknown URLs are stored as 404, not 500.
- The logs page no longer errors on an empty log file; `ai-investigate` handles trace frames without a function.
- Query callers are detected when the app's own path contains `digdeep`.
- Streamed and binary responses (downloads, SSE) are no longer profiled. New `sample_rate` option (`DIGDEEP_SAMPLE_RATE`) profiles only a share of requests.
- `max_profile_size_kb` is enforced: oversized profiles drop their largest sections until they fit. Collectors keep at most `limits.max_entries` entries and shorten long strings. Pruning runs on a lottery (`prune_lottery`) and deletes in chunks.
- Listeners are registered once per worker, so Octane/FrankenPHP no longer pile up listeners or leak data between requests.
- AI endpoints return 501 instead of crashing when `laravel/ai` is missing.
- AI model and provider defaults apply when env is unset.
- Database page, EXPLAIN and MCP schema work on MySQL/MariaDB and PostgreSQL.
- Profiling no longer errors on non-Laravel responses with status >= 400.

### Changed
- Test suite covers 100% of `src/`, enforced in CI. `laravel/ai` is a dev dependency so the AI features are tested.
- Classes moved to `Http\Controllers`, `Http\Middleware` and `Console\Commands`.
- Migrations publishable via the `digdeep-migrations` tag.

### Added
- Pest + Testbench suite, Pint, Larastan and a CI matrix (PHP 8.4/8.5 × Laravel 12/13).

[Unreleased]: https://github.com/LaravelPlus/digdeep/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/LaravelPlus/digdeep/compare/148f71f...v2.0.0
