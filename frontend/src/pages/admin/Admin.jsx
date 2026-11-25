import { useState } from 'react'
import { Link, NavLink, Route, Routes } from 'react-router-dom'
import { api } from '../../api/client'
import { PaletteStrip, Pager } from '../../components/DesignCard'
import { Empty } from '../../components/Guards'
import { useAuth } from '../../context/AuthContext'
import { useToast } from '../../context/ToastContext'
import { invalidateMeta, label, timeAgo, useApi } from '../../hooks'

export default function Admin() {
  const stats = useApi('/admin/stats')
  const pending = stats.data?.totals.pending
  return (
    <div className="wrap" style={{ paddingBottom: 40 }}>
      <div className="page-head"><div><span className="eyebrow">Studio control</span><h1>Admin</h1><p>Review uploads, manage people and keep the domain list tidy.</p></div></div>
      <nav className="tabs">
        <NavLink to="/admin" end>Overview</NavLink>
        <NavLink to="/admin/review">Review queue {pending > 0 && <span className="pill">{pending}</span>}</NavLink>
        <NavLink to="/admin/users">Users</NavLink>
        <NavLink to="/admin/categories">Domains</NavLink>
      </nav>
      <Routes>
        <Route index element={<Overview stats={stats} />} />
        <Route path="review" element={<Review onChange={stats.reload} />} />
        <Route path="users" element={<Users />} />
        <Route path="categories" element={<Categories />} />
      </Routes>
    </div>
  )
}

