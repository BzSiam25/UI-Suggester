# UISpark

A platform for getting UI ideas for any website or mobile app, in any domain.

- **Explore** a gallery of real UI screens, filtered by domain (e-commerce, health, agriculture…), platform (web / mobile / tablet), screen type (login, dashboard, checkout…) and dominant color.
- **Generate** a mockup for any product idea: describe it, pick platform, screen, style and accent color, and get a working HTML page you can preview, open, download and share.
- **Community**: users upload screenshots; an admin approves them before they go public. Likes, bookmarks with collections, and threaded comments.
- **Color palettes** are extracted automatically from every upload and shown as swatches (click to copy hex).
- **Admin dashboard**: stats, review queue with rejection reasons, user management (promote / suspend), domain management.

Stack: **React 18 + Vite** (frontend), **Laravel 12 + Sanctum** (API), **MySQL**.

```
uispark/
├── backend/    Laravel API  (http://localhost:8000/api)
└── frontend/   React app    (http://localhost:5173)
```

---

## Requirements

- PHP 8.2+ with the `gd`, `pdo_mysql`, `mbstring`, `fileinfo` extensions
- Composer 2
- MySQL 8 (or MariaDB 10.6+)
- Node.js 18+

## 1. Backend setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Create the database, then set your credentials in `backend/.env`:

```sql
CREATE DATABASE uispark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```env
DB_DATABASE=uispark
DB_USERNAME=root
DB_PASSWORD=your_password
```

Then:

```bash
php artisan migrate --seed     # tables + 16 domains + demo accounts + 22 demo designs
php artisan storage:link       # makes uploaded images publicly reachable
php artisan serve              # http://localhost:8000
```

### Demo accounts (created by the seeder)

| Role  | Email               | Password |
|-------|---------------------|----------|
| Admin | admin@uispark.test  | password |
| User  | demo@uispark.test   | password |

Change these passwords (or delete the accounts) before deploying anywhere public.

### AI generation (optional)

The generator works out of the box with a built-in **template engine**: it detects the product's domain from the description (laundry, pharmacy, farming, fintech…) and produces a themed, domain-specific page in 6 styles for 18 screen types.

To have Claude design each mockup instead, add an Anthropic API key to `backend/.env`:

```env
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-sonnet-5-5
```

With a key set, the generator shows a "Design with AI" toggle. If an AI call fails, it falls back to the template engine automatically and tells the user.

## 2. Frontend setup

```bash
cd frontend
npm install
cp .env.example .env           # VITE_API_URL=http://localhost:8000/api
npm run dev                    # http://localhost:5173
```

`FRONTEND_URL` in `backend/.env` must match the frontend's origin (for CORS). For production, run `npm run build` and serve `frontend/dist/` from any static host, routing all paths to `index.html`.

## 3. Tests

```bash
cd backend
php artisan test
```

Feature tests cover registration/login, the upload → moderation → gallery flow, filters, likes, bookmarks with collections, threaded comments and their counters, generator fallback and sharing, and banning.

---

## How it works

### Data model

| Table | Purpose |
|---|---|
| `users` | `role` (user/admin), `is_banned`, `bio` |
| `categories` | Domains / topics, with emoji icon and sort order |
| `designs` | Uploaded screens: platform, screen type, image size, `palette` (JSON), `color_families`, `status` (pending/approved/rejected), cached like/bookmark/comment/view counts |
| `tags`, `design_tag` | Free-form tags, created on the fly |
| `likes` | One per user per design |
| `collections`, `bookmarks` | Saved designs, optionally grouped into named collections |
| `comments` | One level of replies via `parent_id` |
| `ui_generations` | Generated mockups: prompt, options, HTML, provider, `is_public` |
| `personal_access_tokens` | Sanctum API tokens |

### Key backend pieces

- `app/Services/PaletteExtractor.php` — samples the image with GD, buckets colors, merges perceptually close ones, and returns up to 6 colors with coverage %. Also names color families ("blue", "white"…) so the gallery can filter by color.
- `app/Services/Generator/UiGenerator.php` — chooses Claude API or template engine, extracts clean HTML from the model response.
- `app/Services/Generator/TemplateEngine.php` + `DomainProfiles.php` — offline mockup generator: 12 domain profiles × 6 styles × web/mobile layouts.
- `app/Http/Controllers/Api/*` — REST controllers; `routes/api.php` lists every endpoint.

### Security notes

- Generated HTML is rendered in an `<iframe sandbox="allow-scripts">` **without** `allow-same-origin`, so a mockup can never read the user's token or call the API. The raw endpoint also sends `Content-Security-Policy: sandbox`.
- Uploads are validated as real images (JPG/PNG/WebP, ≤ 8 MB, ≥ 200 px) and stored under random names.
- Rate limits: login/register 10/min, uploads 20/hour, comments 30/min, generations 10/min.
- Suspended users lose all tokens and are blocked by middleware.

## API reference (all under `/api`)

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/auth/register`, `/auth/login` | – | Returns `{ user, token }` |
| GET | `/auth/me` · PUT `/auth/profile` · POST `/auth/logout` | ✓ | Current user |
| GET | `/categories`, `/meta` | – | Domains with counts; filter options and popular tags |
| GET | `/designs?q&category&platform&screen_type&color&tag&sort&page` | – | Gallery (sort: latest, trending, popular, most_viewed) |
| GET | `/designs/{slug}`, `/designs/{slug}/similar` | – | Detail, "more like this" |
| POST | `/designs` (multipart) | ✓ | Upload; pending unless uploader is admin |
| PUT/DELETE | `/designs/{slug}` | owner/admin | Edit (resubmits rejected) / delete |
| GET | `/my/designs?status` | ✓ | Own uploads |
| POST | `/designs/{slug}/like`, `/designs/{slug}/bookmark` | ✓ | Toggles |
| PUT | `/designs/{slug}/bookmark` | ✓ | Move to a collection |
| GET | `/my/bookmarks?collection_id`, `/my/collections` · POST `/my/collections` · DELETE `/my/collections/{id}` | ✓ | Bookmarks |
| GET | `/designs/{slug}/comments` · POST same | –/✓ | Threaded comments |
| DELETE | `/comments/{id}` | owner/admin | |
| GET | `/generator/options` | – | Platforms, screens, styles, whether AI is on |
| POST | `/generations` | ✓ | Generate a mockup |
| GET | `/my/generations`, `/generations/public`, `/generations/{id}`, `/generations/{id}/raw` | ✓/– | History, showcase, detail, raw HTML |
| PATCH/DELETE | `/generations/{id}` | owner | Share publicly / delete |
| GET | `/admin/stats`, `/admin/designs?status`, `/admin/users?q` | admin | Dashboard, queue, users |
| PATCH | `/admin/designs/{slug}/approve`, `/admin/designs/{slug}/reject` | admin | Moderation (reject needs `reason`) |
| PATCH | `/admin/users/{id}` | admin | `role`, `is_banned` |
| POST/PUT/DELETE | `/admin/categories[/{id}]` | admin | Manage domains |

## Ideas for next steps

- Email notifications when an upload is approved or rejected
- Full-text search (MySQL `FULLTEXT` or Meilisearch via Laravel Scout)
- "Upload from URL": take a screenshot of a live site with a headless browser
- Export a generated mockup straight into the gallery as a design
