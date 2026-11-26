import { useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api/client'
import Avatar from '../components/Avatar'
import { Pager, Wall } from '../components/DesignCard'
import { Empty } from '../components/Guards'
import { External, Upload } from '../components/Icons'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { label, timeAgo, useApi } from '../hooks'
import '../profile.css'

const LINKS = [
  { key: 'github', label: 'GitHub', placeholder: 'https://github.com/you' },
  { key: 'linkedin', label: 'LinkedIn', placeholder: 'https://linkedin.com/in/you' },
  { key: 'dribbble', label: 'Dribbble', placeholder: 'https://dribbble.com/you' },
  { key: 'behance', label: 'Behance', placeholder: 'https://behance.net/you' },
  { key: 'x', label: 'X', placeholder: 'https://x.com/you' },
]

const host = (url) => { try { return new URL(url).host.replace(/^www\./, '') } catch { return url } }
const joined = (iso) => new Date(iso).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })

// ------------------------------------------------------------------ public page

export default function Profile() {
  const { username } = useParams()
  const toast = useToast()
  const profile = useApi(`/users/${username}`, null, [username])
  const [tab, setTab] = useState('designs')
  const [page, setPage] = useState(1)
  const list = useApi(profile.data ? `/users/${username}/${tab}` : null, { page }, [tab, page, username, !!profile.data])

  useEffect(() => { setPage(1) }, [tab, username])

  if (profile.error) {
    return (
      <div className="wrap" style={{ padding: '48px 0' }}>
        <Empty title="Profile not found" action={<Link className="btn" to="/">Go to explore</Link>}>
          This profile does not exist or its owner keeps it private.
        </Empty>
      </div>
    )
  }
  if (!profile.data) return <div className="wrap" style={{ padding: '40px 0' }}><div className="skeleton" style={{ height: 320 }} /></div>

  const { data: u, stats, is_owner: owner } = profile.data
  const hue = ((u.id || 7) * 47) % 360
  const links = LINKS.filter((l) => u.links?.[l.key])
  const copyLink = () => navigator.clipboard.writeText(window.location.href).then(() => toast('Profile link copied'))

  return (
    <div className="profile-page">
      <div className="profile-cover" style={{ '--hue': hue }} />
      <div className="wrap">
        <section className="profile-head">
          <Avatar user={u} size={112} className="profile-avatar" />
          <div className="profile-id">
            <h1>{u.name}</h1>
            <div className="profile-handle">@{u.username}{u.role === 'admin' && <span className="pill">Admin</span>}</div>
            {u.headline && <p className="profile-headline">{u.headline}</p>}
            <ul className="profile-meta">
              {u.location && <li>📍 {u.location}</li>}
              {u.website && <li><a href={u.website} target="_blank" rel="noreferrer noopener">🔗 {host(u.website)}</a></li>}
              <li>🗓 Joined {joined(u.created_at)}</li>
            </ul>
          </div>
          <div className="profile-actions">
            {owner && <Link className="btn" to="/me/settings">Edit profile</Link>}
            <button className="btn secondary" onClick={copyLink}>Share</button>
          </div>
        </section>

        {owner && !u.is_profile_public && (
          <div className="notice warn" style={{ marginBottom: 18 }}>Your profile is private. Only you can see this page. <Link to="/me/settings">Make it public</Link></div>
        )}

        <div className="profile-grid">
          <aside className="profile-side">
            <div className="panel profile-card">
              <h3>About</h3>
              <p className={u.bio ? '' : 'muted'}>{u.bio || (owner ? 'Add a short bio so people know what you work on.' : 'No bio yet.')}</p>
              {u.skills?.length > 0 && (
                <>
                  <h4>Skills</h4>
                  <div className="profile-skills">{u.skills.map((s) => <span key={s} className="pill">{s}</span>)}</div>
                </>
              )}
              {links.length > 0 && (
                <>
                  <h4>Elsewhere</h4>
                  <div className="profile-links">
                    {links.map((l) => (
                      <a key={l.key} href={u.links[l.key]} target="_blank" rel="noreferrer noopener" className="profile-link">
                        <span>{l.label}</span><External />
                      </a>
                    ))}
                  </div>
                </>
              )}
            </div>

            {owner && (
              <div className="panel profile-card">
                <h3>Only you see this</h3>
                <dl className="profile-private">
                  <div><dt>Pending uploads</dt><dd><Link to="/me/uploads">{stats.pending_designs}</Link></dd></div>
                  <div><dt>All generated projects</dt><dd><Link to="/generate">{stats.all_projects}</Link></dd></div>
                  <div><dt>Bookmarks</dt><dd><Link to="/me/bookmarks">{stats.bookmarks}</Link></dd></div>
                </dl>
              </div>
            )}
          </aside>

          <main className="profile-main">
            <div className="profile-stats">
              <div><strong>{stats.designs}</strong><span>Designs</span></div>
              <div><strong>{stats.likes_received}</strong><span>Likes received</span></div>
              <div><strong>{stats.projects}</strong><span>Shared projects</span></div>
              <div><strong>{stats.comments}</strong><span>Comments</span></div>
            </div>

            <div className="tabs" role="tablist">
              <button role="tab" className={tab === 'designs' ? 'active' : ''} aria-selected={tab === 'designs'} onClick={() => setTab('designs')}>Designs <span className="count">{stats.designs}</span></button>
              <button role="tab" className={tab === 'projects' ? 'active' : ''} aria-selected={tab === 'projects'} onClick={() => setTab('projects')}>Generated projects <span className="count">{stats.projects}</span></button>
            </div>

            {tab === 'designs' && (
              list.data?.data?.length || list.loading
                ? <><Wall designs={list.data?.data || []} loading={list.loading} compact /><Pager meta={list.data?.meta} onPage={setPage} /></>
                : <Empty title="No designs yet" action={owner && <Link className="btn" to="/upload"><Upload />Upload a design</Link>}>
                    {owner ? 'Share a screen you made and it shows up here once approved.' : `${u.name} has not shared any designs yet.`}
                  </Empty>
            )}

            {tab === 'projects' && (
              list.data?.data?.length || list.loading
                ? (
                  <>
                    <div className="profile-projects">
                      {(list.data?.data || []).map((g) => (
                        <Link key={g.id} to={`/mockups/${g.id}`} className="profile-project">
                          <div className="profile-project-swatch" style={{ background: g.design?.palette?.length ? `linear-gradient(135deg, ${g.design.palette.join(', ')})` : undefined }}>
                            <span>{(g.design?.brand || g.prompt).slice(0, 1).toUpperCase()}</span>
                          </div>
                          <div>
                            <strong>{g.design?.brand || g.prompt}</strong>
                            <p className="muted small clamp-2">{g.prompt}</p>
                            <span className="row" style={{ gap: 6, flexWrap: 'wrap' }}>
                              {g.design?.framework_name && <span className="pill">{g.design.framework_name}</span>}
                              <span className="pill">{label(g.platform)}</span>
                              <span className="pill">{(g.screens || []).length} screen{(g.screens || []).length === 1 ? '' : 's'}</span>
                            </span>
                            <span className="muted small">{timeAgo(g.created_at)}</span>
                          </div>
                        </Link>
                      ))}
                    </div>
                    <Pager meta={list.data?.meta} onPage={setPage} />
                  </>
                )
                : <Empty title="No shared projects" action={owner && <Link className="btn" to="/generate">Generate a project</Link>}>
                    {owner ? 'Generate UI with Qwen and press Share to show it here.' : `${u.name} has not shared any generated projects yet.`}
                  </Empty>
            )}
          </main>
        </div>
      </div>
    </div>
  )
}

