import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { Pager, Wall } from '../components/DesignCard'
import { Empty } from '../components/Guards'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useApi } from '../hooks'

export function Bookmarks() {
  const toast = useToast()
  const [collection, setCollection] = useState('')
  const [page, setPage] = useState(1)
  const [name, setName] = useState('')
  const collections = useApi('/my/collections')
  const { data, loading } = useApi('/my/bookmarks', { collection_id: collection, page }, [collection, page])

  async function create(e) {
    e.preventDefault()
    try {
      await api('/my/collections', { method: 'POST', body: { name } })
      setName('')
      collections.reload()
    } catch (err) { toast(err.message, 'bad') }
  }
  async function removeCollection(c) {
    if (!confirm(`Delete “${c.name}”? The designs stay in your bookmarks.`)) return
    await api(`/my/collections/${c.id}`, { method: 'DELETE' })
    if (String(collection) === String(c.id)) setCollection('')
    collections.reload()
  }

  return (
    <div className="wrap">
      <div className="page-head"><div><span className="eyebrow">Saved work</span><h1>Bookmarks</h1><p>Designs you saved, grouped into collections for each project.</p></div></div>
      <div className="tabs" role="tablist">
        <button role="tab" aria-selected={!collection} onClick={() => { setCollection(''); setPage(1) }}>All saved</button>
        {collections.data?.data.map((c) => (
          <button key={c.id} role="tab" aria-selected={String(collection) === String(c.id)} onClick={() => { setCollection(c.id); setPage(1) }}>
            {c.name} <span className="muted">{c.bookmarks_count}</span>
          </button>
        ))}
      </div>
      <form className="row" onSubmit={create} style={{ marginBottom: 24 }}>
        <input className="input" style={{ maxWidth: 260 }} value={name} onChange={(e) => setName(e.target.value)} maxLength={60} placeholder="New collection, e.g. Laundry app" aria-label="New collection name" />
        <button className="btn secondary" disabled={!name.trim()}>Create collection</button>
        {collection && <button type="button" className="btn quiet" onClick={() => removeCollection(collections.data.data.find((c) => String(c.id) === String(collection)))}>Delete this collection</button>}
      </form>
      {data?.data.length === 0 ? (
        <Empty title={collection ? 'This collection is empty' : 'No bookmarks yet'} action={<Link className="btn" to="/">Explore designs</Link>}>
          Press Save on any design to keep it here.
        </Empty>
      ) : (
        <>
          <Wall designs={data?.data || []} loading={loading} />
          <Pager meta={data?.meta} onPage={setPage} />
        </>
      )}
    </div>
  )
}

export function MyUploads() {
  const [status, setStatus] = useState('')
  const { data, loading } = useApi('/my/designs', { status }, [status])
  const list = data?.data || []
  const rejected = list.filter((d) => d.status === 'rejected')

  return (
    <div className="wrap">
      <div className="page-head">
        <div><span className="eyebrow">Your submissions</span><h1>My uploads</h1><p>Track your submissions. Pending designs go live once an admin approves them.</p></div>
        <span className="spacer" /><Link className="btn" to="/upload">Upload a design</Link>
      </div>
      <div className="tabs" role="tablist">
        {[['', 'All'], ['approved', 'Published'], ['pending', 'In review'], ['rejected', 'Not approved']].map(([v, l]) => (
          <button key={v} role="tab" aria-selected={status === v} onClick={() => setStatus(v)}>{l}</button>
        ))}
      </div>
      {rejected.length > 0 && (
        <div className="stack" style={{ gap: 8, marginBottom: 20 }}>
          {rejected.map((d) => (
            <div key={d.id} className="notice bad"><strong>{d.title}</strong> was not approved: {d.rejection_reason}. <EditTitle design={d} /></div>
          ))}
        </div>
      )}
      {data && list.length === 0 ? (
        <Empty title="Nothing here yet" action={<Link className="btn" to="/upload">Upload your first design</Link>}>Share a screen you admire or one you designed.</Empty>
      ) : <Wall designs={list} loading={loading} showStatus />}
    </div>
  )
}

/** Small inline fix-and-resubmit for rejected designs. */
function EditTitle({ design }) {
  const toast = useToast()
  const [open, setOpen] = useState(false)
  const [title, setTitle] = useState(design.title)
  const [description, setDescription] = useState(design.description || '')
  if (!open) return <button className="link-btn" onClick={() => setOpen(true)}>Edit and resubmit</button>
  return (
    <form className="stack" style={{ gap: 8, marginTop: 10 }} onSubmit={async (e) => {
      e.preventDefault()
      await api(`/designs/${design.slug}`, { method: 'PUT', body: { title, description } })
      toast('Sent back for review')
      setOpen(false)
      window.location.reload()
    }}>
      <input className="input" value={title} onChange={(e) => setTitle(e.target.value)} aria-label="Title" />
      <textarea className="textarea" value={description} onChange={(e) => setDescription(e.target.value)} aria-label="Description" />
      <div className="row"><button className="btn small">Resubmit for review</button><button type="button" className="btn quiet small" onClick={() => setOpen(false)}>Cancel</button></div>
    </form>
  )
}

export function Settings() {
  const { user, setUser } = useAuth()
  const toast = useToast()
  const [form, setForm] = useState({ name: user.name, bio: user.bio || '', current_password: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState({})
  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }))

  async function save(e) {
    e.preventDefault()
    const body = { name: form.name, bio: form.bio }
    if (form.password) Object.assign(body, { current_password: form.current_password, password: form.password, password_confirmation: form.password_confirmation })
    try {
      const r = await api('/auth/profile', { method: 'PUT', body })
      setUser(r.data); setErrors({})
      setForm((f) => ({ ...f, current_password: '', password: '', password_confirmation: '' }))
      toast('Settings saved')
    } catch (err) { setErrors(err.errors || {}); toast(err.message, 'bad') }
  }
  const err = (k) => errors[k] && <span className="error-text">{errors[k][0]}</span>

  return (
    <div className="wrap" style={{ maxWidth: 640, paddingBottom: 40 }}>
      <div className="page-head"><div><span className="eyebrow">Account</span><h1>Settings</h1></div></div>
      <form className="panel stack" onSubmit={save}>
        <label className="field"><span>Name</span><input className="input" value={form.name} onChange={set('name')} required />{err('name')}</label>
        <label className="field"><span>Email</span><input className="input" value={user.email} disabled /></label>
        <label className="field"><span>Bio <span className="hint">optional</span></span><textarea className="textarea" maxLength={280} value={form.bio} onChange={set('bio')} /></label>
        <h3 style={{ fontSize: 18, marginTop: 8 }}>Change password</h3>
        <label className="field"><span>Current password</span><input className="input" type="password" value={form.current_password} onChange={set('current_password')} autoComplete="current-password" />{err('current_password')}</label>
        <label className="field"><span>New password</span><input className="input" type="password" value={form.password} onChange={set('password')} autoComplete="new-password" />{err('password')}</label>
        <label className="field"><span>Confirm new password</span><input className="input" type="password" value={form.password_confirmation} onChange={set('password_confirmation')} autoComplete="new-password" /></label>
        <div><button className="btn">Save changes</button></div>
      </form>
    </div>
  )
}
