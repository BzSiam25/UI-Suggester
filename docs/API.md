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
  -d '{"name":"Ada","email":"ada@example.com","password":"secret1234","password_confirmation":"secret1234"}'

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
| `POST /auth/register`, `POST /auth/login` | 10 per minute |
| `POST /designs` (upload) | 20 per hour |
| `POST /designs/{slug}/comments` | 30 per minute |
| `POST /generations` | 10 per minute |

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
`429` throttled, `500` unexpected server error.

## Endpoints

### Auth and profile

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/auth/register` | – | Create an account, returns `{ user, token }` |
| POST | `/auth/login` | – | Log in, returns `{ user, token }` |
| GET | `/auth/me` | ✓ | Current user |
| PUT | `/auth/profile` | ✓ | Update name, bio or password |
| POST | `/auth/logout` | ✓ | Revoke the current token |

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
| GET | `/generator/options` | – | Platforms, screen types, styles, and whether AI is enabled |
| POST | `/generations` | ✓ | Generate a mockup |
| GET | `/my/generations` | ✓ | Own generation history |
| GET | `/generations/public` | – | Publicly shared mockups |
| GET | `/generations/{id}` | ✓ | Generation detail, including the design brief |
| GET | `/generations/{id}/raw` | – | Raw HTML document (`Content-Security-Policy: sandbox`) |
| PATCH | `/generations/{id}` | owner | `{ "is_public": true|false }` |
| DELETE | `/generations/{id}` | owner | Delete |

`POST /generations` body:

```json
{
  "prompt": "laundry service website for students",
  "platform": "web",
  "screen_type": "landing",
  "style": "modern",
  "accent": "#2563eb",
  "category_id": 3,
  "use_ai": true
}
```

Response (`201`):

```json
{
  "data": {
    "id": 42,
    "provider": "local",
    "notice": null,
    "html": "<!DOCTYPE html>…",
    "design": {
      "direction": "Editorial",
      "language": "editorial",
      "summary": "…",
      "recipe": { "hero": "…", "proof": "…", "features": "…", "showcase": "…", "closing": "…", "order": "work first" },
      "research": { "source": "live", "query": "laundry service website ui design inspiration", "references": [] }
    }
  }
}
```

`provider` is `local`, `anthropic` or `template`. When a configured model fails, the response is
still `201` with `provider: "template"` and a human-readable `notice` explaining the fallback.

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
