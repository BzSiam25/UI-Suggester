import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Pager, Wall } from '../components/DesignCard'
import { Empty } from '../components/Guards'
import HeroScene from '../components/HeroScene'
import { Search } from '../components/Icons'
import { label, useApi, useMeta } from '../hooks'

const COLOR_HEX = {
  red: '#e5484d', orange: '#f76b15', yellow: '#ffc53d', green: '#30a46c', teal: '#12a594', blue: '#3e63dd',
  purple: '#8e4ec6', pink: '#d6409f', brown: '#a07553', black: '#1c1c1f', white: '#ffffff', gray: '#8b8d98',
}

export default function Explore() {
  const [params, setParams] = useSearchParams()
  const meta = useMeta()
  const [q, setQ] = useState(params.get('q') || '')

  const filters = Object.fromEntries(['q', 'category', 'platform', 'screen_type', 'color', 'tag', 'sort', 'page'].map((k) => [k, params.get(k) || '']))
  const key = params.toString()
  const { data, loading, error } = useApi('/designs', filters, [key])

  // Debounce the search box into the URL
  useEffect(() => {
    if (q === (params.get('q') || '')) return
    const t = setTimeout(() => update({ q }), 350)
    return () => clearTimeout(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q])

  function update(patch) {
    const next = new URLSearchParams(params)
    Object.entries(patch).forEach(([k, v]) => (v ? next.set(k, v) : next.delete(k)))
    if (!('page' in patch)) next.delete('page')
    setParams(next, { replace: true })
  }
  const toggle = (k, v) => update({ [k]: filters[k] === v ? '' : v })
  const anyFilter = ['q', 'category', 'platform', 'screen_type', 'color', 'tag'].some((k) => filters[k])
  const total = data?.meta?.total

  return (
    <>
      <section className="hero">
        <div className="wrap hero-grid">
          <div className="hero-copy">
            <span className="eyebrow">UI inspiration library</span>
            <h1>See how others designed it before you start</h1>
            <p>Real screens from websites and apps, grouped by domain, platform and screen type. Can't find yours? Generate a mockup for any idea.</p>
            <label className="search">
              <span className="sr-only">Search designs</span>
              <Search />
              <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Try “checkout”, “pharmacy” or “dark dashboard”" />
            </label>
          </div>
          <div className="hero-media" aria-hidden="true">
            <div className="hero-scene"><HeroScene /></div>
          </div>
        </div>
      </section>

      <div className="wrap">
        <div className="domains" role="group" aria-label="Domains">
          <button className="domain" aria-pressed={!filters.category} onClick={() => update({ category: '' })}>All domains</button>
          {meta?.categories.map((c) => (
            <button key={c.id} className="domain" aria-pressed={filters.category === c.slug} onClick={() => toggle('category', c.slug)}>
              <span aria-hidden="true">{c.icon}</span>{c.name}<span className="count">{c.designs_count}</span>
            </button>
          ))}
        </div>

        <div className="filters">
        <div className="segmented" role="group" aria-label="Platform">
          <button aria-pressed={!filters.platform} onClick={() => update({ platform: '' })}>All</button>
          {(meta?.platforms || []).map((p) => <button key={p} aria-pressed={filters.platform === p} onClick={() => toggle('platform', p)}>{label(p)}</button>)}
        </div>
        <select className="select" aria-label="Screen type" value={filters.screen_type} onChange={(e) => update({ screen_type: e.target.value })}>
          <option value="">Any screen</option>
          {(meta?.screen_types || []).map((s) => <option key={s} value={s}>{label(s)}</option>)}
        </select>
        <div className="colors" role="group" aria-label="Color">
          {(meta?.colors || []).map((c) => (
            <button key={c} className="color-dot" title={label(c)} aria-label={`${label(c)} designs`} aria-pressed={filters.color === c}
              style={{ background: COLOR_HEX[c] }} onClick={() => toggle('color', c)} />
          ))}
        </div>
        <span className="spacer" />
        {total !== undefined && <span className="result-count">{total} {total === 1 ? 'design' : 'designs'}</span>}
        <select className="select" aria-label="Sort" value={filters.sort || 'latest'} onChange={(e) => update({ sort: e.target.value === 'latest' ? '' : e.target.value })}>
          <option value="latest">Newest</option>
          <option value="trending">Trending this week</option>
          <option value="popular">Most liked</option>
          <option value="most_viewed">Most viewed</option>
        </select>
        {anyFilter && <button className="btn quiet small" onClick={() => { setQ(''); setParams({}, { replace: true }) }}>Clear filters</button>}
      </div>

      {filters.tag && <p className="muted" style={{ marginBottom: 16 }}>Tagged “{filters.tag}”</p>}

      {error ? (
        <Empty title="Couldn't load designs">{error.message}. Check that the API is running at the address in your .env file.</Empty>
      ) : data && data.data.length === 0 ? (
        <Empty title="No designs match these filters" action={<Link className="btn" to={`/generate?prompt=${encodeURIComponent(filters.q || '')}`}>Generate a mockup instead</Link>}>
          Try a broader search, or have UISpark draw a first idea for you.
        </Empty>
      ) : (
        <>
          <Wall designs={data?.data || []} loading={loading} />
          <Pager meta={data?.meta} onPage={(p) => { update({ page: String(p) }); window.scrollTo({ top: 0 }) }} />
        </>
      )}
      </div>
    </>
  )
}
