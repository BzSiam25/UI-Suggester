const base = { fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', viewBox: '0 0 24 24', 'aria-hidden': true }

export const Search = (p) => <svg {...base} {...p}><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
export const Heart = ({ filled, ...p }) => <svg {...base} fill={filled ? 'currentColor' : 'none'} {...p}><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 1 0-7.8 7.8L12 21.2l8.8-8.8a5.5 5.5 0 0 0 0-7.8z" /></svg>
export const Bookmark = ({ filled, ...p }) => <svg {...base} fill={filled ? 'currentColor' : 'none'} {...p}><path d="M19 21 12 16 5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" /></svg>
export const Eye = (p) => <svg {...base} {...p}><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="3" /></svg>
export const Chat = (p) => <svg {...base} {...p}><path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z" /></svg>
export const Upload = (p) => <svg {...base} {...p}><path d="M12 16V4M7 9l5-5 5 5M4 20h16" /></svg>
export const Spark = (p) => <svg {...base} {...p}><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6" /></svg>
export const External = (p) => <svg {...base} {...p}><path d="M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5" /></svg>
export const Download = (p) => <svg {...base} {...p}><path d="M12 4v12M7 11l5 5 5-5M4 20h16" /></svg>
export const Menu = (p) => <svg {...base} {...p}><path d="M4 7h16M4 12h16M4 17h16" /></svg>
export const Trash = (p) => <svg {...base} {...p}><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg>
export const Arrow = (p) => <svg {...base} {...p}><path d="M4 12h16M14 6l6 6-6 6" /></svg>
export const Globe = (p) => <svg {...base} {...p}><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" /></svg>

export const Logo = (p) => (
  <svg viewBox="0 0 32 32" aria-hidden="true" {...p}>
    <rect x="3" y="3" width="11" height="26" rx="3" fill="#171A26" />
    <rect x="17" y="3" width="12" height="12" rx="3" fill="#2B4BEE" />
    <rect x="17" y="18" width="12" height="11" rx="3" fill="#F2B84B" />
  </svg>
)
