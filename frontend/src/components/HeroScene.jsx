/**
 * Hero illustration: a designer losing the fight with a UI layout.
 * The screen is stuck at 99%, blocks keep jumping out of place, sticky notes
 * pile up and the coffee is long gone — the problem UISpark is here to fix.
 * Pure SVG so it stays crisp, needs no asset, and animates (see .hs3-* in styles.css).
 */
const HAND = '"Segoe Print", "Comic Sans MS", "Bradley Hand", cursive'

export default function HeroScene() {
  return (
    <svg viewBox="0 0 420 360" role="img" aria-label="A stressed designer scratching their head while a broken UI layout is stuck at 99 percent">
      <defs>
        <linearGradient id="hs3-wall" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#f9f5e9" />
          <stop offset="1" stopColor="#efeedd" />
        </linearGradient>
        <linearGradient id="hs3-desk" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#e4dcc3" />
          <stop offset="1" stopColor="#d9cfb2" />
        </linearGradient>
        <pattern id="hs3-dots" width="16" height="16" patternUnits="userSpaceOnUse">
          <circle cx="2" cy="2" r="1" fill="#ddd7c2" />
        </pattern>
        <filter id="hs3-shadow" x="-20%" y="-20%" width="140%" height="160%">
          <feDropShadow dx="0" dy="5" stdDeviation="6" floodColor="#2e3323" floodOpacity=".14" />
        </filter>
        <clipPath id="hs3-screen"><rect x="104" y="156" width="146" height="98" rx="3" /></clipPath>
      </defs>

      {/* wall */}
      <rect width="420" height="360" fill="url(#hs3-wall)" />
      <rect width="420" height="270" fill="url(#hs3-dots)" opacity=".65" />
      <circle cx="318" cy="170" r="104" fill="#ebe6d0" opacity=".85" />

      {/* sticky notes */}
      <g fontFamily={HAND} fill="#2e3323">
        <g transform="rotate(-6 66 62)" filter="url(#hs3-shadow)">
          <rect x="34" y="36" width="66" height="54" fill="#f6dd8a" />
          <rect x="58" y="32" width="18" height="8" fill="#e8e1cb" opacity=".9" />
          <text x="40" y="56" fontSize="8.5">v12_final</text>
          <text x="40" y="68" fontSize="8.5">_FINAL</text>
          <text x="40" y="81" fontSize="8.5">(real).fig</text>
        </g>
        <g transform="rotate(5 140 52)" filter="url(#hs3-shadow)">
          <rect x="110" y="28" width="58" height="48" fill="#f3c3a8" />
          <text x="116" y="46" fontSize="8.5">make it</text>
          <text x="116" y="59" fontSize="10" fontWeight="700">POP!</text>
          <path d="M151 64l6-4m-6 4l6 4" stroke="#2e3323" strokeWidth="1.2" fill="none" />
        </g>
        <g transform="rotate(-3 78 118)" filter="url(#hs3-shadow)">
          <rect x="48" y="98" width="62" height="40" fill="#d3e2b3" />
          <text x="54" y="114" fontSize="8">fix spacing</text>
          <text x="54" y="127" fontSize="10" fontWeight="700">??</text>
        </g>
      </g>

      {/* clock with hands that will not slow down */}
      <g transform="translate(212 52)">
        <circle r="20" fill="#fffdf7" stroke="#2e3323" strokeWidth="2.5" />
        {[0, 90, 180, 270].map((a) => <rect key={a} x="-1" y="-17" width="2" height="4" fill="#2e3323" transform={`rotate(${a})`} />)}
        <rect className="hs3-hour" x="-1.4" y="-10" width="2.8" height="11" rx="1.4" fill="#2e3323" />
        <rect className="hs3-min" x="-1" y="-15" width="2" height="16" rx="1" fill="#c0573a" />
        <circle r="2.2" fill="#2e3323" />
      </g>

      {/* desk */}
      <rect y="268" width="420" height="92" fill="url(#hs3-desk)" />
      <rect y="266" width="420" height="5" fill="#c9bd9b" />

      {/* laptop with the broken layout */}
      <g filter="url(#hs3-shadow)">
        <rect x="96" y="148" width="162" height="114" rx="7" fill="#2e3323" />
        <rect x="104" y="156" width="146" height="98" rx="3" fill="#fffdf7" />
        <path d="M84 262h186l-10 9H94z" fill="#bfb393" />
      </g>
      <g clipPath="url(#hs3-screen)">
        <rect x="110" y="162" width="134" height="8" rx="2" fill="#ece5d0" />
        <rect className="hs3-jit hs3-j1" x="110" y="178" width="46" height="32" rx="3" fill="#dfe6c4" />
        <rect className="hs3-jit hs3-j2" x="164" y="174" width="96" height="16" rx="3" fill="#f2e2c0" transform="rotate(7 212 182)" />
        <rect className="hs3-jit hs3-j3" x="168" y="198" width="52" height="5" rx="2.5" fill="#cfc8b0" />
        <rect className="hs3-jit hs3-j4" x="172" y="208" width="88" height="5" rx="2.5" fill="#cfc8b0" />
        <rect x="120" y="216" width="40" height="12" rx="2" fill="none" stroke="#c0573a" strokeWidth="1.4" strokeDasharray="3 2.5" />
        <text x="125" y="225" fontSize="6.5" fill="#c0573a" fontFamily="ui-monospace, Menlo, monospace">?? px</text>
        {/* progress stuck at the end */}
        <rect x="110" y="238" width="104" height="6" rx="3" fill="#ece5d0" />
        <rect className="hs3-bar" x="110" y="238" width="102" height="6" rx="3" fill="#c2d36a" />
        <text x="218" y="244" fontSize="7" fontWeight="700" fill="#2e3323" fontFamily="system-ui, sans-serif">99%</text>
        <g transform="translate(240 226)">
          <circle r="5" fill="none" stroke="#ece5d0" strokeWidth="2" />
          <path className="hs3-spin" d="M0-5a5 5 0 0 1 5 5" fill="none" stroke="#2e3323" strokeWidth="2" strokeLinecap="round" />
        </g>
      </g>

      {/* error toast */}
      <g className="hs3-toast" filter="url(#hs3-shadow)">
        <rect x="118" y="120" width="124" height="24" rx="12" fill="#fffdf7" />
        <circle cx="132" cy="132" r="7" fill="#e07a5a" />
        <rect x="131" y="127.5" width="2" height="5" rx="1" fill="#fffdf7" />
        <circle cx="132" cy="135.6" r="1.1" fill="#fffdf7" />
        <text x="144" y="135" fontSize="8" fontWeight="600" fill="#2e3323" fontFamily="system-ui, sans-serif">Layout broke… again</text>
      </g>

      {/* the designer */}
      <g>
        {/* body */}
        <path d="M272 270v-30q0-26 32-30h32q32 4 32 30v30z" fill="#3d442c" />
        <path d="M306 210l14 14 14-14" fill="none" stroke="#2e3323" strokeWidth="2" />
        <rect x="312" y="194" width="16" height="18" fill="#cf9d74" />
        {/* arm reaching for the keyboard */}
        <path d="M286 236q-24 12-34 30" fill="none" stroke="#3d442c" strokeWidth="15" strokeLinecap="round" />
        <circle cx="250" cy="266" r="7" fill="#d8a980" />

        <g className="hs3-head">
          <circle cx="320" cy="164" r="33" fill="#d8a980" />
          <circle cx="288" cy="168" r="6" fill="#cf9d74" />
          {/* messy hair */}
          <path d="M287 160q-4-34 30-38 10-10 22-2 16 2 16 22 4 6 2 16-6-14-14-16 2 8-4 12-2-10-12-12-2 8-10 8 2-8-2-12-8 6-16 6 0 8-6 10z" fill="#2e3323" />
          <path d="M318 122q2-10 10-12M330 124q6-8 14-6" stroke="#2e3323" strokeWidth="3" fill="none" strokeLinecap="round" />
          {/* worried brows, tired eyes, wobbly mouth */}
          <path d="M302 160q6-6 13-3M326 157q7-3 13 3" stroke="#2e3323" strokeWidth="2.4" fill="none" strokeLinecap="round" />
          <circle cx="310" cy="168" r="2.6" fill="#2e3323" />
          <circle cx="331" cy="168" r="2.6" fill="#2e3323" />
          <path d="M305 175q5 3 10 0M326 175q5 3 10 0" stroke="#b9845f" strokeWidth="1.4" fill="none" strokeLinecap="round" />
          <path d="M310 186q3-3 6 0t6 0 6 0" stroke="#2e3323" strokeWidth="2" fill="none" strokeLinecap="round" />
          <path className="hs3-sweat" d="M347 150q4 7 0 10-4-3 0-10z" fill="#9cc6dc" />
        </g>
        {/* arm scratching the head */}
        <path d="M352 232q22-26 8-62" fill="none" stroke="#3d442c" strokeWidth="15" strokeLinecap="round" />
        <g className="hs3-scratch">
          <ellipse cx="356" cy="150" rx="9" ry="10" fill="#d8a980" />
          <path d="M349 145h8M349 150h9M350 155h7" stroke="#b9845f" strokeWidth="1.2" strokeLinecap="round" />
        </g>
      </g>

      {/* tangled thoughts */}
      <path className="hs3-scribble" d="M292 104c10-14 26-2 18 6s-20-6-6-14 26 2 20 10-16 4-10-4 22-10 26 0-6 12-14 6 4-16 16-12" fill="none" stroke="#7c8565" strokeWidth="2" strokeLinecap="round" />
      <text className="hs3-q hs3-q1" x="270" y="128" fontSize="18" fontWeight="800" fill="#c0573a" fontFamily="system-ui, sans-serif">?</text>
      <text className="hs3-q hs3-q2" x="372" y="118" fontSize="14" fontWeight="800" fill="#8ea67b" fontFamily="system-ui, sans-serif">?</text>

      {/* empty coffee cups */}
      <g>
        <path d="M372 238h22l-3 30h-16z" fill="#fffdf7" stroke="#2e3323" strokeWidth="2" strokeLinejoin="round" />
        <rect x="370" y="234" width="26" height="5" rx="2" fill="#2e3323" />
        <path d="M394 246q8 0 7 8t-8 6" fill="none" stroke="#2e3323" strokeWidth="2" />
        <path d="M398 250h18l-2 18h-14z" fill="#f1ead6" stroke="#2e3323" strokeWidth="2" strokeLinejoin="round" transform="rotate(14 407 259)" />
      </g>

      {/* crumpled attempts */}
      {[[176, 288, 1.1, -10], [200, 300, 0.85, 30], [266, 292, 1, 70], [362, 296, 0.8, 140]].map(([x, y, k, rot], i) => (
        <g key={i} transform={`translate(${x} ${y}) rotate(${rot}) scale(${k})`}>
          <g className="hs3-ball" style={{ '--d': `${i * 0.6}s` }}>
            <path d="M-9-3l4-6 6 1 5-2 3 6-1 5 2 4-5 4-5-1-4 2-5-3-1-5z" fill="#fbf8ef" stroke="#b9b199" strokeWidth="1.1" strokeLinejoin="round" />
            <path d="M-5-6l3 5 5-1M-1-1l-2 6M3 0l5 3M-7 1l4 0" fill="none" stroke="#cfc8b0" strokeWidth=".9" strokeLinecap="round" />
          </g>
        </g>
      ))}
    </svg>
  )
}
