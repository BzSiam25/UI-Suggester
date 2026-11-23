import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export function RequireAuth({ children, admin = false }) {
  const { user, ready, isAdmin } = useAuth()
  const location = useLocation()
  if (!ready) return <div className="wrap" style={{ padding: 40 }}><div className="skeleton" style={{ height: 300 }} /></div>
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  if (admin && !isAdmin) return <Navigate to="/" replace />
  return children
}

export function Empty({ title, children, action }) {
  return (
    <div className="empty">
      <h3>{title}</h3>
      {children && <p>{children}</p>}
      {action}
    </div>
  )
}
