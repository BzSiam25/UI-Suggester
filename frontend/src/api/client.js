const BASE = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '')
const TOKEN_KEY = 'uispark_token'

export const tokenStore = {
  get: () => { try { return localStorage.getItem(TOKEN_KEY) } catch { return null } },
  set: (t) => { try { t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY) } catch { /* private mode */ } },
}

export class ApiError extends Error {
  constructor(status, body) {
    const first = body?.errors && Object.values(body.errors)[0]?.[0]
    super(first || body?.message || `Request failed (${status})`)
    this.status = status
    this.errors = body?.errors || {}
  }
}

/** Small fetch wrapper: JSON in/out, bearer token, FormData passthrough. */
export async function api(path, { method = 'GET', body, params, signal } = {}) {
  const url = new URL(BASE + path, window.location.origin)
  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v)
    })
  }
  const headers = { Accept: 'application/json' }
  const token = tokenStore.get()
  if (token) headers.Authorization = `Bearer ${token}`

  let payload = body
  if (body && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }

  const res = await fetch(url, { method, headers, body: payload, signal })
  const data = res.status === 204 ? null : await res.json().catch(() => null)
  if (!res.ok) {
    if (res.status === 401) tokenStore.set(null)
    throw new ApiError(res.status, data)
  }
  return data
}

export const API_BASE = BASE
