# Contributing

Contributions are welcome through
[github.com/RentnerKev/UserLockdown](https://github.com/RentnerKev/UserLockdown).

Use an [issue template](https://github.com/RentnerKev/UserLockdown/issues/new/choose)
for bugs, feature requests, documentation improvements, and questions. Report
suspected vulnerabilities through the [private security reporting
form](https://github.com/RentnerKev/UserLockdown/security/advisories/new), not a
public issue.

## Development workflow

1. Fork the repository or create a focused branch and open a pull request against
   `main`. Direct pushes to `main` are reserved for repository administrators.
2. Keep PHP code aligned with the Nextcloud coding standard and frontend code
   aligned with the existing React/TypeScript components.
3. Add tests for behavior changes.
4. Run the relevant checks below. If frontend sources change, run `bun run build`
   and commit the generated `css/` and `js/` files.
5. Use Conventional Commit messages such as `fix(dav): block a mutation verb`.
6. Fill in the pull request template and link the related issue when applicable.

## Checks

`make check` runs the full local suite when Bun, PHP, and Composer are available.
The frontend checks can also be run separately:

```console
bun install --frozen-lockfile
bun run typecheck
bun run lint
bun run format:check
bun run test
bun run build
```

For PHP changes, run `composer validate --strict`, `composer install`,
`composer cs:check`, `composer phpstan`, and `composer test`. GitHub Actions runs
these checks on PHP 8.2–8.5 and the integration suite on Nextcloud 32–34.
The required CI checks and a code-owner review must pass before a contributor's
pull request can be merged.

Do not commit private keys, certificates containing private material, generated
dependency directories, or development credentials. Database changes must use
Nextcloud's migration and query-builder APIs and remain compatible with all
databases supported by Nextcloud.

By contributing, you agree that your contribution is licensed under
AGPL-3.0-or-later.
