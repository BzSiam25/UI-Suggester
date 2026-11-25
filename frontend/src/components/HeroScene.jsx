/**
 * Hero illustration: a designer stuck on a UI layout.
 * Pure SVG so it stays crisp, needs no asset, and animates (see .hs-* in styles.css).
 */
export default function HeroScene() {
  return (
    <svg viewBox="0 0 420 360" role="img" aria-label="A designer with their head in their hands, stuck on a messy UI layout">
      <defs>
        <linearGradient id="hs-wall" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#faf6ea" />
          <stop offset="1" stopColor="#f1ebd9" />
        </linearGradient>
      </defs>

      <rect width="420" height="360" fill="url(#hs-wall)" />
      <circle cx="298" cy="152" r="120" fill="#ece5d0" opacity="0.8" />

      {/* desk */}
      <rect y="280" width="420" height="80" fill="#e6dfc8" />
      <rect y="280" width="420" height="3" fill="#cfc5a8" />

      {/* laptop with a layout that refuses to line up */}
      <g>
        <rect x="40" y="168" width="138" height="100" rx="4" fill="#fffdf7" stroke="#2e3323" strokeWidth="3" />
        <rect x="50" y="178" width="118" height="10" fill="#e6dfc8" />
        <rect className="hs-b1" x="50" y="196" width="46" height="28" fill="#dfe6c4" />
        <rect className="hs-b2" x="104" y="194" width="56" height="22" fill="#f2e6c8" />
        <rect className="hs-b3" x="50" y="232" width="70" height="16" fill="#e4dbc2" />
        <rect className="hs-b4" x="126" y="228" width="34" height="24" fill="none" stroke="#a8703a" strokeWidth="2" strokeDasharray="4 3" />
        <rect x="32" y="268" width="154" height="12" rx="2" fill="#c8bb96" />
      </g>

      {/* the designer */}
      <g>
        <path d="M258 280V222q0-26 30-32h28q30 6 30 32v58z" fill="#3d442c" />
        <rect x="292" y="172" width="16" height="22" fill="#d8a980" />
        <g className="hs-head">
          <circle cx="300" cy="140" r="40" fill="#d8a980" />
          <path d="M260 140q0-44 40-44t40 44q-10-24-40-24t-40 24z" fill="#2e3323" />
          <path d="M280 132q7-4 14-1M306 131q7-3 14 1" stroke="#2e3323" strokeWidth="2.5" fill="none" strokeLinecap="round" />
          <path d="M282 148q6 5 12 0M306 148q6 5 12 0" stroke="#2e3323" strokeWidth="2.5" fill="none" strokeLinecap="round" />
          <path d="M292 164q8-5 16 0" stroke="#2e3323" strokeWidth="2.5" fill="none" strokeLinecap="round" />
        </g>
        <path className="hs-arm-l" d="M270 216q-12-38 6-66" stroke="#4a5237" strokeWidth="17" fill="none" strokeLinecap="round" />
        <path className="hs-arm-r" d="M330 216q12-38-6-66" stroke="#4a5237" strokeWidth="17" fill="none" strokeLinecap="round" />
        <circle cx="277" cy="146" r="9.5" fill="#d8a980" />
        <circle cx="323" cy="146" r="9.5" fill="#d8a980" />
      </g>

      {/* desk clutter */}
      <g>
        <circle cx="172" cy="268" r="10" fill="#fffdf7" stroke="#dcd3b8" strokeWidth="2" />
        <path d="M166 264l12 8M170 273l4-10" stroke="#dcd3b8" strokeWidth="1.5" />
        <rect x="210" y="252" width="24" height="28" rx="3" fill="#fffdf7" stroke="#2e3323" strokeWidth="2.5" />
        <path d="M234 258h6a6 6 0 0 1 0 12h-6" fill="none" stroke="#2e3323" strokeWidth="2.5" />
        <g className="hs-steam" stroke="#b9ad8c" strokeWidth="2" fill="none" strokeLinecap="round">
          <path d="M218 246c-4-5 4-8 0-13" />
          <path d="M226 246c-4-5 4-8 0-13" />
        </g>
      </g>

      {/* the frustration */}
      <g className="hs-card">
        <rect x="330" y="66" width="54" height="40" rx="3" fill="#fffdf7" stroke="#2e3323" strokeWidth="2.5" />
        <rect x="338" y="74" width="22" height="8" fill="#dfe6c4" />
        <rect x="338" y="88" width="38" height="6" fill="#e4dbc2" />
      </g>
      <g className="hs-alert">
        <circle cx="216" cy="104" r="15" fill="#c2d36a" />
        <rect x="213" y="96" width="6" height="10" rx="3" fill="#2e3323" />
        <circle cx="216" cy="112" r="3" fill="#2e3323" />
      </g>
      <path className="hs-scribble" d="M118 104q9-13 18 0t18 0 18 0 18 0" stroke="#a8703a" strokeWidth="3" fill="none" strokeLinecap="round" />
    </svg>
  )
}
