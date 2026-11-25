## What and why

<!-- What changes, and which problem it solves. Link the issue: Closes #123 -->

## How to review

<!-- Where a reviewer should start; anything surprising; screenshots for UI changes. -->

## Type

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor (no behaviour change)
- [ ] Documentation / tooling
- [ ] Breaking change (API contract or database)

## Checklist

- [ ] `php artisan test` passes
- [ ] `vendor/bin/pint --test` passes
- [ ] `npm run build` passes (for frontend changes)
- [ ] Tests added or updated for the new behaviour
- [ ] `docs/API.md` updated for API changes
- [ ] `CHANGELOG.md` updated under `Unreleased`
- [ ] No secrets, `.env` files or build output committed
