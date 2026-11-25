import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../api/client'
import { Download, External, Globe, Spark, Trash } from '../components/Icons'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { label, timeAgo, useApi, useMeta } from '../hooks'

const STYLE_PREVIEW = {
  modern: 'linear-gradient(135deg,#eef0ff,#5b5bd6)',
  minimal: 'linear-gradient(135deg,#ffffff 55%,#111 55%)',
  dark: 'linear-gradient(135deg,#0d0f14 60%,#7c9cff)',
  playful: 'linear-gradient(135deg,#ffe3d6,#ff6b5b)',
  corporate: 'linear-gradient(135deg,#e8f0fa,#0b5cab)',
  glass: 'linear-gradient(135deg,#3a1c71,#d76d77,#ffaf7b)',
}
const PROVIDER_LABEL = { local: 'Qwen', anthropic: 'Claude', template: 'Template' }
const RESEARCH_LABEL = { live: 'Live web research', cache: 'Web research · cached', curated: 'Reference gallery', offline: 'Reference gallery' }

/** Shows the art direction a mockup was built in, plus the pages that inspired it. */
export function DesignNote({ design, compact }) {
  if (!design) return null
  const references = design.research?.references || []
  const recipe = design.recipe

  return (
    <div className="notice info" style={{ margin: compact ? 0 : '12px 14px 0', display: 'flex', flexDirection: 'column', gap: 7 }}>
      <div className="row" style={{ gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
        <span className="pill">{design.direction}</span>
        {recipe && <span>{recipe.hero} hero · {recipe.features} benefits · {recipe.showcase} showcase · {recipe.order}</span>}
        <span className="pill">{RESEARCH_LABEL[design.research?.source] || 'References'}</span>
      </div>
      {references.length > 0 && (
        <div className="row" style={{ gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
          <span>Inspired by</span>
          {references.slice(0, 4).map((r) => (
            <a key={r.url} className="pill" href={r.url} target="_blank" rel="noreferrer noopener" style={{ textDecoration: 'none', maxWidth: 260, overflow: 'hidden', textOverflow: 'ellipsis' }} title={r.title}>
              {r.source || r.title}
            </a>
          ))}
        </div>
      )}
    </div>
  )
}

const ACCENTS = ['#2b4bee', '#0d9488', '#16a34a', '#ea580c', '#e11d48', '#7c3aed', '#111111']
const IDEAS = ['Pharmacy delivery app', 'Freelancer job portal', 'Smart farm monitor', 'Online tuition platform', 'Laundry pickup service', 'Mosque prayer-time app']

const NATIVE_WIDTH = { web: 1280, tablet: 820, mobile: 390 }

/** Renders the mockup at its native viewport width, scaled down to fit the available space. */
export function Preview({ generation, height }) {
  const platform = generation.platform
  const native = NATIVE_WIDTH[platform] || 1280
  const frameHeight = height || (platform === 'mobile' ? 800 : platform === 'tablet' ? 1000 : 860)
  const boxRef = useRef(null)
  const [scale, setScale] = useState(1)

  useEffect(() => {
    const el = boxRef.current
    if (!el) return
    const ro = new ResizeObserver(([entry]) => setScale(Math.min(1, entry.contentRect.width / native)))
    ro.observe(el)
    return () => ro.disconnect()
  }, [native])

  return (
    <div className={`device ${platform}`} style={{ maxWidth: platform === 'web' ? '100%' : native + (platform === 'mobile' ? 20 : 24) }}>
      {platform === 'web' && <div className="chrome"><i /><i /><i /></div>}
      <div ref={boxRef} style={{ height: frameHeight * scale, overflow: 'hidden' }}>
        {/* sandbox without allow-same-origin: generated pages can't touch this app */}
        <iframe title={`Mockup: ${generation.prompt}`} sandbox="allow-scripts" srcDoc={generation.html}
          style={{ width: native, height: frameHeight, transform: `scale(${scale})`, transformOrigin: '0 0' }} />
      </div>
    </div>
  )
}

export function openHtml(html) {
  const url = URL.createObjectURL(new Blob([html], { type: 'text/html' }))
  window.open(url, '_blank', 'noopener')
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}
export function downloadHtml(html, name) {
  const a = document.createElement('a')
  a.href = URL.createObjectURL(new Blob([html], { type: 'text/html' }))
  a.download = name
  a.click()
  setTimeout(() => URL.revokeObjectURL(a.href), 5000)
}

export default function Generate() {
  const [params] = useSearchParams()
  const { user } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const meta = useMeta()
  const options = useApi('/generator/options')
  const history = useApi(user ? '/my/generations' : null, null, [user?.id])

  const [form, setForm] = useState({
    prompt: params.get('prompt') || '',
    platform: params.get('platform') || 'web',
    screen_type: params.get('screen_type') || 'landing',
    style: 'modern',
    accent: '',
    category_id: '',
    use_ai: true,
  })
  const [current, setCurrent] = useState(null)
  const [notice, setNotice] = useState(null)
  const [busy, setBusy] = useState(false)
  const [errors, setErrors] = useState({})

  const set = (k) => (v) => setForm((f) => ({ ...f, [k]: v?.target ? v.target.value : v }))
  const aiEnabled = options.data?.ai_enabled

  // Mobile and web have different sensible default screens
  useEffect(() => {
    setForm((f) => (f.platform === 'mobile' && f.screen_type === 'landing' ? { ...f, screen_type: 'home' } : f))
  }, [form.platform])

  async function generate(e) {
    e?.preventDefault()
    if (!user) { toast('Log in to generate mockups.'); navigate('/login', { state: { from: '/generate' } }); return }
    setBusy(true); setErrors({}); setNotice(null)
    try {
      const r = await api('/generations', {
        method: 'POST',
        body: { ...form, accent: form.accent || null, category_id: form.category_id || null, use_ai: aiEnabled && form.use_ai },
      })
      setCurrent(r.data)
      setNotice(r.notice)
      history.setData((h) => (h ? { ...h, data: [r.data, ...h.data] } : h))
    } catch (err) {
      setErrors(err.errors || {})
      toast(err.status === 429 ? 'You are generating very fast. Wait a minute and try again.' : err.message, 'bad')
    } finally { setBusy(false) }
  }

  async function load(id) {
    const r = await api(`/generations/${id}`)
    setCurrent(r.data); setNotice(null)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  async function togglePublic() {
    const r = await api(`/generations/${current.id}`, { method: 'PATCH', body: { is_public: !current.is_public } })
    setCurrent((c) => ({ ...c, is_public: r.data.is_public }))
    history.setData((h) => ({ ...h, data: h.data.map((g) => (g.id === current.id ? { ...g, is_public: r.data.is_public } : g)) }))
    toast(r.data.is_public ? 'Shared to the showcase' : 'Removed from the showcase')
  }
  async function remove() {
    if (!confirm('Delete this mockup?')) return
    await api(`/generations/${current.id}`, { method: 'DELETE' })
    history.setData((h) => ({ ...h, data: h.data.filter((g) => g.id !== current.id) }))
    setCurrent(null)
    toast('Mockup deleted')
  }

  return (
    <div className="wrap">
      <div className="page-head">
        <div>
          <span className="eyebrow">Mockup generator</span>
          <h1>Generate a UI idea</h1>
          <p>Describe the product and pick a screen. You get a working HTML mockup you can open, download and share.</p>
        </div>
      </div>

      <div className="gen">
        <form className="panel gen-form stack" onSubmit={generate}>
          <label className="field">
            <span>What are you building?</span>
            <textarea className="textarea" rows={3} maxLength={500} required value={form.prompt} onChange={set('prompt')}
              placeholder="A grocery delivery app for busy families in Dhaka" />
            {errors.prompt && <span className="error-text">{errors.prompt[0]}</span>}
          </label>

          <div className="field">
            <span>Platform</span>
            <div className="segmented" role="group" aria-label="Platform">
              {(options.data?.platforms || ['web', 'mobile', 'tablet']).map((p) => (
                <button type="button" key={p} aria-pressed={form.platform === p} onClick={() => set('platform')(p)}>{label(p)}</button>
              ))}
            </div>
          </div>

          <div className="row" style={{ alignItems: 'flex-start' }}>
            <label className="field" style={{ flex: 1 }}>
              <span>Screen</span>
              <select className="select" value={form.screen_type} onChange={set('screen_type')}>
                {(options.data?.screen_types || []).map((s) => <option key={s} value={s}>{label(s)}</option>)}
              </select>
            </label>
            <label className="field" style={{ flex: 1 }}>
              <span>Domain <span className="hint">optional</span></span>
              <select className="select" value={form.category_id} onChange={set('category_id')}>
                <option value="">Detect from description</option>
                {meta?.categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </label>
          </div>

          <div className="field">
            <span>Visual style</span>
            <div className="styles">
              {(options.data?.styles || Object.keys(STYLE_PREVIEW)).map((s) => (
                <button type="button" key={s} className="style-opt" aria-pressed={form.style === s} onClick={() => set('style')(s)}>
                  <i style={{ background: STYLE_PREVIEW[s] }} /><span>{label(s)}</span>
                </button>
              ))}
            </div>
          </div>

          <div className="field">
            <span>Accent color <span className="hint">optional</span></span>
            <div className="accent-row">
              <button type="button" className="color-dot" aria-label="Style default" aria-pressed={!form.accent} onClick={() => set('accent')('')}
                style={{ background: 'conic-gradient(#e5484d,#ffc53d,#30a46c,#3e63dd,#8e4ec6,#e5484d)' }} />
              {ACCENTS.map((c) => <button type="button" key={c} className="color-dot" aria-label={c} aria-pressed={form.accent === c} style={{ background: c }} onClick={() => set('accent')(c)} />)}
              <input type="color" aria-label="Custom accent" value={form.accent || '#2b4bee'} onChange={set('accent')} />
            </div>
          </div>

          {aiEnabled && (
            <label className="check">
              <input type="checkbox" checked={form.use_ai} onChange={(e) => set('use_ai')(e.target.checked)} />
              <span>Design with AI <span className="muted small">(runs on Qwen, slower but more varied). Off uses the instant template engine.</span></span>
            </label>
          )}

          <button className="btn" disabled={busy || form.prompt.trim().length < 3} style={{ height: 46 }}>
            {busy ? <><span className="thinking" />{aiEnabled && form.use_ai ? 'Designing on the local model — up to 3 minutes' : 'Generating'}</> : <><Spark />Generate mockup</>}
          </button>
          {!user && <p className="muted small"><Link to="/login">Log in</Link> or <Link to="/register">create an account</Link> to generate and keep your mockups.</p>}
        </form>

        <section className="viewer" aria-live="polite">
          {current ? (
            <>
              <div className="viewer-bar">
                <strong style={{ marginRight: 'auto' }}>{current.prompt}</strong>
                <span className="pill">{PROVIDER_LABEL[current.provider] || 'Template'}</span>
                <button className="btn secondary small" onClick={generate} disabled={busy} title="Same brief, new layout and palette">{busy ? 'Designing…' : 'New direction'}</button>
                <button className="btn secondary small" onClick={() => openHtml(current.html)}><External />Open</button>
                <button className="btn secondary small" onClick={() => downloadHtml(current.html, `uispark-${current.platform}-${current.screen_type}.html`)}><Download />HTML</button>
                {current.is_owner && <button className={`btn small ${current.is_public ? 'on' : 'secondary'}`} onClick={togglePublic}><Globe />{current.is_public ? 'Shared' : 'Share'}</button>}
                {current.is_owner && <button className="btn quiet small icon-btn" aria-label="Delete mockup" onClick={remove}><Trash /></button>}
              </div>
              {notice && <div className="notice warn" style={{ margin: '12px 14px 0' }}>{notice}</div>}
              <DesignNote design={current.design} />
              <div className="viewer-stage"><Preview generation={current} /></div>
            </>
          ) : (
            <div className="viewer-empty">
              <h3>Your mockup appears here</h3>
              <p>Describe a product on the left, or start from one of these:</p>
              <div className="ideas">
                {IDEAS.map((i) => <button key={i} type="button" className="domain" onClick={() => set('prompt')(i)}>{i}</button>)}
              </div>
            </div>
          )}
        </section>
      </div>

      {user && history.data?.data?.length > 0 && (
        <section style={{ paddingBottom: 40 }}>
          <h2 className="section-title">Your mockups</h2>
          <div className="history">
            {history.data.data.map((g) => (
              <button key={g.id} className={`history-item ${current?.id === g.id ? 'current' : ''}`} onClick={() => load(g.id)}>
                <strong>{g.prompt}</strong>
                <span className="row" style={{ gap: 6 }}>
                  <span className="pill">{label(g.platform)}</span><span className="pill">{label(g.screen_type)}</span><span className="pill">{label(g.style)}</span>
                  {g.is_public && <span className="pill">Shared</span>}
                </span>
                <span className="muted small">{timeAgo(g.created_at)}</span>
              </button>
            ))}
          </div>
        </section>
      )}
    </div>
  )
}
