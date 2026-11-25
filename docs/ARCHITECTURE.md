# Architecture

UISpark is a decoupled Laravel API and a React single-page app. The API owns all state; the SPA
talks to it with a bearer token and never renders untrusted HTML in its own document.

```
Browser ──▶ React SPA (Vite) ──▶ Laravel API (/api) ──▶ MySQL
                                        │
                                        ├─▶ storage/app/public/designs (uploads)
                                        ├─▶ Exa / keyless search (research)
                                        └─▶ local model or Anthropic (design provider)
```

## Backend layers

| Path | Responsibility |
|---|---|
| `routes/api.php` | Every endpoint, grouped by middleware: public, `auth:sanctum` + `not_banned`, admin |
| `app/Http/Controllers/Api` | Request validation, authorization, orchestration; one controller per resource |
| `app/Http/Resources` | Stable JSON shapes (`DesignResource`, `GenerationResource`, …) |
| `app/Models` | Eloquent models with counters, scopes and relationships |
| `app/Services` | Domain logic that is too large for a controller |
| `app/Http/Middleware` | `EnsureUserIsNotBanned`, `EnsureUserIsAdmin` |

Controllers keep to a short cycle: validate → authorize → delegate to a model or service → return a
resource. Nothing in a controller knows about another resource's internals.

## Data model

| Table | Purpose |
|---|---|
| `users` | `role` (`user`/`admin`), `is_banned`, `bio` |
| `categories` | Domains/topics, with emoji icon and sort order |
| `designs` | Uploaded screens: platform, screen type, image size, `palette` (JSON), `color_families`, `status` (`pending`/`approved`/`rejected`), cached like/bookmark/comment/view counts |
| `tags`, `design_tag` | Free-form tags, created on the fly |
| `likes` | One row per user per design |
| `collections`, `bookmarks` | Saved designs, optionally grouped into named collections |
| `comments` | One level of replies via `parent_id` |
| `ui_generations` | Generated mockups: prompt, options, HTML, provider, `is_public`, `meta` (JSON design brief) |
| `personal_access_tokens` | Sanctum tokens |

Counters are denormalised on the parent row and updated in the same transaction as the interaction,
so gallery listings stay a single query.

## Services

### `PaletteExtractor`

Samples the uploaded image with GD, buckets colours, merges perceptually close ones, and returns up
to six colours with coverage percentages. It also names colour families (`blue`, `white`, …) so the
gallery can filter by colour without storing per-image indexes.

### Generator

`app/Services/Generator` is the mockup pipeline, and each class has one job:

| Class | Job |
|---|---|
| `InspirationScout` | Builds a search query from the brief, queries Exa (keyed) then a keyless endpoint, caches results for six hours, falls back to a curated reference board, and reports `source` as `live`, `cache`, `curated` or `offline` |
| `DesignDirection` | Derives a seeded art direction — design language, display/body type, radius, accent, and a layout recipe (`hero`, `proof`, `features`, `showcase`, `cta`, ordering) |
| `DomainProfiles` | Recognises the product's domain from the prompt (plumbing, pharmacy, farming, fintech…) and supplies vocabulary |
| `TemplateEngine` | Offline composer: turns a direction into a complete HTML page from composable section variants |
| `UiGenerator` | Orchestrates the above, selects the provider, normalises model output, and builds the `meta.design` payload |

Provider order is deliberate:

1. `LOCAL_AI_BASE_URL` — any OpenAI-compatible `/v1/chat/completions` server.
2. `ANTHROPIC_API_KEY` — Claude Messages API.
3. `TemplateEngine` — always available, no network.

A failing model never fails the request: the template engine takes over and the response carries a
`notice`. Model output is normalised by `extractHtml`, which strips markdown fences, keeps only the
document, and closes `</body></html>` when a small model runs out of tokens mid-page.

### Determinism and variety

`DesignDirection` seeds its random choices from the prompt plus a per-request seed, and `UiGenerator`
gives each run a new direction. That is what makes two identical prompts yield different pages while
a single direction stays internally consistent (one accent, one type pairing, one rhythm).

## Frontend

```
src/
├── api/client.js        fetch wrapper: base URL, bearer token, error normalisation
├── context/             AuthContext (session), ToastContext (notifications)
├── hooks.js             data-fetching hooks shared by pages
├── components/          Layout, DesignCard, Guards, Icons, HeroScene
└── pages/               Auth, Explore, DesignDetail, Upload, Generate, Showcase, Me, admin/*
```

The SPA keeps no secrets: tokens live in `localStorage`, are attached by `api/client.js`, and are
cleared on `401`. Route guards read `AuthContext` rather than calling the API on every navigation.

CSS is hand-written in `src/styles.css` with custom properties for tokens (colour, radius, spacing,
display type). There is no UI framework, so mockup styles and app styles never collide.

## Preview isolation

Generated HTML is untrusted. It is rendered in `<iframe sandbox="allow-scripts">` — deliberately
without `allow-same-origin` — so scripts inside a mockup run in an opaque origin and cannot read the
SPA's token, cookies or DOM. `GET /generations/{id}/raw` additionally responds with
`Content-Security-Policy: sandbox`, so opening that URL directly is equally contained.

## Extending

| Goal | Where |
|---|---|
| New screen type or platform | `GenerationController::options()` plus the relevant `TemplateEngine` composition branch |
| New design language | `DesignDirection`: add to the language table and to the `style` mapping |
| New provider | `UiGenerator`: add a `via…` method, return HTML, let the existing fallback handle errors |
| New domain vocabulary | `DomainProfiles`: add keywords and copy tokens |
| New gallery filter | `DesignController::index()` plus `CategoryController::meta()` |

## Known limitations

- CPU-only inference on a small local model takes minutes per page and produces weaker layout
  quality than the template engine; the token budget is intentionally low to fit the timeout.
- Keyless research endpoints can be blocked by bot protection; the generator then reports
  `source: curated`, which is expected rather than an error.
- Uploads are stored on the local disk (`FILESYSTEM_DISK=public`); a multi-node deployment needs
  object storage and a shared cache.
- Generation happens inside the HTTP request, so a slow provider holds a PHP worker for its duration;
  moving it to a queued job is the natural next step.
