import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../api/client'
import { Download, External, Globe, Spark, Trash } from '../components/Icons'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { label, timeAgo, useApi } from '../hooks'
import { downloadBlob, makeZip } from '../zip'
import '../generator.css'

const STYLE_PREVIEW = {
  modern: 'linear-gradient(135deg,#eef0ff,#5b5bd6)',
  minimal: 'linear-gradient(135deg,#ffffff 55%,#111 55%)',
  dark: 'linear-gradient(135deg,#0d0f14 60%,#7c9cff)',
  playful: 'linear-gradient(135deg,#ffe3d6,#ff6b5b)',
  corporate: 'linear-gradient(135deg,#e8f0fa,#0b5cab)',
  glass: 'linear-gradient(135deg,#3a1c71,#d76d77,#ffaf7b)',
}
const PROVIDER_LABEL = { local: 'Qwen (local)', anthropic: 'Claude', template: 'Template' }
const RESEARCH_LABEL = { live: 'Live web research', cache: 'Web research · cached', curated: 'Reference gallery', offline: 'Reference gallery', skipped: 'No web research' }
const ACTIVE = ['pending', 'running']
const ACCENTS = ['#2b4bee', '#0d9488', '#16a34a', '#ea580c', '#e11d48', '#7c3aed', '#111111']
const IDEAS = [
  { label: 'Pharmacy delivery', text: 'Pharmacy delivery app: customers upload a prescription, browse medicines, add to cart and track the rider.' },
  { label: 'Job portal', text: 'Job portal for fresh graduates in Bangladesh with job search, company profiles and an applicant dashboard.' },
  { label: 'Smart farm', text: 'Smart farm monitor showing soil moisture, pump control and weekly crop reports for each field.' },
  { label: 'Laundry pickup', text: 'Laundry pickup service: book a pickup, choose wash or dry-clean, pay with bKash and track the order.' },
]
const NATIVE_WIDTH = { web: 1280, tablet: 820, mobile: 390 }

