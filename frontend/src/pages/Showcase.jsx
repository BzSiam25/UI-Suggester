import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Pager } from '../components/DesignCard'
import { Empty } from '../components/Guards'
import { Download, External } from '../components/Icons'
import { label, timeAgo, useApi } from '../hooks'
import { DesignNote, downloadHtml, openHtml, Preview } from './Generate'

export default function Showcase() {
  const [page, setPage] = useState(1)
  const { data, loading } = useApi('/generations/public', { page }, [page])

  return (
    <div className="wrap">
      <div className="page-head">
        <div>
          <span className="eyebrow">Showcase</span>
          <h1>Community mockups</h1>
          <p>Generated UI ideas that people chose to share. Open one to preview it full size or download the HTML.</p>
        </div>
        <span className="spacer" />
        <Link to="/generate" className="btn">Generate your own</Link>
      </div>
      {loading && !data ? <div className="skeleton" style={{ height: 200 }} /> : data?.data.length === 0 ? (
        <Empty title="Nothing shared yet" action={<Link to="/generate" className="btn">Generate a mockup</Link>}>
          Generate a mockup and press Share to add it here.
        </Empty>
      ) : (
        <>
          <div className="history">
            {data.data.map((g) => (
              <Link key={g.id} to={`/mockups/${g.id}`} className="history-item">
                <strong>{g.prompt}</strong>
                <span className="row" style={{ gap: 6 }}>
                  <span className="pill">{label(g.platform)}</span><span className="pill">{label(g.screen_type)}</span><span className="pill">{label(g.style)}</span>
                </span>
                <span className="muted small">By {g.user?.name}, {timeAgo(g.created_at)}</span>
              </Link>
            ))}
          </div>
          <Pager meta={data.meta} onPage={setPage} />
        </>
      )}
    </div>
  )
}

export function MockupView() {
  const { id } = useParams()
  const { data, error } = useApi(`/generations/${id}`)
  if (error) return <div className="wrap" style={{ padding: '60px 0' }}><Empty title="Mockup not found" action={<Link className="btn" to="/showcase">Back to showcase</Link>}>It may be private or deleted.</Empty></div>
  if (!data) return <div className="wrap" style={{ padding: 40 }}><div className="skeleton" style={{ height: 600 }} /></div>
  const g = data.data

  return (
    <div className="wrap" style={{ paddingBottom: 40 }}>
      <div className="page-head">
        <div>
          <span className="eyebrow">Shared mockup</span>
          <h1>{g.prompt}</h1>
          <p>{label(g.platform)} {label(g.screen_type).toLowerCase()} screen in the {g.style} style, shared by {g.user ? <Link to={`/u/${g.user.username}`}>{g.user.name}</Link> : 'someone'}.</p>
        </div>
        <span className="spacer" />
        <div className="row">
          <button className="btn secondary" onClick={() => openHtml(g.html)}><External />Open full size</button>
          <button className="btn secondary" onClick={() => downloadHtml(g.html, `uispark-mockup-${g.id}.html`)}><Download />Download HTML</button>
          <Link className="btn" to={`/generate?prompt=${encodeURIComponent(g.prompt)}&platform=${g.platform}&screen_type=${g.screen_type}`}>Remix</Link>
        </div>
      </div>
      <DesignNote design={g.design} compact />
      <div className="viewer" style={{ marginTop: 12 }}><div className="viewer-stage"><Preview generation={g} /></div></div>
    </div>
  )
}
