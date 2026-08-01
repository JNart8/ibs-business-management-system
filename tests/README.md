# IBS Sales App test suite

Run the complete non-destructive suite from the project root:

```powershell
npm test
# or: php tests/run.php
```

The suite deliberately does not connect to the configured database. It is safe
to run against a development machine that also contains real data.

## Coverage layers

- `unit/helpers_test.php` executes reusable formatting, security, session and
  financial-account rules with an in-memory fake database.
- `contract/application_contract_test.php` checks PHP syntax across the app,
  route/controller/view wiring, authorization boundaries, database domains,
  imports, reports and exports.
- `functional-matrix.md` is the database-backed acceptance/regression script.
  Execute it against a disposable database after applying all files in
  `database/`; never point destructive scenarios at production.

The custom runner has no Composer dependency and returns a non-zero exit code
on failure, so it can be used directly in CI.

