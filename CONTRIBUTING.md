# Contributing

1. Open an issue first for anything bigger than a small fix.
2. Fork, then branch from `main` (`fix/...`, `feat/...`).
3. Run the checks before pushing:

   ```bash
   composer test      # Pest + Testbench
   composer lint      # Pint (composer format to fix)
   composer analyse   # Larastan
   ```

4. Add a test that fails without your change.
5. Open a PR that links the issue (`Closes #n`) and describes any breaking change.

Security problems: see [SECURITY.md](SECURITY.md).
