# Architecture

UISpark is a decoupled API + SPA: a Laravel 12 backend under `backend/` and a React 18 + Vite 7
frontend under `frontend/`.

```
Browser ──▶ React SPA (Vite) ──▶ Laravel API (/api) ──▶ MySQL
                                        │
                                        ├──▶ Queue Worker (GenerateUiProject)
                                        │      └──▶ Local Qwen (LM Studio / Ollama)
                                        └──▶ Keyless web search (DuckDuckGo HTML)
```

## Backend

### Data model

| Table | Purpose |
|---|---|
| `users` | Accounts, roles (`user`/`admin`), suspension flag, profile fields (`username`, `headline`, `bio`, `location`, `website`, `skills`, `links`, `avatar_path`, `is_profile_public`) |
| `categories` | Product domains (`fintech`, `saas`, `healthcare`, …) |
| `designs` | Uploaded screenshots, moderation `status` (`pending`/`approved`/`rejected`), extracted `palette` JSON and `color_families` |
| `tags` / `design_tag` | Many-to-many tags created on upload |
| `likes`, `bookmarks`, `collections`, `comments` | User interactions; comments support one level of replies via `parent_id` |
| `generator_options` | Seeded and user-created `language`, `framework`, `screen`, and `domain` options |
| `ui_generations` | Queued and completed project generations: prompt, options, `status`, `progress`, `previews`, `files`, `html`, `is_public`, `meta` |
| `personal_access_tokens` | Sanctum tokens |

Counters are denormalised on the parent row and updated in the same transaction as the interaction,
so gallery listings stay a single query.

### Services

#### `PaletteExtractor`

Samples the uploaded image with GD, buckets colours, merges perceptually close ones, and returns up
to six colours with coverage percentages. It also names colour families (`blue`, `white`, …) so the
gallery can filter by colour without storing per-image indexes.

#### Generator (`app/Services/Generator`)

Project generation runs asynchronously via the `GenerateUiProject` queued job:

| Class | Job |
|---|---|
| `QwenClient` | Checks health and talks to the local OpenAI-compatible `/v1/chat/completions` server (loopback/private addresses only) |
| `InspirationScout` | Derives a search query from the prompt and screens, fetches keyless DuckDuckGo HTML results, studies top pages, caches for 6 hours, and falls back to a curated reference board |
| `DesignDirection` | Derives a seeded art direction — design language, display/body type, radius, accent, and layout recipe |
| `DomainProfiles` | Recognises product domains and supplies domain vocabulary |
| `FrameworkCatalog` | Defines file layouts, dependencies, and generation instructions per framework |
| `ProjectGenerator` | Orchestrates research → plan → HTML preview per screen → framework code per screen → scaffold, updating progress in `ui_generations` |
| `ProjectScaffold` | Generates entry points, router, config files, and README for the target framework |
| `TemplateEngine` | Defines supported visual style presets (`TemplateEngine::STYLES`) and section composition helpers |
| `GenerationCancelled` | Thrown when a generation is deleted or cancelled mid-run |

## Frontend

```
src/
├── api/client.js        fetch wrapper: base URL, bearer token, error normalisation
├── context/             AuthContext (session), ToastContext (notifications)
├── hooks.js             data-fetching hooks shared by pages
├── zip.js               client-side ZIP builder for downloading generated projects
├── components/          Avatar, DesignCard, Guards, HeroScene, Icons, Layout
└── pages/               Auth, Comments, DesignDetail, Explore, Generate, Me, Profile, Showcase, Upload, admin/*
```

The SPA keeps no secrets: tokens live in `localStorage`, are attached by `api/client.js`, and are
cleared on `401`. Route guards read `AuthContext` rather than calling the API on every navigation.

CSS is hand-written in `src/styles.css`, `src/generator.css`, and `src/profile.css` using custom
properties for tokens (colour, radius, spacing, display type). There is no UI framework, so mockup
styles and app styles never collide.

## Preview isolation

Generated HTML is untrusted. It is rendered in `<iframe sandbox="allow-scripts">` — deliberately
without `allow-same-origin` — so scripts inside a mockup run in an opaque origin and cannot read the
SPA's token, cookies or DOM. `GET /generations/{id}/raw` additionally responds with
`Content-Security-Policy: sandbox allow-scripts`, so opening that URL directly is equally contained.

## Extending

| Goal | Where |
|---|---|
| New framework or language | `FrameworkCatalog` and `GeneratorOptionSeeder` (or users can add custom ones via `POST /generator/options`) |
| New design language | `DesignDirection` and `TemplateEngine::STYLES` |
| New domain vocabulary | `DomainProfiles` |
| New gallery filter | `DesignController::index()` plus `CategoryController::meta()` |

## Known limitations

- CPU-only inference on a small local model takes several minutes per screen and step; run
  `php artisan queue:work --timeout=0` so jobs do not time out.
- Keyless research endpoints can be blocked by bot protection; the generator then reports
  `source: curated`, which is expected rather than an error.
- Uploads are stored on the local disk (`FILESYSTEM_DISK=public`); a multi-node deployment needs
  object storage and a shared cache.
