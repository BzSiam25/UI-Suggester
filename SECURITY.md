# Security policy

## Supported versions

Security fixes land on `main` and are released from the latest tag. Older tags are not maintained.

| Version | Supported |
|---|---|
| `1.x` (latest) | ✅ |
| < `1.0` | ❌ |

## Reporting a vulnerability

Please **do not** open a public issue for security problems.

Email **bayazidsiam5678@gmail.com** with:

- a description of the issue and its impact,
- the affected endpoint, file or configuration,
- reproduction steps or a proof of concept,
- the version or commit you tested.

You will get an acknowledgement within a few days and an assessment with a fix timeline. Please give
us time to ship a fix before publishing details. Reporters are credited in the release notes unless
they ask to stay anonymous.

## Scope

In scope:

- Authentication and authorization bypasses (Sanctum tokens, admin middleware, ownership checks)
- IDOR on designs, generations, comments, bookmarks and collections
- Stored or reflected XSS through generated HTML, comments, tags or profile fields
- Upload handling: type confusion, path traversal, decompression bombs, storage exposure
- SQL injection, mass assignment, sensitive data exposure in API resources
- Rate-limit bypass and account-suspension bypass

Out of scope:

- Findings that require a compromised device, browser extension or admin account
- Missing headers with no demonstrable impact, or automated-scanner output without a proof of concept
- Denial of service through sheer volume, or reports about the demo seed accounts existing
- Self-XSS, clickjacking of pages with no sensitive action, or social engineering

## Hardening checklist for deployments

- Change or delete the seeded `admin@uispark.test` and `demo@uispark.test` accounts.
- Set `APP_ENV=production`, `APP_DEBUG=false` and a fresh `APP_KEY`.
- Serve the API over HTTPS and restrict `FRONTEND_URL` (CORS) to your real origin.
- Keep `ANTHROPIC_API_KEY`, `EXA_API_KEY` and database credentials in the environment, never in the
  repository or the built frontend bundle.
- Run `php artisan storage:link` and ensure `backend/storage` is not publicly listable beyond the
  linked `public/storage` directory.
- Review the rate limits in `routes/api.php` for your traffic, and put a proxy in front if you need
  IP-based blocking.
