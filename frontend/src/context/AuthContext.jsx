import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import { api, tokenStore } from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [ready, setReady] = useState(false)

  useEffect(() => {
    if (!tokenStore.get()) { setReady(true); return }
    api('/auth/me')
      .then((r) => setUser(r.data))
      .catch(() => tokenStore.set(null))
      .finally(() => setReady(true))
  }, [])

  const finish = (r) => { tokenStore.set(r.token); setUser(r.user); return r.user }

  const login = useCallback((email, password) => api('/auth/login', { method: 'POST', body: { email, password } }).then(finish), [])
  const register = useCallback((form) => api('/auth/register', { method: 'POST', body: form }).then(finish), [])
  const logout = useCallback(async () => {
    try { await api('/auth/logout', { method: 'POST' }) } catch { /* token may be gone */ }
    tokenStore.set(null)
    setUser(null)
  }, [])

  return (
    <AuthContext.Provider value={{ user, setUser, ready, login, register, logout, isAdmin: user?.role === 'admin' }}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => useContext(AuthContext)