/** Brand, palette, art direction and the web pages that inspired a generation. */
export function DesignNote({ design, compact }) {
  if (!design) return null
  const references = design.research?.references || []
  const recipe = design.recipe

  return (
    <div className="notice info" style={{ margin: compact ? 0 : '12px 14px 0', display: 'flex', flexDirection: 'column', gap: 7 }}>
      <div className="row" style={{ gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
        {design.brand && <strong>{design.brand}</strong>}
        {design.tagline && <span className="muted">{design.tagline}</span>}
        {(design.palette || []).map((c) => <span key={c} className="swatch-dot" title={c} style={{ background: c }} />)}
        {design.direction && <span className="pill">{design.direction}</span>}
        {recipe && <span>{recipe.hero} hero · {recipe.features} benefits · {recipe.order}</span>}
        {design.research && <span className="pill">{RESEARCH_LABEL[design.research.source] || 'References'}</span>}
      </div>
      {references.length > 0 && (
        <div className="row" style={{ gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
          <span>Ideas from</span>
          {references.slice(0, 5).map((r) => (
            <a key={r.url} className="pill" href={r.url} target="_blank" rel="noreferrer noopener"
              style={{ textDecoration: 'none', maxWidth: 240, overflow: 'hidden', textOverflow: 'ellipsis' }}
              title={r.ideas?.headings?.length ? `${r.title}\n\n${r.ideas.headings.join(' · ')}` : r.title}>
              {r.source || r.title}
            </a>
          ))}
        </div>
      )}
    </div>
  )
}

/** Renders a screen at its native viewport width, scaled down to fit the available space. */
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
        <iframe title={`Mockup: ${generation.prompt}`} sandbox="allow-scripts" srcDoc={generation.html || ''}
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
  downloadBlob(new Blob([html], { type: 'text/html' }), name)
}

/** Pick-many list backed by the database, with an inline "add your own" box. */
function ChipPicker({ items, selected, onToggle, onAdd, onRemove, max, placeholder, busy }) {
  const [text, setText] = useState('')
  const full = selected.length >= max

  async function add(e) {
    e.preventDefault()
    const name = text.trim()
    if (name.length < 2) return
    if (await onAdd(name)) setText('')
  }

  return (
    <div className="chip-picker">
      <div className="chips">
        {items.map((item) => {
          const on = selected.includes(item.slug)
          return (
            <span key={item.slug} className="chip-wrap">
              <button type="button" className={`domain ${item.custom ? 'custom' : ''}`} aria-pressed={on}
                disabled={!on && full} onClick={() => onToggle(item.slug)} title={item.description || item.name}>
                {item.name}{on && selected.length > 1 && <span className="count">{selected.indexOf(item.slug) + 1}</span>}
              </button>
              {item.custom && onRemove && (
                <button type="button" className="chip-x" aria-label={`Remove ${item.name}`} onClick={() => onRemove(item)}>×</button>
              )}
            </span>
          )
        })}
      </div>
      <div className="chip-add">
        <input className="input" value={text} maxLength={60} placeholder={placeholder}
          onChange={(e) => setText(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') add(e) }} />
        <button type="button" className="btn secondary small" onClick={add} disabled={busy || text.trim().length < 2}>Add</button>
      </div>
    </div>
  )
}

/** Small "add your own" row for single-choice lists (language, framework). */
function AddInline({ placeholder, onAdd }) {
  const [open, setOpen] = useState(false)
  const [text, setText] = useState('')
  if (!open) return <button type="button" className="link-btn" onClick={() => setOpen(true)}>+ Add your own</button>

  async function add() {
    if (text.trim().length < 2) return
    if (await onAdd(text.trim())) { setText(''); setOpen(false) }
  }
  return (
    <div className="chip-add">
      <input className="input" autoFocus value={text} maxLength={60} placeholder={placeholder}
        onChange={(e) => setText(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); add() } if (e.key === 'Escape') setOpen(false) }} />
      <button type="button" className="btn secondary small" onClick={add}>Add</button>
    </div>
  )
}

function QwenStatus({ qwen, onRefresh }) {
  if (!qwen) return null
  return (
    <div className={`qwen-status ${qwen.online ? 'on' : 'off'}`}>
      <span className="dot" />
      <span>
        {qwen.online
          ? <>Qwen is running locally · <code>{qwen.model}</code></>
          : <>Qwen is not running. Start LM Studio (or Ollama), load <code>{qwen.model}</code> and serve it at <code>{qwen.base_url}</code>.</>}
      </span>
      <button type="button" className="link-btn" onClick={onRefresh}>Check again</button>
    </div>
  )
}

function Progress({ generation }) {
  const p = generation.progress || {}
  const pct = p.total ? Math.round((Math.min(p.done || 0, p.total) / p.total) * 100) : 0
  const [waited, setWaited] = useState(0)
  useEffect(() => {
    const started = Date.now()
    const t = setInterval(() => setWaited(Math.round((Date.now() - started) / 1000)), 1000)
    return () => clearInterval(t)
  }, [generation.id])

  return (
    <div className="gen-progress">
      <div className="row" style={{ justifyContent: 'space-between', gap: 12 }}>
        <strong><span className="thinking" />{p.message || 'Working'}</strong>
        <span className="muted small">{pct}% · {Math.floor(waited / 60)}m {waited % 60}s</span>
      </div>
      <div className="bar"><i style={{ width: `${Math.max(4, pct)}%` }} /></div>
      {generation.status === 'pending' && waited > 20 && (
        <div className="notice warn">Still waiting for the generator worker. Is <code>start-dev.bat</code> (or <code>php artisan queue:work</code>) running?</div>
      )}
      <p className="muted small">Qwen runs on your computer, so each screen can take a few minutes. You can leave this page; the result is saved under “Your projects”.</p>
      {p.log?.length > 0 && (
        <ol className="gen-log">
          {p.log.slice(-8).map((l, i) => <li key={i}><span className="muted">{new Date(l.at).toLocaleTimeString()}</span> {l.message}</li>)}
        </ol>
      )}
    </div>
  )
}

function CodeView({ files, brand }) {
  const [active, setActive] = useState(files[0]?.path)
  const toast = useToast()
  useEffect(() => { if (!files.some((f) => f.path === active)) setActive(files[0]?.path) }, [files, active])
  const file = files.find((f) => f.path === active)

  const tree = useMemo(() => [...files].sort((a, b) => {
    const da = a.path.split('/').length > 1, db = b.path.split('/').length > 1
    return da === db ? a.path.localeCompare(b.path) : da ? -1 : 1
  }), [files])

  return (
    <div className="code-view">
      <div className="file-list" role="listbox" aria-label="Project files">
        {tree.map((f) => (
          <button key={f.path} type="button" role="option" aria-selected={f.path === active} className="file-btn" onClick={() => setActive(f.path)} title={f.path}>
            <span className="muted">{f.path.includes('/') ? f.path.slice(0, f.path.lastIndexOf('/') + 1) : ''}</span>{f.path.split('/').pop()}
          </button>
        ))}
      </div>
      {file && (
        <div className="code-pane">
          <div className="code-bar">
            <code>{file.path}</code>
            <span className="pill">{file.language}</span>
            <span className="spacer" />
            <button type="button" className="btn secondary small" onClick={() => navigator.clipboard.writeText(file.content).then(() => toast('Copied'))}>Copy</button>
            <button type="button" className="btn secondary small" onClick={() => downloadBlob(new Blob([file.content], { type: 'text/plain' }), file.path.split('/').pop())}><Download />File</button>
          </div>
          <pre className="code"><code>{file.content}</code></pre>
        </div>
      )}
      {!file && <p className="muted" style={{ padding: 16 }}>No files yet for {brand || 'this project'}.</p>}
    </div>
  )
}

export default function Generate() {
  const [params] = useSearchParams()
  const { user } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const options = useApi('/generator/options', null, [user?.id])
  const history = useApi(user ? '/my/generations' : null, null, [user?.id])
  const opts = options.data

  const [form, setForm] = useState({
    prompt: params.get('prompt') || '',
    language: '',
    framework: '',
    platform: params.get('platform') || 'web',
    style: 'modern',
    accent: '',
    screens: params.get('screen_type') ? [params.get('screen_type')] : [],
    domains: [],
    use_research: true,
  })
  const [current, setCurrent] = useState(null)
  const [view, setView] = useState('preview')
  const [screenIdx, setScreenIdx] = useState(0)
  const [busy, setBusy] = useState(false)
  const [adding, setAdding] = useState(false)
  const [errors, setErrors] = useState({})

  const set = (k) => (v) => setForm((f) => ({ ...f, [k]: v?.target ? v.target.value : v }))
  const frameworks = (opts?.frameworks || []).filter((f) => f.language === form.language)
  const limits = opts?.limits || { screens: 8, domains: 4, prompt: 4000 }

  // Defaults once the options arrive: first language, its first framework.
  useEffect(() => {
    if (!opts) return
    setForm((f) => {
      const language = f.language && opts.languages.some((l) => l.slug === f.language) ? f.language : opts.languages[0]?.slug || ''
      const fw = opts.frameworks.filter((x) => x.language === language)
      const framework = fw.some((x) => x.slug === f.framework) ? f.framework : fw[0]?.slug || ''
      const screens = f.screens.filter((s) => opts.screens.some((x) => x.slug === s))
      return { ...f, language, framework, screens }
    })
  }, [opts])

  // Poll while the worker is on it.
  useEffect(() => {
    if (!current || !ACTIVE.includes(current.status)) return
    const t = setTimeout(async () => {
      try {
        const r = await api(`/generations/${current.id}`)
        setCurrent(r.data)
        if (!ACTIVE.includes(r.data.status)) {
          history.reload()
          if (r.data.status === 'done') toast('Your UI is ready')
          else toast(r.data.error || 'Generation stopped', 'bad')
        }
      } catch (err) {
        if (err.status === 404) setCurrent(null)
      }
    }, 3000)
    return () => clearTimeout(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [current])

  useEffect(() => { setScreenIdx(0) }, [current?.id])

  // On narrow screens the result sits under the form, so bring it into view.
  function showViewer() {
    setTimeout(() => document.querySelector('.viewer')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50)
  }

  function toggle(key, slug, max) {
    setForm((f) => {
      const list = f[key]
      if (list.includes(slug)) return { ...f, [key]: list.filter((s) => s !== slug) }
      return list.length >= max ? f : { ...f, [key]: [...list, slug] }
    })
  }

  async function addOption(type, name, parent) {
    if (!user) { toast('Log in to add your own entries.'); return false }
    setAdding(true)
    try {
      const r = await api('/generator/options', { method: 'POST', body: { type, name, parent } })
      const item = r.data
      const listKey = { language: 'languages', framework: 'frameworks', screen: 'screens', domain: 'domains' }[type]
      options.setData((d) => (d[listKey].some((x) => x.slug === item.slug) ? d : { ...d, [listKey]: [...d[listKey], item] }))
      if (type === 'screen') setForm((f) => (f.screens.includes(item.slug) || f.screens.length >= limits.screens ? f : { ...f, screens: [...f.screens, item.slug] }))
      if (type === 'domain') setForm((f) => (f.domains.includes(item.slug) || f.domains.length >= limits.domains ? f : { ...f, domains: [...f.domains, item.slug] }))
      if (type === 'language') setForm((f) => ({ ...f, language: item.slug, framework: '' }))
      if (type === 'framework') setForm((f) => ({ ...f, framework: item.slug }))
      return true
    } catch (err) {
      toast(err.errors ? Object.values(err.errors)[0][0] : err.message, 'bad')
      return false
    } finally { setAdding(false) }
  }

  async function removeOption(type, item) {
    if (!confirm(`Remove “${item.name}” from your list?`)) return
    await api(`/generator/options/${item.id}`, { method: 'DELETE' })
    const listKey = type === 'screen' ? 'screens' : 'domains'
    options.setData((d) => ({ ...d, [listKey]: d[listKey].filter((x) => x.slug !== item.slug) }))
    setForm((f) => ({ ...f, [listKey]: f[listKey].filter((s) => s !== item.slug) }))
  }

  async function generate(e) {
    e?.preventDefault()
    if (!user) { toast('Log in to generate UI.'); navigate('/login', { state: { from: '/generate' } }); return }
    setBusy(true); setErrors({})
    try {
      const r = await api('/generations', {
        method: 'POST',
        body: { ...form, accent: form.accent || null },
      })
      setCurrent(r.data)
      setView('preview')
      history.setData((h) => (h ? { ...h, data: [r.data, ...h.data] } : h))
      showViewer()
    } catch (err) {
      setErrors(err.errors || {})
      if (err.status === 503) options.reload()
      toast(err.status === 429 ? 'You are generating very fast. Wait a minute and try again.' : err.message, 'bad')
    } finally { setBusy(false) }
  }

  async function load(id) {
    const r = await api(`/generations/${id}`)
    setCurrent(r.data)
    showViewer()
  }
  function reuse() {
    if (!current) return
    setForm((f) => ({
      ...f,
      prompt: current.prompt,
      platform: current.platform,
      style: current.style || f.style,
      accent: current.accent || '',
      language: current.language || f.language,
      framework: current.framework || f.framework,
      screens: (current.screens || []).map((s) => s.slug).filter((s) => opts?.screens.some((x) => x.slug === s)),
      domains: (current.domains || []).map((s) => s.slug).filter((s) => opts?.domains.some((x) => x.slug === s)),
    }))
    toast('Brief copied into the form')
  }
  async function retry() {
    const r = await api(`/generations/${current.id}/retry`, { method: 'POST' })
    setCurrent(r.data)
  }
  async function togglePublic() {
    const r = await api(`/generations/${current.id}`, { method: 'PATCH', body: { is_public: !current.is_public } })
    setCurrent((c) => ({ ...c, is_public: r.data.is_public }))
    history.setData((h) => ({ ...h, data: h.data.map((g) => (g.id === current.id ? { ...g, is_public: r.data.is_public } : g)) }))
    toast(r.data.is_public ? 'Shared to the showcase' : 'Removed from the showcase')
  }
  async function remove() {
    if (!confirm(ACTIVE.includes(current.status) ? 'Stop and delete this generation?' : 'Delete this project?')) return
    await api(`/generations/${current.id}`, { method: 'DELETE' })
    history.setData((h) => ({ ...h, data: h.data.filter((g) => g.id !== current.id) }))
    setCurrent(null)
    toast('Deleted')
  }
  function downloadZip() {
    const brand = current.design?.brand || 'uispark-project'
    const root = brand.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'uispark-project'
    downloadBlob(makeZip(current.files || [], root), `${root}.zip`)
  }

  const previews = current?.previews || []
  const screen = previews[Math.min(screenIdx, previews.length - 1)]
  const promptLeft = limits.prompt - form.prompt.length
  const languageName = (slug) => opts?.languages.find((l) => l.slug === slug)?.name || slug
  const frameworkName = (slug) => opts?.frameworks.find((l) => l.slug === slug)?.name || slug

  return (
    <div className="wrap">
      <div className="page-head">
        <div>
          <span className="eyebrow">UI generator · Qwen on your computer</span>
          <h1>Generate UI and code</h1>
          <p>Pick a language and framework, the screens and the domain, then describe what you need. Qwen studies similar products on the web, designs every screen and writes the code files.</p>
        </div>
      </div>

      <div className="gen">
        <form className="panel gen-form stack" onSubmit={generate}>
          <QwenStatus qwen={opts?.qwen} onRefresh={options.reload} />

          <div className="row" style={{ alignItems: 'flex-start' }}>
            <label className="field" style={{ flex: 1 }}>
              <span>Language</span>
              <select className="select" value={form.language} onChange={(e) => {
                const language = e.target.value
                const first = (opts?.frameworks || []).find((f) => f.language === language)
                setForm((f) => ({ ...f, language, framework: first?.slug || '' }))
              }}>
                {(opts?.languages || []).map((l) => <option key={l.slug} value={l.slug}>{l.name}{l.custom ? ' (yours)' : ''}</option>)}
              </select>
              <AddInline placeholder="e.g. Rust" onAdd={(name) => addOption('language', name)} />
            </label>
            <label className="field" style={{ flex: 1 }}>
              <span>Framework</span>
              <select className="select" value={form.framework} onChange={set('framework')} disabled={!frameworks.length}>
                {!frameworks.length && <option value="">Add a framework →</option>}
                {frameworks.map((f) => <option key={f.slug} value={f.slug}>{f.name}{f.custom ? ' (yours)' : ''}</option>)}
              </select>
              <AddInline placeholder={`A ${languageName(form.language)} framework`} onAdd={(name) => addOption('framework', name, form.language)} />
              {errors.framework && <span className="error-text">{errors.framework[0]}</span>}
            </label>
          </div>

          <div className="field">
            <span>Screens <span className="hint">{form.screens.length}/{limits.screens} · in this order</span></span>
            <ChipPicker items={opts?.screens || []} selected={form.screens} max={limits.screens} busy={adding}
              onToggle={(slug) => toggle('screens', slug, limits.screens)}
              onAdd={(name) => addOption('screen', name)} onRemove={(item) => removeOption('screen', item)}
              placeholder="Add a screen, e.g. Prescription upload" />
            {errors.screens && <span className="error-text">{errors.screens[0]}</span>}
          </div>

          <div className="field">
            <span>Domain <span className="hint">{form.domains.length}/{limits.domains} · optional</span></span>
            <ChipPicker items={opts?.domains || []} selected={form.domains} max={limits.domains} busy={adding}
              onToggle={(slug) => toggle('domains', slug, limits.domains)}
              onAdd={(name) => addOption('domain', name)} onRemove={(item) => removeOption('domain', item)}
              placeholder="Add a domain, e.g. Veterinary" />
          </div>

          <label className="field">
            <span>Your requirements <span className="hint">{promptLeft} left</span></span>
            <textarea className="textarea" rows={6} maxLength={limits.prompt} required value={form.prompt} onChange={set('prompt')}
              placeholder="What the product does, who uses it, and what each page needs. e.g. Login with phone + OTP, a dashboard with today's orders and earnings, an order list with filters…" />
            {errors.prompt && <span className="error-text">{errors.prompt[0]}</span>}
            {!form.prompt && (
              <div className="ideas gen-ideas">
                <span className="muted small">Try:</span>
                {IDEAS.map((i) => <button key={i.label} type="button" className="domain" title={i.text} onClick={() => set('prompt')(i.text)}>{i.label}</button>)}
              </div>
            )}
          </label>

          <div className="field">
            <span>Platform</span>
            <div className="segmented" role="group" aria-label="Platform">
              {(opts?.platforms || ['web', 'mobile', 'tablet']).map((p) => (
                <button type="button" key={p} aria-pressed={form.platform === p} onClick={() => set('platform')(p)}>{label(p)}</button>
              ))}
            </div>
          </div>

          <div className="field">
            <span>Visual style</span>
            <div className="styles">
              {(opts?.styles || Object.keys(STYLE_PREVIEW)).map((s) => (
                <button type="button" key={s} className="style-opt" aria-pressed={form.style === s} onClick={() => set('style')(s)}>
                  <i style={{ background: STYLE_PREVIEW[s] }} /><span>{label(s)}</span>
                </button>
              ))}
            </div>
          </div>

          <div className="field">
            <span>Accent color <span className="hint">optional</span></span>
            <div className="accent-row">
              <button type="button" className="color-dot" aria-label="Let Qwen choose" aria-pressed={!form.accent} onClick={() => set('accent')('')}
                style={{ background: 'conic-gradient(#e5484d,#ffc53d,#30a46c,#3e63dd,#8e4ec6,#e5484d)' }} />
              {ACCENTS.map((c) => <button type="button" key={c} className="color-dot" aria-label={c} aria-pressed={form.accent === c} style={{ background: c }} onClick={() => set('accent')(c)} />)}
              <input type="color" aria-label="Custom accent" value={form.accent || '#2b4bee'} onChange={set('accent')} />
            </div>
          </div>

          {opts?.research !== false && (
            <label className="check">
              <input type="checkbox" checked={form.use_research} onChange={(e) => set('use_research')(e.target.checked)} />
              <span>Research the web first <span className="muted small">(finds similar products and passes their layout ideas to Qwen)</span></span>
            </label>
          )}

          <button className="btn" disabled={busy || form.prompt.trim().length < 10 || !form.screens.length || !form.framework} style={{ height: 46 }}>
            {busy ? <><span className="thinking" />Sending to Qwen…</> : <><Spark />Generate {form.screens.length || ''} screen{form.screens.length === 1 ? '' : 's'}</>}
          </button>
          {!form.screens.length && <p className="muted small">Pick at least one screen.</p>}
          {!user && <p className="muted small"><Link to="/login">Log in</Link> or <Link to="/register">create an account</Link> to generate and keep your projects.</p>}
        </form>

        <section className={`viewer ${current ? '' : 'is-empty'}`} aria-live="polite">
          {current ? (
            <>
              <div className="viewer-bar">
                <div style={{ marginRight: 'auto', minWidth: 0 }}>
                  <strong className="clamp-1">{current.design?.brand || current.prompt}</strong>
                  <div className="muted small">
                    {[languageName(current.language), frameworkName(current.framework), label(current.platform)].filter(Boolean).join(' · ')}
                  </div>
                </div>
                <span className="pill">{current.status === 'done' ? PROVIDER_LABEL[current.provider] || 'Qwen' : label(current.status)}</span>
                {current.status === 'done' && (
                  <>
                    <button className="btn secondary small" onClick={downloadZip} disabled={!current.files?.length}><Download />ZIP</button>
                    {screen && <button className="btn secondary small" onClick={() => openHtml(screen.html)}><External />Open</button>}
                    {current.is_owner && <button className={`btn small ${current.is_public ? 'on' : 'secondary'}`} onClick={togglePublic}><Globe />{current.is_public ? 'Shared' : 'Share'}</button>}
                  </>
                )}
                {current.is_owner && current.status === 'failed' && <button className="btn small" onClick={retry}>Try again</button>}
                <button className="btn secondary small" onClick={reuse} title="Copy this brief back into the form">Reuse brief</button>
                {current.is_owner && <button className="btn quiet small icon-btn" aria-label="Delete" onClick={remove}><Trash /></button>}
              </div>

              {ACTIVE.includes(current.status) && <Progress generation={current} />}
              {current.status === 'failed' && (
                <div className="notice bad" style={{ margin: '12px 14px 0' }}>
                  <strong>Generation stopped.</strong> {current.error}
                </div>
              )}

              {previews.length > 0 && (
                <>
                  {current.status === 'done' && <DesignNote design={current.design} />}
                  <div className="result-tabs">
                    <div className="tabs" role="tablist">
                      <button type="button" role="tab" aria-selected={view === 'preview'} className={view === 'preview' ? 'active' : ''} onClick={() => setView('preview')}>Preview</button>
                      <button type="button" role="tab" aria-selected={view === 'code'} className={view === 'code' ? 'active' : ''} onClick={() => setView('code')} disabled={!current.files?.length}>
                        Code {current.files?.length ? `(${current.files.length} files)` : ''}
                      </button>
                    </div>
                    {view === 'preview' && previews.length > 1 && (
                      <div className="segmented screen-tabs" role="group" aria-label="Screen">
                        {previews.map((p, i) => <button type="button" key={p.slug} aria-pressed={i === screenIdx} onClick={() => setScreenIdx(i)}>{p.name}</button>)}
                      </div>
                    )}
                  </div>
                  {view === 'preview' && screen && <div className="viewer-stage"><Preview generation={{ ...current, html: screen.html }} /></div>}
                  {view === 'code' && <CodeView files={current.files || []} brand={current.design?.brand} />}
                </>
              )}
            </>
          ) : (
            <div className="viewer-empty">
              <h3>Your screens and code appear here</h3>
              <p>Choose a framework and screens on the left, describe the product, and Qwen designs each screen and writes the files.</p>
              <ol className="how-list">
                <li>Searches the web for products like yours</li>
                <li>Plans a brand, colours and the content of every screen</li>
                <li>Designs each screen as a live preview</li>
                <li>Writes the screens in your framework, with routing and a README</li>
              </ol>
            </div>
          )}
        </section>
      </div>

      {user && history.data?.data?.length > 0 && (
        <section style={{ paddingBottom: 40 }}>
          <h2 className="section-title">Your projects</h2>
          <div className="history">
            {history.data.data.map((g) => (
              <button key={g.id} className={`history-item ${current?.id === g.id ? 'current' : ''}`} onClick={() => load(g.id)}>
                <strong>{g.design?.brand ? `${g.design.brand} · ` : ''}{g.prompt}</strong>
                <span className="row" style={{ gap: 6, flexWrap: 'wrap' }}>
                  {g.framework && <span className="pill">{frameworkName(g.framework)}</span>}
                  <span className="pill">{label(g.platform)}</span>
                  <span className="pill">{(g.screens || []).length} screen{(g.screens || []).length === 1 ? '' : 's'}</span>
                  {g.status !== 'done' && <span className={`pill status-${g.status}`}>{label(g.status)}</span>}
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
