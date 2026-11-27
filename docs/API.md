# API reference

Base URL: `http://localhost:8000/api` (override with `APP_URL`).

All requests and responses are JSON unless noted. Authentication uses Sanctum personal access
tokens:

```http
Authorization: Bearer <token>
Accept: application/json
```

## Authentication

```bash
# register
curl -X POST http://localhost:8000/api/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Ada","email":"ada@example.com","password":"secret!1234","password_confirmation":"secret!1234"}'

# log in
curl -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@uispark.test","password":"password"}'
```

Both return `{ "user": {...}, "token": "1|..." }`. Send the token as a bearer header for any
protected route.

## Rate limits

| Scope | Limit |
|---|---|
| `POST /auth/register`, `POST /auth/login`, `PUT /auth/password` | 10 per minute |
| `POST /auth/avatar` | 20 per minute |
| `POST /designs` (upload) | 20 per hour |
| `POST /designs/{slug}/comments` | 30 per minute |
| `POST /generations`, `POST /generations/{id}/retry` | 10 per minute |
| `POST /generator/options` | 30 per minute |

Exceeding a limit returns `429` with a `Retry-After` header.

## Errors

Validation failures return `422` with Laravel's shape:

```json
{
  "message": "The prompt field is required.",
  "errors": { "prompt": ["The prompt field is required."] }
}
```

Other common codes: `401` unauthenticated, `403` forbidden (role or ownership), `404` not found,
`409` conflict, `429` throttled, `503` local model server offline, `500` unexpected server error.

## Endpoints

### Auth and profile

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/auth/register` | – | Create an account, returns `{ user, token }` |
| POST | `/auth/login` | – | Log in, returns `{ user, token }` |
| GET | `/auth/me` | ✓ | Current user |
| PUT | `/auth/profile` | ✓ | Update profile fields (`name`, `username`, `headline`, `bio`, `location`, `website`, `skills`, `links`, `is_profile_public`) |
| PUT | `/auth/password` | ✓ | Change password (`current_password`, `password`, `password_confirmation`) |
| POST | `/auth/avatar` | ✓ | Upload and square-crop avatar (`avatar` image) |
| DELETE | `/auth/avatar` | ✓ | Remove avatar |
| POST | `/auth/logout` | ✓ | Revoke the current token |
| GET | `/users/{username}` | – | Public profile and stats (owner/admin also see private profiles) |
| GET | `/users/{username}/designs` | – | Approved designs by user |
| GET | `/users/{username}/projects` | – | Publicly shared generations by user |

### Catalogue

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/categories` | – | Domains with design counts |
| GET | `/meta` | – | Filter options: platforms, screen types, colours, popular tags |
| GET | `/designs?q&category&platform&screen_type&color&tag&sort&page` | – | Approved designs (`sort`: `latest`, `trending`, `popular`, `most_viewed`) |
| GET | `/designs/{slug}` | – | Design detail |
| GET | `/designs/{slug}/similar` | – | More like this |

### Uploads and ownership

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/designs` | ✓ | Upload a screenshot (multipart: `image`, `title`, `category_id`, `platform`, `screen_type`, …); pending unless the uploader is an admin |
| PUT | `/designs/{slug}` | owner/admin | Edit; a rejected design is resubmitted for review |
| DELETE | `/designs/{slug}` | owner/admin | Delete |
| GET | `/my/designs?status` | ✓ | Own uploads by status |

### Interactions

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/designs/{slug}/like` | ✓ | Toggle like |
| POST | `/designs/{slug}/bookmark` | ✓ | Toggle bookmark |
| PUT | `/designs/{slug}/bookmark` | ✓ | Move a bookmark to a collection |
| GET | `/my/bookmarks?collection_id` | ✓ | Saved designs |
| GET | `/my/collections` | ✓ | Collections with counts |
| POST | `/my/collections` | ✓ | Create a collection (`name`) |
| DELETE | `/my/collections/{id}` | ✓ | Delete a collection |
| GET | `/designs/{slug}/comments` | – | Threaded comments |
| POST | `/designs/{slug}/comments` | ✓ | Add a comment (`body`, optional `parent_id`) |
| DELETE | `/comments/{id}` | owner/admin | Delete a comment |

### Generator

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/generator/options` | – | Languages, frameworks, screens, domains, platforms, styles, limits, and local Qwen health |
| POST | `/generator/options` | ✓ | Save a custom `language`, `framework`, `screen`, or `domain` |
| DELETE | `/generator/options/{option}` | owner | Remove a custom option |
| POST | `/generations` | ✓ | Queue a project generation (`202` accepted, or `503` if Qwen is offline) |
| POST | `/generations/{id}/retry` | owner | Re-queue a non-active generation |
| GET | `/my/generations` | ✓ | Own generation history |
| GET | `/generations/public` | – | Publicly shared generations |
| GET | `/generations/{id}` | ✓ / public | Generation detail, progress, previews, generated files, and design brief |
| GET | `/generations/{id}/raw?screen&download` | ✓ / public | Raw HTML document for a screen (`Content-Security-Policy: sandbox allow-scripts`) |
| PATCH | `/generations/{id}` | owner | `{ "is_public": true|false }` (only when `status === "done"`) |
| DELETE | `/generations/{id}` | owner/admin | Delete |

`POST /generations` body:

```json
{
  "prompt": "laundry service website for students with pickup scheduling",
  "language": "javascript",
  "framework": "react",
  "platform": "web",
  "style": "modern",
  "accent": "#2563eb",
  "screens": ["landing", "dashboard"],
  "domains": ["marketplace"],
  "use_research": true
}
```

Response (`202`):

```json
{
  "data": {
    "id": 42,
    "status": "pending",
    "provider": "local",
    "progress": {
      "message": "Waiting for the generator worker",
      "done": 0,
      "total": 1,
      "log": []
    }
  }
}
```

### Admin

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/admin/stats` | admin | Counts for the dashboard |
| GET | `/admin/designs?status` | admin | Moderation queue |
| PATCH | `/admin/designs/{slug}/approve` | admin | Approve an upload |
| PATCH | `/admin/designs/{slug}/reject` | admin | Reject; `reason` is required |
| GET | `/admin/users?q` | admin | Search users |
| PATCH | `/admin/users/{id}` | admin | Update `role` and/or `is_banned` |
| POST/PUT/DELETE | `/admin/categories[/{id}]` | admin | Manage domains |

## Notes

- A banned user's tokens are revoked and every authenticated request returns `403`.
- Design detail responses include cached like, bookmark, comment and view counters.
- Palette swatches (`palette[]` with `hex` and `coverage`) are extracted on upload, not on read.
