import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client'
import { Wall } from '../components/DesignCard'
import { Empty } from '../components/Guards'
import { Bookmark, External, Eye, Heart, Trash } from '../components/Icons'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { label, timeAgo, useApi } from '../hooks'
import Comments from './Comments'

export default function DesignDetail() {
  const { slug } = useParams()
  const { user } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const { data, loading, error, setData } = useApi(`/designs/${slug}`, null, [user?.id])
  const similar = useApi(`/designs/${slug}/similar`)
  const collections = useApi(user ? '/my/collections' : null)
  const [busy, setBusy] = useState(false)

  if (loading && !data) return <div className="wrap detail"><div className="skeleton" style={{ height: 600 }} /><div className="skeleton" style={{ height: 400 }} /></div>
  if (error) return <div className="wrap" style={{ padding: '60px 0' }}><Empty title="Design not found" action={<Link className="btn" to="/">Back to explore</Link>}>It may have been removed or is still waiting for review.</Empty></div>

  const d = data.data
  const patch = (p) => setData((old) => ({ ...old, data: { ...old.data, ...p } }))

  const needLogin = () => { toast('Log in to save and like designs.'); navigate('/login', { state: { from: `/designs/${slug}` } }) }

  async function like() {
    if (!user) return needLogin()
    const r = await api(`/designs/${slug}/like`, { method: 'POST' })
    patch({ is_liked: r.liked, likes_count: r.likes_count })
  }
  async function bookmark(collectionId) {
    if (!user) return needLogin()
    setBusy(true)
    try {
      const r = await api(`/designs/${slug}/bookmark`, { method: 'POST', body: { collection_id: collectionId || null } })
      patch({ is_bookmarked: r.bookmarked, bookmarks_count: r.bookmarks_count })
      toast(r.bookmarked ? 'Saved to bookmarks' : 'Removed from bookmarks')
    } finally { setBusy(false) }
  }
  async function remove() {
    if (!confirm('Delete this design? This cannot be undone.')) return
    await api(`/designs/${slug}`, { method: 'DELETE' })
    toast('Design deleted')
    navigate('/me/uploads')
  }
  const copy = (hex) => navigator.clipboard?.writeText(hex).then(() => toast(`Copied ${hex}`))

  return (
    <div className="wrap">
      <div className="detail">
        <div className={`stage ${d.platform === 'mobile' ? 'mobile' : ''}`}>
          <img src={d.image_url} alt={d.title} />
        </div>

        <aside className="aside">
          {d.status === 'pending' && <div className="notice warn">This design is waiting for review. Only you and admins can see it.</div>}
          {d.status === 'rejected' && <div className="notice bad">Not approved: {d.rejection_reason}. Edit it from My uploads to send it back for review.</div>}

          <div className="stack" style={{ gap: 10 }}>
            <h1>{d.title}</h1>
            <div className="byline"><span className="avatar">{d.user?.name?.charAt(0)}</span>Shared by {d.user?.name}, {timeAgo(d.approved_at || d.created_at)}</div>
          </div>

          {d.description && <p>{d.description}</p>}

          {d.status === 'approved' && (
            <div className="row">
              <button className={`btn ${d.is_liked ? 'on' : 'secondary'}`} aria-pressed={d.is_liked} onClick={like}>
                <Heart filled={d.is_liked} />{d.is_liked ? 'Liked' : 'Like'} {d.likes_count > 0 && `(${d.likes_count})`}
              </button>
              <button className={`btn ${d.is_bookmarked ? 'on' : ''}`} aria-pressed={d.is_bookmarked} disabled={busy} onClick={() => bookmark()}>
                <Bookmark filled={d.is_bookmarked} />{d.is_bookmarked ? 'Saved' : 'Save'}
              </button>
              {!d.is_bookmarked && collections.data?.data?.length > 0 && (
                <select className="select" style={{ width: 'auto', height: 40 }} aria-label="Save to collection" value="" onChange={(e) => bookmark(e.target.value)}>
                  <option value="">Save to collection…</option>
                  {collections.data.data.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              )}
            </div>
          )}

          <dl className="facts">
            <dt>Domain</dt><dd><Link to={`/?category=${d.category?.slug}`}>{d.category?.icon} {d.category?.name}</Link></dd>
            <dt>Platform</dt><dd><Link to={`/?platform=${d.platform}`}>{label(d.platform)}</Link></dd>
            <dt>Screen</dt><dd><Link to={`/?screen_type=${d.screen_type}`}>{label(d.screen_type)}</Link></dd>
            <dt>Views</dt><dd className="row" style={{ gap: 6 }}><Eye width={15} />{d.views_count}</dd>
            {d.source_url && <><dt>Source</dt><dd><a href={d.source_url} target="_blank" rel="noreferrer noopener">Visit original <External width={13} /></a></dd></>}
          </dl>

          {d.palette?.length > 0 && (
            <div className="stack" style={{ gap: 10 }}>
              <strong>Color palette</strong>
              <div className="swatches">
                {d.palette.map((c) => (
                  <button key={c.hex} className="swatch" onClick={() => copy(c.hex)} title="Copy hex">
                    <i style={{ background: c.hex }} />
                    <span>{c.hex.toUpperCase()}<small>{c.percent}%</small></span>
                  </button>
                ))}
              </div>
              <span className="muted small">Click a color to copy its hex code.</span>
            </div>
          )}

          {d.tags?.length > 0 && <div className="tags">{d.tags.map((t) => <Link key={t.slug} className="tag" to={`/?tag=${t.slug}`}>{t.name}</Link>)}</div>}

          <div className="row">
            <Link className="btn secondary" to={`/generate?prompt=${encodeURIComponent(`${d.category?.name || ''} ${label(d.screen_type)}`.trim())}&platform=${d.platform}&screen_type=${d.screen_type}`}>
              Generate a variation
            </Link>
            {d.can_manage && <button className="btn danger" onClick={remove}><Trash />Delete</button>}
          </div>
        </aside>
      </div>

      {similar.data?.data?.length > 0 && (
        <section>
          <h2 className="section-title">More like this</h2>
          <Wall designs={similar.data.data} compact />
        </section>
      )}

      {d.status === 'approved' && (
        <section style={{ marginTop: 32 }}>
          <h2 className="section-title">Discussion {d.comments_count > 0 && <span className="muted">({d.comments_count})</span>}</h2>
          <Comments slug={slug} onCountChange={(delta) => patch({ comments_count: d.comments_count + delta })} />
        </section>
      )}
    </div>
  )
}
