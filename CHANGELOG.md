# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `composer.lock` required PHP 8.4 (Symfony 8 packages) although the project supports PHP 8.2+;
  Composer now resolves for PHP 8.2 (`config.platform.php`), so CI passes on PHP 8.2 and 8.3.
- `php artisan test` failed because the empty `tests/Unit` directory was not committed.
- Code style (Pint) violations in `ProfileController`, `ProjectScaffold` and `config/services.php`.

### Security

- Frontend: upgraded `react-router-dom` 6 → 7 (open-redirect advisories) and `vite` 5 → 7 with
  `@vitejs/plugin-react` 5 (esbuild dev-server advisory). `npm audit` now reports 0 vulnerabilities.

## [1.1.0] — 2026-11-25

### Added

- Web research before every mockup: `InspirationScout` derives a search query from the prompt,
  queries Exa when `EXA_API_KEY` is set, falls back to a keyless HTML search and then to a curated
  reference gallery, caching results for six hours.
- `DesignDirection`: eight seeded design languages (editorial, Swiss, quiet minimal, soft SaaS,
  console, glass neon, pop, craft) with typography, radius, accent and a layout recipe, so repeated
  prompts no longer produce identical pages.
- Local model provider: any OpenAI-compatible `/v1/chat/completions` endpoint via
  `LOCAL_AI_BASE_URL`, preferred over Claude when configured.
- `meta` column on `ui_generations` storing the design brief (direction, recipe, research source and
  references); exposed as `design` in the API and shown in the app.
- "New direction" action in the mockup viewer and a "Design note" panel with the brief and its
  inspiration links.
- System prompt and normalisation rules for model output, including auto-closing a document cut off
  by the token budget.

### Changed

- `TemplateEngine` now composes pages from section variants driven by a direction instead of a fixed
  layout, with Google Fonts, type scale and spacing tokens per language.
- Generator falls back to the template engine with a user-facing notice when a provider fails.

## [1.0.0] — 2026-11-23

### Added

- Laravel 12 API with Sanctum token auth, roles and banned-user middleware.
- Design gallery: filtering by domain, platform, screen type, colour and tag; four sort modes.
- Uploads with image validation, GD palette extraction and colour-family naming.
- Moderation queue with rejection reasons, dashboard stats, user and domain management.
- Likes, bookmarks, named collections and one-level threaded comments with cached counters.
- Mockup generator with offline template engine, screen types, styles and shareable output.
- React SPA: explore, detail, upload, generate, showcase, profile and admin screens.
- Feature tests covering auth, upload → moderation → gallery, interactions and generation.

## [0.0.1] — 2026-11-17

### Added

- Repository initialised with the project license.

[Unreleased]: https://github.com/BzSiam25/UI-Suggester/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/BzSiam25/UI-Suggester/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/BzSiam25/UI-Suggester/releases/tag/v1.0.0
