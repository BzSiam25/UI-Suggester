import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { Pager } from '../components/DesignCard'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { timeAgo, useApi } from '../hooks'

function CommentForm({ slug, parentId, onDone, autoFocus, placeholder = 'Share what works or what you would change…' }) {
  const [body, setBody] = useState('')
  const [busy, setBusy] = useState(false)
  const toast = useToast()

  async function submit(e) {
    e.preventDefault()
    if (!body.trim()) return
    setBusy(true)
    try {
      const r = await api(`/designs/${slug}/comments`, { method: 'POST', body: { body, parent_id: parentId } })
      setBody('')
      onDone(r.data)
    } catch (err) { toast(err.message, 'bad') } finally { setBusy(false) }
  }

  return (
    <form onSubmit={submit} className="stack" style={{ gap: 8, marginBottom: 12 }}>
      <textarea className="textarea" rows={parentId ? 2 : 3} maxLength={1000} value={body} autoFocus={autoFocus}
        onChange={(e) => setBody(e.target.value)} placeholder={placeholder} aria-label="Comment" />
      <div className="row"><button className="btn small" disabled={busy || !body.trim()}>{parentId ? 'Post reply' : 'Post comment'}</button></div>
    </form>
  )
}

function CommentItem({ c, slug, onDelete, onReply }) {
  const { user } = useAuth()
  const [replying, setReplying] = useState(false)
  return (
    <div className="comment">
      <span className="avatar">{c.user.name.charAt(0)}</span>
      <div className="comment-body">
        <header><strong>{c.user.name}</strong><span className="muted small">{timeAgo(c.created_at)}</span></header>
        <p>{c.body}</p>
        <div className="row" style={{ gap: 14 }}>
          {user && !c.parent_id && <button className="link-btn" onClick={() => setReplying((r) => !r)}>Reply</button>}
          {c.can_delete && <button className="link-btn" onClick={() => onDelete(c)}>Delete</button>}
        </div>
        {replying && <div style={{ marginTop: 10 }}><CommentForm slug={slug} parentId={c.id} autoFocus placeholder={`Reply to ${c.user.name}`} onDone={(r) => { setReplying(false); onReply(c.id, r) }} /></div>}
        {c.replies?.length > 0 && (
          <div className="replies">
            {c.replies.map((r) => <CommentItem key={r.id} c={r} slug={slug} onDelete={onDelete} onReply={onReply} />)}
          </div>
        )}
      </div>
    </div>
  )
}

export default function Comments({ slug, onCountChange }) {
  const { user } = useAuth()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const { data, setData } = useApi(`/designs/${slug}/comments`, { page }, [page, user?.id])
  const list = data?.data || []

  const addTop = (c) => { setData((d) => ({ ...d, data: [c, ...d.data] })); onCountChange(1) }
  const addReply = (parentId, r) => {
    setData((d) => ({ ...d, data: d.data.map((c) => (c.id === parentId ? { ...c, replies: [...c.replies, r] } : c)) }))
    onCountChange(1)
  }
  async function remove(c) {
    if (!confirm('Delete this comment?')) return
    await api(`/comments/${c.id}`, { method: 'DELETE' })
    const removed = 1 + (c.replies?.length || 0)
    setData((d) => ({
      ...d,
      data: d.data.filter((x) => x.id !== c.id).map((x) => ({ ...x, replies: x.replies.filter((r) => r.id !== c.id) })),
    }))
    onCountChange(-removed)
    toast('Comment deleted')
  }

  return (
    <div className="comments">
      {user ? <CommentForm slug={slug} onDone={addTop} /> : <p className="muted" style={{ marginBottom: 12 }}><Link to="/login">Log in</Link> to join the discussion.</p>}
      {list.length === 0 && data && <p className="muted">No comments yet. Be the first to share feedback.</p>}
      {list.map((c) => <CommentItem key={c.id} c={c} slug={slug} onDelete={remove} onReply={addReply} />)}
      <Pager meta={data?.meta} onPage={setPage} />
    </div>
  )
}