function Overview({ stats }) {
  const s = stats.data
  if (!s) return <div className="skeleton" style={{ height: 300 }} />
  const maxDay = Math.max(1, ...s.daily_uploads.map((d) => d.uploads))
  const maxCat = Math.max(1, ...s.by_category.map((c) => c.count))
  const t = s.totals

  return (
    <div className="stack" style={{ gap: 20 }}>
      <div className="kpis">
        <div className={`kpi ${t.pending ? 'attention' : ''}`}><span className="muted">Waiting for review</span><b>{t.pending}</b>{t.pending > 0 && <Link to="/admin/review" className="small">Review now</Link>}</div>
        <div className="kpi"><span className="muted">Published designs</span><b>{t.designs}</b></div>
        <div className="kpi"><span className="muted">Users</span><b>{t.users}</b></div>
        <div className="kpi"><span className="muted">Mockups generated</span><b>{t.generations}</b></div>
        <div className="kpi"><span className="muted">Likes</span><b>{t.likes}</b></div>
        <div className="kpi"><span className="muted">Comments</span><b>{t.comments}</b></div>
      </div>
      <div className="two-col">
        <div className="panel">
          <strong>Uploads, last 14 days</strong>
          <div className="bars" role="img" aria-label="Daily uploads chart">
            {s.daily_uploads.map((d) => (
              <div key={d.date} title={`${d.date}: ${d.uploads}`}>
                {d.uploads > 0 && <span>{d.uploads}</span>}
                <i style={{ height: `${(d.uploads / maxDay) * 100}%` }} />
                <span>{new Date(d.date).getDate()}</span>
              </div>
            ))}
          </div>
        </div>
        <div className="panel">
          <strong>Designs by domain</strong>
          <div style={{ marginTop: 12 }}>
            {s.by_category.filter((c) => c.count > 0).slice(0, 8).map((c) => (
              <div key={c.name} className="hbar"><span>{c.icon} {c.name}</span><i style={{ width: `${(c.count / maxCat) * 100}%` }} /><span className="muted">{c.count}</span></div>
            ))}
            {s.by_category.every((c) => c.count === 0) && <p className="muted">No published designs yet.</p>}
          </div>
        </div>
      </div>
      <div className="panel">
        <strong>Most liked</strong>
        <table className="table" style={{ marginTop: 10 }}>
          <thead><tr><th>Design</th><th>Domain</th><th>Platform</th><th>Likes</th><th>Views</th></tr></thead>
          <tbody>
            {s.top_designs.map((d) => (
              <tr key={d.id}><td><Link to={`/designs/${d.slug}`}>{d.title}</Link></td><td>{d.category?.name}</td><td>{label(d.platform)}</td><td>{d.likes_count}</td><td>{d.views_count}</td></tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

const REASONS = ['Low-quality or blurry image', 'Not a UI screen', 'Duplicate of an existing design', 'Wrong domain or screen type', 'Contains personal data']

function ReviewItem({ d, onDone }) {
  const toast = useToast()
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)

  async function act(kind) {
    if (kind === 'reject' && !reason.trim()) { toast('Add a reason so the uploader knows what to fix.', 'bad'); return }
    setBusy(true)
    try {
      await api(`/admin/designs/${d.slug}/${kind}`, { method: 'PATCH', body: kind === 'reject' ? { reason } : undefined })
      toast(kind === 'approve' ? `Approved “${d.title}”` : `Rejected “${d.title}”`)
      onDone()
    } catch (err) { toast(err.message, 'bad') } finally { setBusy(false) }
  }

  return (
    <div className="queue-item">
      <a href={d.image_url} target="_blank" rel="noreferrer"><img src={d.image_url} alt={d.title} /><PaletteStrip palette={d.palette} /></a>
      <div className="stack" style={{ gap: 6 }}>
        <strong style={{ fontSize: 17 }}>{d.title}</strong>
        <span className="muted small">{label(d.platform)} {label(d.screen_type).toLowerCase()} in {d.category?.name}, by {d.user?.name}, {timeAgo(d.created_at)}</span>
        {d.description && <p className="small">{d.description}</p>}
        {d.tags?.length > 0 && <div className="tags">{d.tags.map((t) => <span key={t.slug} className="tag">{t.name}</span>)}</div>}
        {d.source_url && <a className="small" href={d.source_url} target="_blank" rel="noreferrer noopener">{d.source_url}</a>}
        {d.status === 'rejected' && <span className="notice bad small">Rejected: {d.rejection_reason}</span>}
      </div>
      <div className="queue-actions">
        {d.status !== 'approved' && <button className="btn" disabled={busy} onClick={() => act('approve')}>Approve</button>}
        {d.status !== 'rejected' && (
          <>
            <select className="select" value={REASONS.includes(reason) ? reason : ''} onChange={(e) => setReason(e.target.value)} aria-label="Rejection reason">
              <option value="">Choose a reason…</option>
              {REASONS.map((r) => <option key={r}>{r}</option>)}
            </select>
            <input className="input" placeholder="Or write a reason" value={reason} onChange={(e) => setReason(e.target.value)} maxLength={255} />
            <button className="btn danger" disabled={busy} onClick={() => act('reject')}>Reject</button>
          </>
        )}
      </div>
    </div>
  )
}

function Review({ onChange }) {
  const [status, setStatus] = useState('pending')
  const [page, setPage] = useState(1)
  const { data, reload } = useApi('/admin/designs', { status, page }, [status, page])
  const done = () => { reload(); onChange() }

  return (
    <div>
      <div className="segmented" role="group" aria-label="Status" style={{ marginBottom: 18 }}>
        {[['pending', 'Pending'], ['approved', 'Approved'], ['rejected', 'Rejected']].map(([v, l]) => (
          <button key={v} aria-pressed={status === v} onClick={() => { setStatus(v); setPage(1) }}>{l}</button>
        ))}
      </div>
      {data?.data.length === 0 ? <Empty title={status === 'pending' ? 'All caught up' : `No ${status} designs`}>{status === 'pending' ? 'New community uploads will appear here.' : null}</Empty> : (
        <div className="queue">{data?.data.map((d) => <ReviewItem key={d.id} d={d} onDone={done} />)}</div>
      )}
      <Pager meta={data?.meta} onPage={setPage} />
    </div>
  )
}

function Users() {
  const { user: me } = useAuth()
  const toast = useToast()
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const { data, setData } = useApi('/admin/users', { q, page }, [q, page])

  async function update(u, patch, msg) {
    try {
      const r = await api(`/admin/users/${u.id}`, { method: 'PATCH', body: patch })
      setData((d) => ({ ...d, data: d.data.map((x) => (x.id === u.id ? r.data : x)) }))
      toast(msg)
    } catch (err) { toast(err.message, 'bad') }
  }

  return (
    <div className="stack">
      <input className="input" style={{ maxWidth: 360 }} placeholder="Search by name or email" value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} aria-label="Search users" />
      <div className="table-wrap">
        <table className="table">
          <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Uploads</th><th>Joined</th><th /></tr></thead>
          <tbody>
            {data?.data.map((u) => (
              <tr key={u.id} style={{ opacity: u.is_banned ? 0.55 : 1 }}>
                <td><strong>{u.name}</strong>{u.is_banned && <span className="pill" style={{ marginLeft: 6 }}>Suspended</span>}</td>
                <td>{u.email}</td>
                <td>{label(u.role)}</td>
                <td>{u.designs_count}</td>
                <td className="muted">{timeAgo(u.created_at)}</td>
                <td>
                  {u.id !== me.id && (
                    <div className="row" style={{ justifyContent: 'flex-end' }}>
                      <button className="btn secondary small" onClick={() => update(u, { role: u.role === 'admin' ? 'user' : 'admin' }, u.role === 'admin' ? `${u.name} is no longer an admin` : `${u.name} is now an admin`)}>
                        {u.role === 'admin' ? 'Remove admin' : 'Make admin'}
                      </button>
                      <button className={`btn small ${u.is_banned ? 'secondary' : 'danger'}`} onClick={() => update(u, { is_banned: !u.is_banned }, u.is_banned ? `${u.name} restored` : `${u.name} suspended`)}>
                        {u.is_banned ? 'Restore' : 'Suspend'}
                      </button>
                    </div>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pager meta={data?.meta} onPage={setPage} />
    </div>
  )
}

function Categories() {
  const toast = useToast()
  const { data, reload } = useApi('/categories')
  const [form, setForm] = useState({ name: '', icon: '', description: '' })
  const [editing, setEditing] = useState(null)

  async function save(e) {
    e.preventDefault()
    try {
      if (editing) await api(`/admin/categories/${editing}`, { method: 'PUT', body: form })
      else await api('/admin/categories', { method: 'POST', body: form })
      toast(editing ? 'Domain updated' : 'Domain added')
      setForm({ name: '', icon: '', description: '' }); setEditing(null)
      invalidateMeta(); reload()
    } catch (err) { toast(err.message, 'bad') }
  }
  async function remove(c) {
    if (!confirm(`Delete “${c.name}”?`)) return
    try { await api(`/admin/categories/${c.id}`, { method: 'DELETE' }); invalidateMeta(); reload(); toast('Domain deleted') } catch (err) { toast(err.message, 'bad') }
  }

  return (
    <div className="two-col">
      <div className="table-wrap">
        <table className="table">
          <thead><tr><th>Domain</th><th>Designs</th><th /></tr></thead>
          <tbody>
            {data?.data.map((c) => (
              <tr key={c.id}>
                <td><strong>{c.icon} {c.name}</strong><div className="muted small">{c.description}</div></td>
                <td>{c.designs_count}</td>
                <td><div className="row" style={{ justifyContent: 'flex-end' }}>
                  <button className="btn secondary small" onClick={() => { setEditing(c.id); setForm({ name: c.name, icon: c.icon || '', description: c.description || '' }) }}>Edit</button>
                  <button className="btn danger small" disabled={c.designs_count > 0} title={c.designs_count > 0 ? 'Has designs' : ''} onClick={() => remove(c)}>Delete</button>
                </div></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <form className="panel stack" onSubmit={save} style={{ alignSelf: 'start' }}>
        <strong>{editing ? 'Edit domain' : 'Add a domain'}</strong>
        <label className="field"><span>Name</span><input className="input" required maxLength={80} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Automotive" /></label>
        <label className="field"><span>Icon <span className="hint">one emoji</span></span><input className="input" maxLength={16} value={form.icon} onChange={(e) => setForm({ ...form, icon: e.target.value })} placeholder="🚗" /></label>
        <label className="field"><span>Description</span><input className="input" maxLength={255} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></label>
        <div className="row"><button className="btn">{editing ? 'Save domain' : 'Add domain'}</button>{editing && <button type="button" className="btn quiet" onClick={() => { setEditing(null); setForm({ name: '', icon: '', description: '' }) }}>Cancel</button>}</div>
      </form>
    </div>
  )
}
