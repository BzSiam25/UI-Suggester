# Contributing to UISpark

Thanks for taking the time to contribute. This document covers the local workflow, the standards
the project enforces in CI, and how to get a change merged.

## Before you start

- Search existing issues and pull requests; someone may already be working on it.
- For anything larger than a bug fix, open an issue first so the approach can be agreed before you
  write code.
- For security problems, do **not** open a public issue — follow [SECURITY.md](SECURITY.md).

## Development setup

Follow [Getting started](README.md#getting-started). In short:

```bash
# API
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

# SPA
cd frontend
npm install
cp .env.example .env
npm run dev
```

The seeder creates `admin@uispark.test` and `demo@uispark.test`, both with password `password`.

## Workflow

1. Fork the repository and create a topic branch off `main`:
   `git switch -c fix/gallery-color-filter`.
2. Keep commits focused — one logical change per commit.
3. Run the checks below before pushing.
4. Open a pull request against `main` and fill in the template.

### Commit messages

The project follows [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <imperative summary>

<body: why the change was needed, not what the diff shows>
```

Common types: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf`. Example scopes:
`api`, `generator`, `gallery`, `admin`, `ui`, `deps`.

## Checks

CI runs these on PHP 8.2–8.4 and Node 20–22; run them locally first:

```bash
cd backend
php artisan test
vendor/bin/pint --test   # auto-fix with: vendor/bin/pint

cd ../frontend
npm run build
```

Rules of thumb:

- New API behaviour needs a feature test in `backend/tests/Feature`.
- Never weaken or delete a test to make a change pass.
- Formatting is enforced by Pint; run it rather than hand-formatting.
- Frontend changes must keep `npm run build` green.

## Code style

**PHP** — Laravel conventions via Pint. Prefer constructor injection, typed properties and early
returns. Put logic that outgrows a controller or model into `app/Services`.

**JavaScript/React** — functional components with hooks, no class components. Data fetching goes
through `src/hooks.js` and `src/api/client.js`; components take props and stay presentational.
Two-space indentation, single quotes, no semicolon-free style changes across a file.

**CSS** — one stylesheet (`src/styles.css`) using the existing custom properties. Reuse tokens
rather than hard-coding colours, radii or spacing.

**Database** — one migration per change, never edit a migration that has shipped. Name indexes and
foreign keys explicitly.

## Pull request checklist

- [ ] The change is described, with the problem it solves
- [ ] Tests cover the new behaviour (or the PR explains why none are needed)
- [ ] `php artisan test`, `vendor/bin/pint --test` and `npm run build` pass
- [ ] API changes are reflected in `docs/API.md`
- [ ] No secrets, credentials, `.env` files or build output are committed

## Review

Maintainers aim to respond within a few days. Expect feedback on scope, naming and tests; a small,
well-tested PR is merged faster than a large one. By contributing you agree that your work is
licensed under the project's [MIT license](LICENSE).