// ------------------------------------------------------------------ settings

function SkillInput({ value, onChange }) {
  const [text, setText] = useState('')
  const add = () => {
    const s = text.trim().replace(/,$/, '')
    if (!s || value.length >= 12 || value.some((v) => v.toLowerCase() === s.toLowerCase())) { setText(''); return }
    onChange([...value, s.slice(0, 30)]); setText('')
  }
  return (
    <div className="skill-input">
      {value.map((s) => (
        <span key={s} className="pill">{s}<button type="button" aria-label={`Remove ${s}`} onClick={() => onChange(value.filter((v) => v !== s))}>×</button></span>
      ))}
      <input value={text} maxLength={30} placeholder={value.length >= 12 ? '12 skills max' : 'Type a skill and press Enter'} disabled={value.length >= 12}
        onChange={(e) => setText(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add() }
          if (e.key === 'Backspace' && !text && value.length) onChange(value.slice(0, -1))
        }}
        onBlur={add} />
    </div>
  )
}

export function ProfileSettings() {
  const { user, setUser } = useAuth()
  const toast = useToast()
  const fileRef = useRef(null)
  const initial = () => ({
    name: user.name || '', username: user.username || '', headline: user.headline || '', bio: user.bio || '',
    location: user.location || '', website: user.website || '', skills: user.skills || [],
    links: Object.fromEntries(LINKS.map((l) => [l.key, user.links?.[l.key] || ''])),
    is_profile_public: user.is_profile_public !== false,
  })
  const [form, setForm] = useState(initial)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [pw, setPw] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [pwErrors, setPwErrors] = useState({})
  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e?.target ? (e.target.type === 'checkbox' ? e.target.checked : e.target.value) : e }))
  const err = (bag, k) => bag[k] && <span className="error-text">{bag[k][0]}</span>
  const dirty = JSON.stringify(form) !== JSON.stringify(initial())

  async function save(e) {
    e.preventDefault()
    setSaving(true)
    try {
      const r = await api('/auth/profile', { method: 'PUT', body: { ...form, website: form.website || null } })
      setUser(r.data); setErrors({})
      toast('Profile saved')
    } catch (er) { setErrors(er.errors || {}); toast(er.message, 'bad') } finally { setSaving(false) }
  }

  async function uploadAvatar(file) {
    if (!file) return
    if (file.size > 3 * 1024 * 1024) { toast('Choose an image under 3 MB.', 'bad'); return }
    const body = new FormData()
    body.append('avatar', file)
    setUploading(true)
    try {
      const r = await api('/auth/avatar', { method: 'POST', body })
      setUser(r.data)
      toast('Photo updated')
    } catch (er) { toast(er.errors?.avatar?.[0] || er.message, 'bad') } finally { setUploading(false); if (fileRef.current) fileRef.current.value = '' }
  }
  async function removeAvatar() {
    const r = await api('/auth/avatar', { method: 'DELETE' })
    setUser(r.data)
    toast('Photo removed')
  }

  async function changePassword(e) {
    e.preventDefault()
    try {
      await api('/auth/password', { method: 'PUT', body: pw })
      setPw({ current_password: '', password: '', password_confirmation: '' }); setPwErrors({})
      toast('Password changed')
    } catch (er) { setPwErrors(er.errors || {}); toast(er.message, 'bad') }
  }

  return (
    <div className="wrap settings-page">
      <div className="page-head">
        <div><span className="eyebrow">Account</span><h1>Profile settings</h1><p>This is what people see on your public profile.</p></div>
        <Link className="btn secondary" to={`/u/${user.username}`}>View profile</Link>
      </div>

      <form className="panel settings-card" onSubmit={save}>
        <div className="settings-avatar">
          <Avatar user={user} size={88} />
          <div>
            <strong>Profile photo</strong>
            <p className="muted small">JPG, PNG or WebP, up to 3 MB. It is cropped to a square.</p>
            <div className="row" style={{ gap: 8 }}>
              <button type="button" className="btn secondary small" disabled={uploading} onClick={() => fileRef.current?.click()}>
                <Upload />{uploading ? 'Uploading…' : user.avatar_url ? 'Change photo' : 'Upload photo'}
              </button>
              {user.avatar_url && <button type="button" className="btn quiet small" onClick={removeAvatar}>Remove</button>}
            </div>
            <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" hidden onChange={(e) => uploadAvatar(e.target.files?.[0])} />
          </div>
        </div>

        <div className="settings-grid">
          <label className="field"><span>Full name</span><input className="input" value={form.name} onChange={set('name')} required maxLength={80} />{err(errors, 'name')}</label>
          <label className="field">
            <span>Username</span>
            <div className="input-prefix"><span>/u/</span><input className="input" value={form.username} maxLength={30} required
              onChange={(e) => set('username')(e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))} /></div>
            {err(errors, 'username')}
          </label>
          <label className="field full"><span>Headline <span className="hint">{120 - form.headline.length} left</span></span>
            <input className="input" value={form.headline} onChange={set('headline')} maxLength={120} placeholder="e.g. Software Engineer · Laravel & React" />{err(errors, 'headline')}
          </label>
          <label className="field full"><span>Bio <span className="hint">{280 - form.bio.length} left</span></span>
            <textarea className="textarea" rows={4} value={form.bio} onChange={set('bio')} maxLength={280} placeholder="What you build, what you are learning, what you are looking for." />{err(errors, 'bio')}
          </label>
          <label className="field"><span>Location</span><input className="input" value={form.location} onChange={set('location')} maxLength={80} placeholder="Dhaka, Bangladesh" />{err(errors, 'location')}</label>
          <label className="field"><span>Website</span><input className="input" type="url" value={form.website} onChange={set('website')} maxLength={190} placeholder="https://yoursite.com" />{err(errors, 'website')}</label>
          <div className="field full"><span>Skills <span className="hint">{form.skills.length}/12</span></span>
            <SkillInput value={form.skills} onChange={set('skills')} />{err(errors, 'skills')}
          </div>
        </div>

        <h3 className="settings-sub">Links</h3>
        <div className="settings-grid">
          {LINKS.map((l) => (
            <label key={l.key} className="field"><span>{l.label}</span>
              <input className="input" type="url" value={form.links[l.key]} placeholder={l.placeholder}
                onChange={(e) => setForm((f) => ({ ...f, links: { ...f.links, [l.key]: e.target.value } }))} />
              {err(errors, `links.${l.key}`)}
            </label>
          ))}
        </div>

        <label className="check settings-public">
          <input type="checkbox" checked={form.is_profile_public} onChange={set('is_profile_public')} />
          <span>Public profile <span className="muted small">Anyone can open /u/{form.username || 'username'}. Turn off to keep it to yourself.</span></span>
        </label>

        <div className="settings-actions">
          <button className="btn" disabled={saving || !dirty}>{saving ? 'Saving…' : 'Save profile'}</button>
          {dirty && <button type="button" className="btn quiet" onClick={() => { setForm(initial()); setErrors({}) }}>Discard changes</button>}
        </div>
      </form>

      <form className="panel settings-card" onSubmit={changePassword}>
        <h3 className="settings-sub" style={{ marginTop: 0 }}>Sign-in</h3>
        <label className="field"><span>Email</span><input className="input" value={user.email} disabled /></label>
        <div className="settings-grid three">
          <label className="field"><span>Current password</span><input className="input" type="password" value={pw.current_password} autoComplete="current-password"
            onChange={(e) => setPw({ ...pw, current_password: e.target.value })} required />{err(pwErrors, 'current_password')}</label>
          <label className="field"><span>New password</span><input className="input" type="password" value={pw.password} autoComplete="new-password"
            onChange={(e) => setPw({ ...pw, password: e.target.value })} required />{err(pwErrors, 'password')}</label>
          <label className="field"><span>Confirm new password</span><input className="input" type="password" value={pw.password_confirmation} autoComplete="new-password"
            onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} required /></label>
        </div>
        <p className="muted small">At least 8 characters with one special character, such as ! or #.</p>
        <div><button className="btn secondary">Change password</button></div>
      </form>
    </div>
  )
}
