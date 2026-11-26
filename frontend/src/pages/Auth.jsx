import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { Eye, EyeOff } from '../components/Icons'

function useAfterAuth() {
  const navigate = useNavigate()
  const location = useLocation()
  return () => navigate(location.state?.from || '/', { replace: true })
}

function AuthStory({ register = false }) {
  return (
    <aside className="auth-story">
      <Link to="/" className="brand auth-story-brand">UISpark<span className="dot">.</span></Link>
      <div className="auth-story-copy">
        <span className="auth-story-kicker"><span /> UI inspiration, made useful</span>
        <h2>{register ? 'A better starting point for your next big idea.' : 'Your saved inspiration is right where you left it.'}</h2>
        <p>{register
          ? 'Collect interface ideas, share what you make and turn a blank page into a first draft.'
          : 'Pick up where you left off: revisit saved screens, join the conversation and keep shaping your ideas.'}</p>
        <ul className="auth-benefits">
          <li><span>01</span><div><strong>Save what inspires you</strong><small>Build a library of useful references.</small></div></li>
          <li><span>02</span><div><strong>Make an idea your own</strong><small>Generate a mockup when you are ready.</small></div></li>
          <li><span>03</span><div><strong>Share with the community</strong><small>Get feedback from other makers.</small></div></li>
        </ul>
      </div>
      <div className="auth-art" aria-hidden="true">
        <div className="auth-art-window"><span /><span /><span /><i /><b /><em /></div>
        <div className="auth-art-card"><span>INSPIRATION BOARD</span><i /><i /><i /></div>
        <div className="auth-art-orbit" />
      </div>
      <span className="auth-story-foot">Explore. Create. Share.</span>
    </aside>
  )
}

function PasswordField({ id, label, value, onChange, autoComplete, visible, onToggle, error, hint }) {
  return (
    <label className="field auth-field" htmlFor={id}>
      <span className="auth-field-label">{label}{hint && <span className="hint">{hint}</span>}</span>
      <span className="auth-password-wrap">
        <input
          id={id}
          className="input auth-password-input"
          type={visible ? 'text' : 'password'}
          required
          autoComplete={autoComplete}
          value={value}
          onChange={onChange}
        />
        <button
          className="password-toggle"
          type="button"
          aria-label={visible ? 'Hide password' : 'Show password'}
          aria-pressed={visible}
          onClick={onToggle}
        >
          {visible ? <EyeOff /> : <Eye />}
          <span>{visible ? 'Hide' : 'Show'}</span>
        </button>
      </span>
      {error && <span className="error-text">{error}</span>}
    </label>
  )
}

function hasPasswordSymbol(password) {
  return /[^\p{L}\p{N}\s]/u.test(password)
}

function getPasswordStrength(password) {
  if (!password) return { score: 0, label: '', tone: '' }
  const longEnough = password.length >= 8
  const hasSymbol = hasPasswordSymbol(password)
  const varied = /\p{L}/u.test(password) && /\p{N}/u.test(password)
  if (longEnough && hasSymbol && password.length >= 12 && varied) return { score: 4, label: 'Strong', tone: 'strong' }
  if (longEnough && hasSymbol) return { score: 3, label: 'Good', tone: 'good' }
  if (password.length >= 6 || hasSymbol) return { score: 2, label: hasSymbol ? 'Add more characters' : 'Add a symbol', tone: 'fair' }
  return { score: 1, label: 'Too short', tone: 'weak' }
}

export function Login() {
  const { login } = useAuth()
  const done = useAfterAuth()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [showPassword, setShowPassword] = useState(false)

  async function submit(e) {
    e.preventDefault()
    setBusy(true); setError(null)
    try { await login(form.email, form.password); done() } catch (err) { setError(err.message) } finally { setBusy(false) }
  }

  return (
    <div className="auth auth-v2">
      <div className="auth-layout wrap">
        <AuthStory />
        <form className="panel stack auth-form" onSubmit={submit}>
          <div className="auth-form-head">
            <span className="eyebrow">Welcome back</span>
            <h1>Sign in to UISpark</h1>
            <p className="muted">Use your account details to continue.</p>
          </div>
          <div className="auth-form-body">
          {error && <div className="notice bad" role="alert">{error}</div>}
          <label className="field auth-field" htmlFor="login-email">
            <span className="auth-field-label">Email address</span>
            <input id="login-email" className="input" type="email" required autoComplete="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          </label>
          <PasswordField
            id="login-password"
            label="Password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            autoComplete="current-password"
            visible={showPassword}
            onToggle={() => setShowPassword((shown) => !shown)}
          />
          <button className="btn auth-submit" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
          <p className="auth-switch">New to UISpark? <Link to="/register">Create an account</Link></p>
          <div className="auth-secure-note"><span aria-hidden="true">✓</span> Your account and saved designs stay private.</div>
          </div>
        </form>
      </div>
    </div>
  )
}

