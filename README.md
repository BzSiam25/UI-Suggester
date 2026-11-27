<div align="center">

# UISpark

**A UI inspiration gallery with a research-driven mockup generator.**

Describe a product in any domain — plumbing, pharmacy, fintech, farming — and UISpark designs
a single-file HTML screen for it, grounded in real interface references gathered from the web.

[![CI](https://github.com/BzSiam25/UI-Suggester/actions/workflows/ci.yml/badge.svg)](https://github.com/BzSiam25/UI-Suggester/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![React](https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black)](https://react.dev/)
[![Vite](https://img.shields.io/badge/Vite-7-646CFF?logo=vite&logoColor=white)](https://vite.dev/)

[Features](#features) · [How it works](#how-generation-works) · [Getting started](#getting-started) · [Configuration](#configuration) · [API](docs/API.md) · [Architecture](docs/ARCHITECTURE.md) · [Contributing](CONTRIBUTING.md)

</div>

---

## Overview

UISpark is a two-part application:

- **A community gallery of real UI screens** — browse by domain, platform, screen type and colour,
  filter and search, like, bookmark into collections, and discuss in threaded comments.
- **A UI + code generator** — pick a language and framework, the screens and the domain (or add
  your own), describe the product, and a **Qwen model running on your own computer** researches
  similar products on the web, designs every screen and writes the code files. Preview each screen,
  read the code, and download the project as a ZIP.

No AI API keys are used: the model runs locally (LM Studio or Ollama) and web research is keyless.

## Features

| Area | Highlights |
|---|---|
| **Explore** | Gallery of approved screens, filtered by domain, platform (web/mobile/tablet), screen type, dominant colour, tag; sorts for latest, trending, popular and most viewed |
| **Generator** | Local Qwen only. Languages, frameworks, screens and domains come from the database and users can add their own; up to 8 screens per run; web research → plan → HTML preview per screen → framework code → routing/config/README; queued with live progress, retry and ZIP download |
| **Community** | Upload screenshots (moderated before publication), likes, bookmarks with named collections, one-level threaded comments, tags created on the fly |
| **Colour extraction** | Palettes sampled from every upload with GD, merged perceptually, named into colour families for filtering, shown as click-to-copy swatches |
| **Accounts** | Token auth via Sanctum, profile editing, role-based admin access, user suspension |
| **Admin** | Dashboard stats, moderation queue with rejection reasons, user management, domain management |
| **Shareable output** | Public showcase of shared mockups, raw HTML endpoint, sandboxed preview iframe |

## Tech stack

| Layer | Choice |
|---|---|
| API | PHP 8.2+, Laravel 12, Sanctum, MySQL 8 (SQLite in tests) |
| Web app | React 18, React Router 7, Vite 7, hand-written CSS |
| Generation | Qwen on a local OpenAI-compatible server (LM Studio / Ollama), run by a Laravel queue worker |
| Research | Keyless DuckDuckGo search; the top pages are read for headings, navigation, buttons, colours and fonts |

## How generation works

```mermaid
flowchart LR
    A["Language + framework<br/>screens + domains<br/>requirements"] --> B["POST /api/generations<br/><em>queued</em>"]
    B --> C["InspirationScout<br/><em>web search + page study</em>"]
    C --> D["Qwen: plan<br/><em>brand, palette, screen contents</em>"]
    D --> E["Qwen: HTML preview<br/><em>per screen</em>"]
    E --> F["Qwen: framework code<br/><em>per screen</em>"]
    F --> G["ProjectScaffold<br/><em>router, config, README</em>"]
    G --> H[("ui_generations<br/>previews + files")]
```

1. **Queue** — the request is validated against `generator_options` and queued; the browser polls
   `GET /api/generations/{id}` and shows each step.
2. **Research** — `InspirationScout` searches the web for the chosen domains and screens and opens
   the top pages to note their headings, navigation, calls to action, colours and fonts.
3. **Plan** — Qwen fixes an original brand, palette, font and what every screen contains.
4. **Design** — Qwen writes a self-contained HTML preview for each screen.
5. **Code** — Qwen rewrites each preview in the chosen framework (`FrameworkCatalog` holds file
   layouts and rules; user-added frameworks get generic rules). For HTML/Tailwind/Bootstrap the
   preview is the code.
6. **Scaffold** — `ProjectScaffold` adds the entry point, router, package/config files and a README.

Details and extension points: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Repository layout

```
uispark/
├── backend/                  Laravel API (http://localhost:8000/api)
│   ├── app/Http/Controllers/Api   REST controllers (Auth, Design, Generation, Admin…)
│   ├── app/Services/              PaletteExtractor, Generator/{UiGenerator, DesignDirection, InspirationScout, TemplateEngine, DomainProfiles}
│   ├── database/                  migrations, seeders (domains, demo accounts, sample uploads)
│   ├── routes/api.php             every endpoint
│   └── tests/Feature              API, generator and local-AI tests
├── frontend/                 React app (http://localhost:5173)
│   └── src/                  pages, components, contexts, hooks
├── docs/                     architecture and API reference
└── .github/                  CI, issue and PR templates
```

## Getting started

### Requirements

- PHP **8.2+** with `gd`, `pdo_mysql`, `mbstring`, `fileinfo`
- Composer **2**
- MySQL **8** (or MariaDB 10.6+)
- Node.js **20.19+** (or 22.12+) and npm

### 1. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Create the database and point `backend/.env` at it:

```sql
CREATE DATABASE uispark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```env
DB_DATABASE=uispark
DB_USERNAME=root
DB_PASSWORD=your_password
```

Then migrate, link storage and serve:

```bash
php artisan migrate --seed     # tables + domains + demo accounts + sample uploads
php artisan storage:link       # makes uploaded images publicly reachable
php artisan serve              # http://localhost:8000
```

### 2. Frontend

```bash
cd frontend
npm install
cp .env.example .env           # VITE_API_URL=http://localhost:8000/api
npm run dev                    # http://localhost:5173
```

`FRONTEND_URL` in `backend/.env` must match the frontend origin (CORS). For production, run
`npm run build` and serve `frontend/dist/` from a static host, routing all paths to `index.html`.

### 3. Sign in

Accounts created by the seeder (change or delete them before any public deployment):

| Role | Email | Password |
|---|---|---|
| Admin | `admin@uispark.test` | `password` |
| User | `demo@uispark.test` | `password` |

## Configuration

All backend variables live in `backend/.env` (see [`backend/.env.example`](backend/.env.example)).
Only the database values are required; everything below is optional.

| Variable | Default | Purpose |
|---|---|---|
| `APP_URL` | `http://localhost:8000` | API origin |
| `FRONTEND_URL` | `http://localhost:5173` | Allowed CORS origin |
| `DB_CONNECTION` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `mysql` / `uispark` / `root` / – | Database |
| `LOCAL_AI_BASE_URL` | `http://127.0.0.1:1234/v1` | OpenAI-compatible endpoint of the local model (must be localhost / private) |
| `LOCAL_AI_MODEL` | `qwen2.5-coder-7b-instruct` | Model identifier to request |
| `LOCAL_AI_TIMEOUT` | `900` | Seconds per model request |
| `LOCAL_AI_PLAN_TOKENS` / `LOCAL_AI_PREVIEW_TOKENS` / `LOCAL_AI_CODE_TOKENS` | `600` / `2600` / `3000` | Response budget per step |
| `INSPIRATION_SEARCH` | `true` | Set `false` to skip web research |
| `INSPIRATION_TIMEOUT` | `8` | Research request timeout (seconds) |

Frontend: `VITE_API_URL` (default `/api`, proxied by Vite to `http://127.0.0.1:8000`).

**Running the generator:** in LM Studio open *Developer → Local Server*, switch the server on
(port 1234) and load the Qwen model; then run `php artisan queue:work --timeout=0` (or
`start-dev.bat` on Windows, which also runs migrations and starts the API and the web app).
On a CPU a 3B model needs roughly 3–8 minutes per screen and step; a 7B model on a GPU is much faster.

## Quality checks

```bash
cd backend
php artisan test          # feature tests on in-memory SQLite
vendor/bin/pint --test    # code style

cd ../frontend
npm run build             # production build
```

The same commands run in CI on every push and pull request (PHP 8.2–8.4, Node 20–22).

## API

Every route is under `/api` and documented in [docs/API.md](docs/API.md), with auth flow, filters,
request bodies and error shapes. Authentication is a Sanctum bearer token returned by
`POST /api/auth/login`.

Rate limits: login/register 10 per minute, uploads 20 per hour, comments 30 per minute,
generations 10 per minute.

## Security

- Generated HTML renders in `<iframe sandbox="allow-scripts">` **without** `allow-same-origin`, so
  a mockup cannot read the viewer's token or call the API; the raw endpoint also sends
  `Content-Security-Policy: sandbox`.
- Uploads are validated as real images (JPG/PNG/WebP, ≤ 8 MB, ≥ 200 px) and stored under random names.
- Suspended users lose all tokens and are blocked by middleware.
- Secrets stay in `.env`; nothing in this repository contains credentials.

To report a vulnerability, follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

## Roadmap

- Email notifications when an upload is approved or rejected
- Full-text search (`FULLTEXT` or Meilisearch via Laravel Scout)
- "Upload from URL": capture a live site with a headless browser
- Export a generated mockup straight into the gallery as a design

## Contributing

Issues and pull requests are welcome — read [CONTRIBUTING.md](CONTRIBUTING.md) first. This project
follows the [Contributor Covenant](CODE_OF_CONDUCT.md).

## License

[MIT](LICENSE) © 2026 Md. Bayezid Hasan Siam
