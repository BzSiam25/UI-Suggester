import '../profile.css'

/** A user's photo, or their initials on a colour picked from their id. */
export default function Avatar({ user, size = 32, className = '' }) {
  if (!user) return null
  const initials = (user.name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase()
  const style = { width: size, height: size, fontSize: Math.round(size * 0.38) }

  if (user.avatar_url) {
    return <img className={`u-avatar ${className}`} src={user.avatar_url} alt="" style={style} loading="lazy" />
  }
  const hue = ((user.id || 7) * 47) % 360
  return (
    <span className={`u-avatar ${className}`} aria-hidden="true" style={{ ...style, background: `hsl(${hue} 28% 34%)` }}>
      {initials}
    </span>
  )
}
