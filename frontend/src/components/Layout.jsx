import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { Logo, Menu, Upload } from './Icons'

export default function Layout() {
  const { user, logout, isAdmin } = useAuth()
  const [menuOpen, setMenuOpen] = useState(false)
  const [navOpen, setNavOpen] = useState(false)
  const location = useLocation()
  const navigate = useNavigate()
  const menuRef = useRef(null)

  useEffect(() => { setMenuOpen(false); setNavOpen(false) }, [location.pathname])
  useEffect(() => {
    const close = (e) => { if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false) }
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [])

  return (
    <>
      <header className="topbar">
        <div className="wrap topbar-inner">
          <button className="btn quiet icon-btn mobile-toggle" aria-label="Menu" onClick={() => setNavOpen((o) => !o)}><Menu /></button>
          <Link to="/" className="brand"><Logo />UISpark</Link>
          <nav className={`nav ${navOpen ? 'open' : ''}`} aria-label="Main">
            <NavLink to="/" end>Explore</NavLink>
            <NavLink to="/generate">Generate</NavLink>
            <NavLink to="/showcase">Showcase</NavLink>
            {isAdmin && <NavLink to="/admin">Admin</NavLink>}
          </nav>
          <div className="account" ref={menuRef}>
            {user ? (
              <>
                <Link to="/upload" className="btn secondary small hide-sm"><Upload />Upload</Link>
                <button className="avatar" aria-label="Account menu" aria-expanded={menuOpen} onClick={() => setMenuOpen((o) => !o)}>
                  {user.name.charAt(0).toUpperCase()}
                </button>
                {menuOpen && (
                  <div className="menu" role="menu">
                    <div className="menu-head"><strong>{user.name}</strong><div className="muted small">{user.email}</div></div>
                    <hr />
                    <Link to="/upload">Upload a design</Link>
                    <Link to="/me/bookmarks">Bookmarks</Link>
                    <Link to="/me/uploads">My uploads</Link>
                    <Link to="/generate">My mockups</Link>
                    <Link to="/me/settings">Settings</Link>
                    {isAdmin && <Link to="/admin">Admin dashboard</Link>}
                    <hr />
                    <button onClick={async () => { await logout(); navigate('/') }}>Log out</button>
                  </div>
                )}
              </>
            ) : (
              <>
                <Link to="/login" className="btn quiet small">Log in</Link>
                <Link to="/register" className="btn small">Sign up</Link>
              </>
            )}
          </div>
        </div>
      </header>
      <main>
        <Outlet />
      </main>
      <footer className="footer">
        <div className="wrap row">
          <span>UISpark: real UI screens and instant mockups for any product idea.</span>
          <span className="spacer" />
          <Link to="/upload">Share a design</Link>
        </div>
      </footer>
    </>
  )
}
