# DigDeep

A Laravel request profiler that captures the complete execution flow of every request — queries, events, views, cache, mail, jobs, HTTP calls, Inertia props, Eloquent models, and full lifecycle timing — all in a Dracula-themed dashboard.

## Requirements

- PHP 8.4+
- Laravel 12+
- `ext-pdo_sqlite`

## Installation

Add the package to your `composer.json` repositories:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/laravelplus/digdeep"
        }
    ]
}
```

Then install:

```bash
composer require laravelplus/digdeep --dev
```

DigDeep auto-registers via its service provider. No further setup is needed.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=digdeep-config
```

Available options in `config/digdeep.php`:

| Option | Default | Description |
|--------|---------|-------------|
| `enabled` | `true` | Master on/off switch (env: `DIGDEEP_ENABLED`) |
| `auto_profile` | `true` | Automatically profile every web request |
| `storage_path` | `storage/digdeep/digdeep.sqlite` | SQLite database path |
| `max_profiles` | `200` | Maximum stored profiles (auto-prunes oldest) |
| `ignored_paths` | `[...]` | URL prefixes to skip profiling |

## Usage

Once installed, visit:

```
http://your-app.test/digdeep
```

DigDeep only activates in `local` and `testing` environments by default.

### Auto-Profiling

When `auto_profile` is enabled, every web request is automatically captured. Browse your application normally and profiles will appear in the dashboard.

### Manual Profiling

Use the URL input on the dashboard to profile any route on demand. Enter a URL, select an HTTP method, and click "Profile". The request is executed server-side and the full execution flow is captured.

### API

DigDeep exposes a small API for programmatic use:

```
POST   /digdeep/api/trigger          Profile a URL (params: url, method)
DELETE /digdeep/api/profile/{id}     Delete a single profile
POST   /digdeep/api/clear            Delete all profiles
```

## Dashboard Pages

### Web (Dashboard)

The main view. Shows all captured profiles with stats, a response time chart, manual profiler, and search/filter controls.

- 4 stat cards: total profiles, avg duration, avg queries, avg memory
- Response time distribution chart (last 30 requests)
- Searchable, filterable profile list
- Top routes sidebar with visit counts

### Profile Detail

Click any profile to see the full execution breakdown:

- **Queries** — Every SQL query with bindings, execution time, caller location, and N+1 detection
- **Route** — Matched route name, action, parameters, middleware stack
- **Events** — All dispatched events with payload summaries
- **Views** — Rendered Blade templates with data keys passed
- **Cache** — Cache hits, misses, and writes with keys
- **Inertia** — Component name, URL, version, and all props sent to the Vue component
- **Mail** — Sent emails with subject and recipients
- **HTTP Client** — Outgoing HTTP requests with method, URL, status, and duration
- **Jobs** — Dispatched queue jobs with class and queue name
- **Request** — Full request headers (sanitized) and payload
- **Response** — Status code, headers, and response body

### Pipeline

A route-first view of your application. Lists all registered routes, then click any route to see a vertical lifecycle traceback:

1. **Request Received** — Method, URL, headers, payload, body
2. **Middleware Pipeline** — Every middleware layer in execution order
3. **Route Matched** — Route name, action, parameters
4. **Controller Action** — Executing action with all queries, events, cache ops, models, mail, HTTP calls, and jobs
5. **View Rendering** — Rendered views, Inertia component with full prop data
6. **Response Sent** — Status, duration, memory, headers, body, exceptions

Also shows all loaded service providers.

### Security

Scans profiled requests for common issues:

- Missing CSRF protection on POST/PUT/PATCH/DELETE routes
- Missing security headers (X-Content-Type-Options)
- Potentially dangerous SQL patterns

### Audits

Route performance analysis across all profiles:

- Visit count, avg/min/max duration, avg queries
- Error rate per route
- Status code distribution

### Database

Query analysis dashboard:

- Read/write breakdown with totals and averages
- Table access patterns (reads and writes per table)
- Top 20 slow queries (>5ms) with caller locations
- Full SQLite schema introspection (tables, columns, types, indexes, foreign keys, row counts)

### Errors

Exception tracking:

- All captured exceptions with stack traces
- Grouped by exception class with counts
- Error rate across all profiled requests

### URLs

Top routes by visit count with visual bar chart.

## What Gets Captured

Every profiled request records:

| Category | Data |
|----------|------|
| **Request** | Method, URL, headers (sanitized), payload, raw body (up to 8KB) |
| **Response** | Status code, headers, body (up to 16KB), size |
| **Queries** | SQL, bindings, execution time (ms), caller file:line |
| **Events** | Event class/name, payload summary |
| **Views** | Template name, file path, data keys, render timestamp |
| **Cache** | Key, operation type (hit/miss/write) |
| **Mail** | Subject, recipients |
| **HTTP Client** | Method, URL, response status, transfer time |
| **Jobs** | Job class, queue name |
| **Inertia** | Component, URL, version, prop summaries, full prop data |
| **Models** | Model class, operation counts (retrieved/created/updated/deleted) |
| **Lifecycle** | Phase timing (bootstrap, routing, controller, view rendering, response) |
| **Performance** | Total duration (ms), peak memory (MB), query count, query time (ms) |
| **Exceptions** | Class, message, file, line, stack trace (30 frames), previous exception |

## Sensitive Data

DigDeep automatically sanitizes:

- `authorization`, `cookie`, and `set-cookie` headers are redacted
- `password` and `password_confirmation` fields are excluded from payloads
- Request/response bodies are truncated to prevent storage bloat

## Storage

Profiles are stored in a local SQLite database at `storage/digdeep/digdeep.sqlite`. The database is created automatically on first use.

Auto-pruning keeps only the latest `max_profiles` entries (default: 200).

## Environment Restriction

By default, DigDeep only activates when `APP_ENV` is `local` or `testing`. To enable in other environments, set:

```env
DIGDEEP_ENABLED=true
```

## Tech Stack

- **Backend**: Laravel 12, SQLite, PHP 8.4
- **Frontend**: Vue 3 (CDN), Tailwind CSS v4 with Dracula theme
- **Storage**: PDO SQLite with JSON data column
- **Middleware**: Global HTTP middleware with sub-request profiling

## License

MIT
