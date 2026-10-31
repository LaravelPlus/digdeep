# Changelog

All notable changes are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Security
- Disabled by default outside `local`/`testing`. Dashboard routes use the `web` group (CSRF). Denied requests get a 404.
- Artisan runner and AI "apply fix" are limited to `local` unless `DIGDEEP_CODE_ACTIONS=true`.
- The debugbar is shown only to viewers who pass the `DigDeep::auth()` gate.
- XSS fixes in HTML exports and debugbar. CDN scripts pinned with SRI.
- Wider redaction (payload, bodies, session, auth headers).
- One config-driven redactor (`redact.keys`, `redact.headers`) now also covers SQL bindings, log context, response bodies and HTTP client bodies. `DIGDEEP_CAPTURE_RESPONSE_BODIES=false` turns response body capture off.

### Fixed
- AI endpoints return 501 instead of crashing when `laravel/ai` is missing.
- AI model and provider defaults apply when env is unset.
- Database page, EXPLAIN and MCP schema work on MySQL/MariaDB and PostgreSQL.
- Profiling no longer errors on non-Laravel responses with status >= 400.

### Changed
- Classes moved to `Http\Controllers`, `Http\Middleware` and `Console\Commands`.
- Migrations publishable via the `digdeep-migrations` tag.

### Added
- Pest + Testbench suite, Pint, Larastan and a CI matrix (PHP 8.4/8.5 × Laravel 12/13).
