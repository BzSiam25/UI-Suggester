import React from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter, Link, Route, Routes } from 'react-router-dom'
import Layout from './components/Layout'
import { Empty, RequireAuth } from './components/Guards'
import { AuthProvider } from './context/AuthContext'
import { ToastProvider } from './context/ToastContext'
import Admin from './pages/admin/Admin'
import { Login, Register } from './pages/Auth'
import DesignDetail from './pages/DesignDetail'
import Explore from './pages/Explore'
import Generate from './pages/Generate'
import { Bookmarks, MyUploads } from './pages/Me'
import Profile, { ProfileSettings } from './pages/Profile'
import Showcase, { MockupView } from './pages/Showcase'
import Upload from './pages/Upload'
import './styles.css'

const NotFound = () => (
  <div className="wrap" style={{ padding: '60px 0' }}>
    <Empty title="Page not found" action={<Link className="btn" to="/">Go to explore</Link>}>The link may be broken or the page was moved.</Empty>
  </div>
)

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <ToastProvider>
          <Routes>
            <Route element={<Layout />}>
              <Route index element={<Explore />} />
              <Route path="designs/:slug" element={<DesignDetail />} />
              <Route path="generate" element={<Generate />} />
              <Route path="showcase" element={<Showcase />} />
              <Route path="mockups/:id" element={<MockupView />} />
              <Route path="login" element={<Login />} />
              <Route path="register" element={<Register />} />
              <Route path="upload" element={<RequireAuth><Upload /></RequireAuth>} />
              <Route path="me/bookmarks" element={<RequireAuth><Bookmarks /></RequireAuth>} />
              <Route path="me/uploads" element={<RequireAuth><MyUploads /></RequireAuth>} />
              <Route path="me/settings" element={<RequireAuth><ProfileSettings /></RequireAuth>} />
              <Route path="u/:username" element={<Profile />} />
              <Route path="admin/*" element={<RequireAuth admin><Admin /></RequireAuth>} />
              <Route path="*" element={<NotFound />} />
            </Route>
          </Routes>
        </ToastProvider>
      </AuthProvider>
    </BrowserRouter>
  </React.StrictMode>,
)
