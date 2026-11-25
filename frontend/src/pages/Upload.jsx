import { useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api/client'
import { Upload as UploadIcon } from '../components/Icons'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { label, useMeta } from '../hooks'

export function TagInput({ value, onChange }) {
  const [text, setText] = useState('')
  const add = () => {
    const t = text.trim().toLowerCase()
    if (t && !value.includes(t) && value.length < 10) onChange([...value, t])
    setText('')
  }
  return (
    <div className="chip-input">
      {value.map((t) => <span key={t} className="tag">{t}<button type="button" aria-label={`Remove ${t}`} onClick={() => onChange(value.filter((x) => x !== t))}>×</button></span>)}
      <input value={text} onChange={(e) => setText(e.target.value)} placeholder={value.length ? '' : 'dark mode, cards, onboarding'}
        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add() } else if (e.key === 'Backspace' && !text && value.length) onChange(value.slice(0, -1)) }}
        onBlur={add} aria-label="Tags" />
    </div>
  )
}

export default function Upload() {
  const meta = useMeta()
  const { isAdmin } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const fileRef = useRef(null)
  const [file, setFile] = useState(null)
  const [preview, setPreview] = useState(null)
  const [over, setOver] = useState(false)
  const [busy, setBusy] = useState(false)
  const [errors, setErrors] = useState({})
  const [form, setForm] = useState({ title: '', description: '', category_id: '', platform: 'web', screen_type: 'landing', source_url: '', tags: [] })
  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e?.target ? e.target.value : e }))

  function pick(f) {
    if (!f) return
    if (!/^image\/(png|jpe?g|webp)$/.test(f.type)) { toast('Use a PNG, JPG or WebP screenshot.', 'bad'); return }
    if (f.size > 8 * 1024 * 1024) { toast('That image is over 8 MB. Try a smaller export.', 'bad'); return }
    setFile(f)
    setPreview(URL.createObjectURL(f))
    // Guess the platform from the aspect ratio
    const img = new Image()
    img.onload = () => setForm((p) => ({ ...p, platform: img.height > img.width * 1.4 ? 'mobile' : 'web' }))
    img.src = URL.createObjectURL(f)
  }

  async function submit(e) {
    e.preventDefault()
    if (!file) { toast('Add a screenshot first.', 'bad'); return }
    const body = new FormData()
    Object.entries(form).forEach(([k, v]) => {
      if (k === 'tags') v.forEach((t) => body.append('tags[]', t))
      else if (v) body.append(k, v)
    })
    body.append('image', file)
    setBusy(true); setErrors({})
    try {
      const r = await api('/designs', { method: 'POST', body })
      toast(r.message)
      navigate(r.data.status === 'approved' ? `/designs/${r.data.slug}` : '/me/uploads')
    } catch (err) {
      setErrors(err.errors || {})
      toast(err.message, 'bad')
    } finally { setBusy(false) }
  }

  const err = (k) => errors[k] && <span className="error-text">{errors[k][0]}</span>

  return (
    <div className="wrap">
      <div className="page-head">
        <div>
          <span className="eyebrow">Contribute</span>
          <h1>Share a design</h1>
          <p>Upload a screenshot of a website or app screen. {isAdmin ? 'As an admin, it is published right away.' : 'An admin reviews every upload before it appears in the gallery.'}</p>
        </div>
      </div>
      <form className="upload" onSubmit={submit}>
        <div
          className={`drop ${over ? 'over' : ''}`}
          role="button" tabIndex={0}
          onClick={() => fileRef.current?.click()}
          onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && fileRef.current?.click()}
          onDragOver={(e) => { e.preventDefault(); setOver(true) }}
          onDragLeave={() => setOver(false)}
          onDrop={(e) => { e.preventDefault(); setOver(false); pick(e.dataTransfer.files[0]) }}
        >
          {preview ? <img src={preview} alt="Selected screenshot" /> : (
            <div>
              <UploadIcon />
              <strong>Drop a screenshot here, or click to choose</strong>
              <p className="muted small" style={{ marginTop: 6 }}>PNG, JPG or WebP, at least 200px wide, up to 8 MB. Colors are extracted automatically.</p>
            </div>
          )}
          <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp" hidden onChange={(e) => pick(e.target.files[0])} />
        </div>
        {err('image')}

        <div className="panel stack">
          <label className="field"><span>Title</span>
            <input className="input" required maxLength={120} value={form.title} onChange={set('title')} placeholder="Grocery app checkout" />{err('title')}
          </label>
          <div className="row" style={{ alignItems: 'flex-start' }}>
            <label className="field" style={{ flex: 1, minWidth: 180 }}><span>Domain</span>
              <select className="select" required value={form.category_id} onChange={set('category_id')}>
                <option value="">Choose a domain</option>
                {meta?.categories.map((c) => <option key={c.id} value={c.id}>{c.icon} {c.name}</option>)}
              </select>{err('category_id')}
            </label>
            <label className="field" style={{ flex: 1, minWidth: 180 }}><span>Screen type</span>
              <select className="select" value={form.screen_type} onChange={set('screen_type')}>
                {(meta?.screen_types || []).map((s) => <option key={s} value={s}>{label(s)}</option>)}
              </select>
            </label>
          </div>
          <div className="field"><span>Platform</span>
            <div className="segmented" role="group" aria-label="Platform">
              {(meta?.platforms || []).map((p) => <button type="button" key={p} aria-pressed={form.platform === p} onClick={() => set('platform')(p)}>{label(p)}</button>)}
            </div>
          </div>
          <label className="field"><span>Description <span className="hint">optional</span></span>
            <textarea className="textarea" maxLength={2000} value={form.description} onChange={set('description')} placeholder="What makes this screen work well?" />
          </label>
          <label className="field"><span>Tags <span className="hint">press Enter after each, up to 10</span></span>
            <TagInput value={form.tags} onChange={set('tags')} />
          </label>
          <label className="field"><span>Source link <span className="hint">optional</span></span>
            <input className="input" type="url" value={form.source_url} onChange={set('source_url')} placeholder="https://" />{err('source_url')}
          </label>
          <button className="btn" disabled={busy} style={{ height: 46 }}>{busy ? 'Uploading…' : isAdmin ? 'Publish design' : 'Submit for review'}</button>
        </div>
      </form>
    </div>
  )
}
