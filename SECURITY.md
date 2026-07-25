# Security policy

## Supported versions

Security fixes are provided for the latest User Lockdown release on supported
Nextcloud versions listed in `appinfo/info.xml`.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability. Use
[GitHub private vulnerability reporting](https://github.com/RentnerKev/UserLockdown/security/advisories/new)
with:

- the affected User Lockdown and Nextcloud versions;
- a concise reproduction;
- the observed and expected result;
- any proof-of-concept request, with secrets removed.

Receipt should be acknowledged within seven days. A disclosure timeline will be
coordinated after triage.

## Scope note

User Lockdown is defense in depth inside Nextcloud. Its documented trust
boundaries are described in `README.md`; reports that cross those boundaries are
still welcome when the boundary can be tightened safely.
