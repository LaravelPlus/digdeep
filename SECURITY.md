# Security Policy

DigDeep records request data and can run artisan commands in `local`, so please report vulnerabilities privately.

## Reporting

Use GitHub's private advisory form: **Security → Report a vulnerability** on this repository. Please don't open a public issue for security problems.

Include the affected version or commit, steps to reproduce and the impact. Expect a first reply within a few days.

## Supported versions

Only the latest release receives security fixes.

## Hardening checklist for apps

- Keep DigDeep a `require-dev` dependency, or leave `DIGDEEP_ENABLED` unset outside `local`.
- If you enable it on a shared environment, register a `DigDeep::auth()` gate and keep `DIGDEEP_CODE_ACTIONS` off.
- Don't profile against production data: SQL bindings are stored as-is.
