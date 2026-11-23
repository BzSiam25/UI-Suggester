import { Link } from 'react-router-dom'
import { label } from '../hooks'
import { Chat, Heart } from './Icons'

export function PaletteStrip({ palette }) {
  const colors = palette?.length ? palette : [{ hex: '#d9dde5', percent: 100 }]
  const total = colors.reduce((s, c) => s + Math.max(c.percent, 6), 0)
  return (
    <div className="strip" aria-hidden="true">
      {/* small accents get a minimum width so they stay visible */}
      {colors.map((c) => <span key={c.hex} style={{ background: c.hex, width: `${(Math.max(c.percent, 6) / total) * 100}%` }} />)}
    </div>
  )
}

export default function DesignCard({ design, showStatus = false }) {
  const ratio = design.image_width && design.image_height ? `${design.image_width} / ${design.image_height}` : undefined
  return (
    <Link to={`/designs/${design.slug}`} className={`shot ${design.platform === 'mobile' ? 'mobile' : ''}`}>
      <div className="shot-frame">
        <img src={design.image_url} alt={design.title} loading="lazy" style={{ aspectRatio: ratio }} />
        {showStatus && <span className={`status ${design.status}`}>{label(design.status)}</span>}
      </div>
      <PaletteStrip palette={design.palette} />
      <div className="shot-meta">
        <div>
          <h3>{design.title}</h3>
          <div className="muted">{label(design.screen_type)} in {design.category?.name}</div>
        </div>
        <div className="shot-stats">
          <span className={design.is_liked ? 'liked' : ''}><Heart filled={design.is_liked} />{design.likes_count}</span>
          {design.comments_count > 0 && <span><Chat />{design.comments_count}</span>}
        </div>
      </div>
    </Link>
  )
}

export function Wall({ designs, loading, showStatus, compact }) {
  if (loading && !designs?.length) {
    return (
      <div className={`wall ${compact ? 'compact' : ''}`}>
        {Array.from({ length: 8 }).map((_, i) => <div key={i} className="skeleton shot" style={{ height: i % 3 === 0 ? 420 : 240 }} />)}
      </div>
    )
  }
  return (
    <div className={`wall ${compact ? 'compact' : ''}`} style={{ opacity: loading ? 0.6 : 1 }}>
      {designs.map((d) => <DesignCard key={d.id} design={d} showStatus={showStatus} />)}
    </div>
  )
}

export function Pager({ meta, onPage }) {
  if (!meta || meta.last_page <= 1) return null
  return (
    <nav className="pager" aria-label="Pagination">
      <button className="btn secondary small" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>Previous</button>
      <span className="muted small">Page {meta.current_page} of {meta.last_page}</span>
      <button className="btn secondary small" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Next</button>
    </nav>
  )
}
