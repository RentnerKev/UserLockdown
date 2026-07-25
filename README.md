# User Lockdown

User Lockdown is a Nextcloud security app that places selected, non-admin users
into a deliberately narrow read-only mode. Restricted users can sign in, sign
out, browse existing files, preview them, and download them. Administrators keep
full control and manage the restricted-user list in the administration settings.

Version 1.0.0 supports Nextcloud 32–34 and PHP 8.2–8.5.

## Security boundary

The app enforces restrictions server-side:

- WebDAV permits only `GET`, `HEAD`, `OPTIONS`, `PROPFIND`, and `REPORT` on the
  authenticated user's Files tree.
- Uploads, chunk uploads, create, write, rename, copy, move, touch, and delete
  operations are rejected.
- AppFramework controllers outside the read-only Files surface and logout are
  rejected for restricted sessions.
- Share creation, share interaction, password changes, and lost-password reset
  completion are rejected.
- Administrators are never restricted, even if a stale database row exists.
- Deleting a user removes its restriction row.

The Files UI also removes write controls and shows a persistent read-only notice.
Those visual changes are usability aids; server-side DAV, middleware, and event
guards are the security controls. User Lockdown covers authenticated Nextcloud
web and WebDAV requests; CLI commands, background jobs, anonymous public-upload
links, and mutations performed entirely inside third-party code are outside this
boundary.

## Installation

Verify and extract the release archive so the final directory is
`custom_apps/user_lockdown`, then enable it:

```console
sha256sum -c user_lockdown.tar.gz.sha256
tar -xzf user_lockdown.tar.gz -C /path/to/nextcloud/custom_apps
cd /path/to/nextcloud
php occ app:enable user_lockdown
```

When running Nextcloud under a dedicated web-server account, execute `occ` as
that account. Never merge a new release into an old app directory because stale
files invalidate the integrity signature.

## Administration

Open **Administration settings → Security → User Lockdown**. Search for a
non-admin user and add or remove the restriction. The change applies on the
user's next request.

## Local development

Requirements: Docker with Compose, Bun 1.2 or newer, and optionally Composer 2
plus PHP 8.2–8.5.

```console
bun install --frozen-lockfile
bun run build
docker compose up -d db redis mailpit nextcloud
docker compose run --rm bootstrap
```

Development endpoints and credentials:

| Service/account | URL or credentials |
| --- | --- |
| Nextcloud | `http://localhost:8080` |
| Administrator | `admin` / `admin-dev-password` |
| Restricted user | `restricted` / `restricted-dev-password` |
| Normal user | `normal` / `normal-dev-password` |
| Mailpit | `http://localhost:8025` |

These credentials are development-only and must never be used in production.

Run all checks with `make check`; use
`sh ./tests/integration/webdav-read-only.sh` for the WebDAV integration suite
after the development stack has started.

## Release

`scripts/package.sh` and `scripts/package.ps1` build a release containing only
runtime assets and documentation. The result is:

```text
build/user_lockdown.tar.gz
build/user_lockdown.tar.gz.sha256
```

The archive always has the required top-level `user_lockdown/` directory. App
Store releases must additionally be signed with the certificate issued for the
`user_lockdown` app ID. Publishing a GitHub release triggers the signed release
workflow after the `APP_PRIVATE_KEY`, `APP_PUBLIC_CRT`, and `APPSTORE_TOKEN`
secrets have been configured in the protected `release` environment.

## Project links

- [Source repository](https://github.com/RentnerKev/UserLockdown)
- [Issue tracker](https://github.com/RentnerKev/UserLockdown/issues)
- [Private security report](https://github.com/RentnerKev/UserLockdown/security/advisories/new)

## License

Copyright © 2026 Kevin Sträßler.

Licensed under the GNU Affero General Public License, version 3 or later
(`AGPL-3.0-or-later`). See [LICENSE](LICENSE).
