import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

function useAfterAuth() {
  const navigate = useNavigate()
  const location = useLocation()
  return () => navigate(location.state?.from || '/', { replace: true })
}

export function Login() {
  const { login } = useAuth()
  const done = useAfterAuth()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  async function submit(e) {
    e.preventDefault()
    setBusy(true); setError(null)
    try { await login(form.email, form.password); done() } catch (err) { setError(err.message) } finally { setBusy(false) }
  }

  return (
    <div className="auth">
      <form className="panel stack" onSubmit={submit}>
        <div><span className="eyebrow">Welcome back</span><h1>Log in</h1><p className="muted">Save designs, join discussions and generate mockups.</p></div>
        {error && <div className="notice bad">{error}</div>}
        <label className="field"><span>Email</span><input className="input" type="email" required autoComplete="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
        <label className="field"><span>Password</span><input className="input" type="password" required autoComplete="current-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></label>
        <button className="btn" disabled={busy} style={{ height: 46 }}>{busy ? 'Logging in…' : 'Log in'}</button>
        <p className="muted small">New to UISpark? <Link to="/register">Create an account</Link></p>
      </form>
    </div>
  )
}

export function Register() {
  const { register } = useAuth()
  const done = useAfterAuth()
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState({})
  const [busy, setBusy] = useState(false)
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  async function submit(e) {
    e.preventDefault()
    setBusy(true); setErrors({})
    try { await register(form); done() } catch (err) { setErrors({ ...err.errors, _: err.message }) } finally { setBusy(false) }
  }
  const err = (k) => errors[k] && <span className="error-text">{errors[k][0]}</span>

  return (
    <div className="auth">
      <form className="panel stack" onSubmit={submit}>
        <div><span className="eyebrow">Join UISpark</span><h1>Create an account</h1><p className="muted">Free. Share screens, bookmark ideas and generate mockups.</p></div>
        {errors._ && !Object.keys(errors).some((k) => k !== '_') && <div className="notice bad">{errors._}</div>}
        <label className="field"><span>Name</span><input className="input" required autoComplete="name" value={form.name} onChange={set('name')} />{err('name')}</label>
        <label className="field"><span>Email</span><input className="input" type="email" required autoComplete="email" value={form.email} onChange={set('email')} />{err('email')}</label>
        <label className="field"><span>Password <span className="hint">at least 8 characters</span></span><input className="input" type="password" required minLength={8} autoComplete="new-password" value={form.password} onChange={set('password')} />{err('password')}</label>
        <label className="field"><span>Confirm password</span><input className="input" type="password" required autoComplete="new-password" value={form.password_confirmation} onChange={set('password_confirmation')} /></label>
        <button className="btn" disabled={busy} style={{ height: 46 }}>{busy ? 'Creating account…' : 'Create account'}</button>
        <p className="muted small">Already have an account? <Link to="/login">Log in</Link></p>
      </form>
    </div>
  )
}
