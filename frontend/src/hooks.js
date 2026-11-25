import { useEffect, useState } from 'react'
import { api } from './api/client'

/** Fetch once per `deps` change; returns { data, error, loading, reload, setData }. */
export function useApi(path, params, deps = []) {
  const [state, setState] = useState({ data: null, error: null, loading: true })
  const [tick, setTick] = useState(0)

  useEffect(() => {
    if (!path) return
    const ctrl = new AbortController()
    setState((s) => ({ ...s, loading: true, error: null }))
    api(path, { params, signal: ctrl.signal })
      .then((data) => setState({ data, error: null, loading: false }))
      .catch((error) => { if (error.name !== 'AbortError') setState({ data: null, error, loading: false }) })
    return () => ctrl.abort()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [path, tick, ...deps])

  return { ...state, reload: () => setTick((t) => t + 1), setData: (fn) => setState((s) => ({ ...s, data: fn(s.data) })) }
}

let metaCache = null
export function useMeta() {
  const [meta, setMeta] = useState(metaCache)
  useEffect(() => {
    if (metaCache) return
    Promise.all([api('/categories'), api('/meta')]).then(([cats, m]) => {
      metaCache = { categories: cats.data, ...m }
      setMeta(metaCache)
    }).catch(() => setMeta({ categories: [], platforms: ['web', 'mobile', 'tablet'], screen_types: [], colors: [], popular_tags: [] }))
  }, [])
  return meta
}
export const invalidateMeta = () => { metaCache = null }

/**
 * Scroll reveal: marks matching elements so they fade/slide up as they enter the
 * viewport. Re-scans on DOM changes so lazily loaded cards animate too.
 */
export function useScrollReveal(selectors) {
  useEffect(() => {
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    const supported = typeof IntersectionObserver !== 'undefined'
    const seen = new WeakSet()
    const io = supported && !reduce
      ? new IntersectionObserver((entries) => {
          entries.forEach((e) => {
            if (!e.isIntersecting) return
            e.target.classList.add('is-in')
            io.unobserve(e.target)
          })
        }, { rootMargin: '0px 0px 10% 0px', threshold: 0.04 })
      : null
    const sel = selectors.join(',')
    let queued = false

    const scan = () => {
      queued = false
      document.querySelectorAll(sel).forEach((el) => {
        if (seen.has(el)) return
        seen.add(el)
        if (!io) { el.classList.add('reveal', 'is-in'); return }
        const siblings = el.parentElement ? [...el.parentElement.children] : []
        const index = Math.min(Math.max(siblings.indexOf(el), 0), 5)
        el.style.transitionDelay = `${index * 55}ms`
        el.classList.add('reveal')
        // Anything already on screen shows right away; only what is below
        // the fold waits for the scroll and animates in.
        if (el.getBoundingClientRect().top < window.innerHeight) {
          el.classList.add('is-in')
          return
        }
        io.observe(el)
      })
    }
    // setTimeout (not requestAnimationFrame) so the first pass still runs when
    // the tab is in the background and rAF is paused.
    const schedule = () => { if (!queued) { queued = true; setTimeout(scan, 16) } }

    scan()
    const mo = new MutationObserver(schedule)
    mo.observe(document.body, { childList: true, subtree: true })
    return () => { mo.disconnect(); io?.disconnect() }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])
}

export const label = (s = '') => s.replace(/-/g, ' ').replace(/^\w/, (c) => c.toUpperCase())

export function timeAgo(iso) {
  const s = (Date.now() - new Date(iso).getTime()) / 1000
  if (s < 60) return 'just now'
  const units = [[31536000, 'year'], [2592000, 'month'], [604800, 'week'], [86400, 'day'], [3600, 'hour'], [60, 'minute']]
  for (const [secs, name] of units) {
    const n = Math.floor(s / secs)
    if (n >= 1) return `${n} ${name}${n > 1 ? 's' : ''} ago`
  }
  return 'just now'
}