export function Register() {
  const { register } = useAuth()
  const done = useAfterAuth()
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState({})
  const [busy, setBusy] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmation, setShowConfirmation] = useState(false)
  const set = (k) => (e) => setForm((current) => ({ ...current, [k]: e.target.value }))
  const strength = getPasswordStrength(form.password)
  const lengthOkay = form.password.length >= 8
  const symbolOkay = hasPasswordSymbol(form.password)

  async function submit(e) {
    e.preventDefault()
    const passwordErrors = []
    if (!lengthOkay) passwordErrors.push('Use at least 8 characters.')
    if (!symbolOkay) passwordErrors.push('Add at least one special character, such as ! or #.')
    if (passwordErrors.length) {
      setErrors({ password: passwordErrors })
      return
    }
    if (form.password !== form.password_confirmation) {
      setErrors({ password_confirmation: ['Passwords do not match.'] })
      return
    }

    setBusy(true); setErrors({})
    try { await register(form); done() } catch (err) { setErrors({ ...err.errors, _: err.message }) } finally { setBusy(false) }
  }
  const err = (k) => errors[k] && <span className="error-text">{errors[k][0]}</span>
  const hasFieldErrors = Object.keys(errors).some((key) => key !== '_')

  return (
    <div className="auth auth-v2">
      <div className="auth-layout wrap">
        <AuthStory register />
        <form className="panel stack auth-form" onSubmit={submit}>
          <div className="auth-form-head">
            <span className="eyebrow">Join the community</span>
            <h1>Create your account</h1>
            <p className="muted">Save inspiration and turn ideas into mockups.</p>
          </div>
          <div className="auth-form-body">
          {errors._ && !hasFieldErrors && <div className="notice bad" role="alert">{errors._}</div>}
          <label className="field auth-field" htmlFor="register-name">
            <span className="auth-field-label">Full name</span>
            <input id="register-name" className="input" required autoComplete="name" value={form.name} onChange={set('name')} />
            {err('name')}
          </label>
          <label className="field auth-field" htmlFor="register-email">
            <span className="auth-field-label">Email address</span>
            <input id="register-email" className="input" type="email" required autoComplete="email" value={form.email} onChange={set('email')} />
            {err('email')}
          </label>
          <PasswordField
            id="register-password"
            label="Create password"
            value={form.password}
            onChange={(e) => { set('password')(e); if (errors.password) setErrors((current) => ({ ...current, password: undefined })) }}
            autoComplete="new-password"
            visible={showPassword}
            onToggle={() => setShowPassword((shown) => !shown)}
          />
          <div className="password-strength" aria-live="polite" aria-label={strength.label ? `Password strength: ${strength.label}` : 'Password strength'}>
            <div className={`strength-meter ${strength.tone}`} role="meter" aria-valuemin="0" aria-valuemax="4" aria-valuenow={strength.score}>
              {[1, 2, 3, 4].map((step) => <span key={step} className={step <= strength.score ? 'filled' : ''} />)}
            </div>
            <span className={`strength-label ${strength.tone}`}>{strength.label || 'Password strength'}</span>
          </div>
          <ul className="password-rules" aria-label="Password requirements">
            <li className={lengthOkay ? 'passed' : ''}><span aria-hidden="true">{lengthOkay ? '✓' : '○'}</span> At least 8 characters</li>
            <li className={symbolOkay ? 'passed' : ''}><span aria-hidden="true">{symbolOkay ? '✓' : '○'}</span> One special character <span className="rule-example">( ! @ # $ )</span></li>
          </ul>
          {err('password')}
          <PasswordField
            id="register-password-confirmation"
            label="Confirm password"
            value={form.password_confirmation}
            onChange={(e) => { set('password_confirmation')(e); if (errors.password_confirmation) setErrors((current) => ({ ...current, password_confirmation: undefined })) }}
            autoComplete="new-password"
            visible={showConfirmation}
            onToggle={() => setShowConfirmation((shown) => !shown)}
            error={errors.password_confirmation?.[0]}
          />
          <button className="btn auth-submit" disabled={busy}>{busy ? 'Creating account…' : 'Create account'}</button>
          <p className="auth-switch">Already have an account? <Link to="/login">Sign in</Link></p>
          <div className="auth-secure-note"><span aria-hidden="true">✓</span> Free to join. Your designs remain yours.</div>
          </div>
        </form>
      </div>
    </div>
  )
}
