# Contributing

Contributions are welcome through
[github.com/RentnerKev/UserLockdown](https://github.com/RentnerKev/UserLockdown).

## Development workflow

1. Create a focused branch.
2. Keep PHP code aligned with the Nextcloud coding standard and frontend code
   aligned with the existing React/TypeScript components.
3. Add tests for behavior changes.
4. Run `make check`.
5. Use Conventional Commit messages such as `fix(dav): block a mutation verb`.

Do not commit private keys, certificates containing private material, generated
dependency directories, or development credentials. Database changes must use
Nextcloud's migration and query-builder APIs and remain compatible with all
databases supported by Nextcloud.

By contributing, you agree that your contribution is licensed under
AGPL-3.0-or-later.
