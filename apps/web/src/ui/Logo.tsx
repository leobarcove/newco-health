/** Wordmark + heartbeat mark. Inline SVG — no asset bytes. */
export function Logo({ size = 'md', tone = 'dark' }: { size?: 'sm' | 'md' | 'lg'; tone?: 'dark' | 'light' }) {
  const box = size === 'lg' ? 'size-11' : size === 'md' ? 'size-9' : 'size-7'
  const text = size === 'lg' ? 'text-2xl' : size === 'md' ? 'text-lg' : 'text-base'

  return (
    <span className="inline-flex items-center gap-2.5">
      <span className={`${box} grid shrink-0 place-items-center rounded-xl bg-emerald-600 shadow-sm shadow-emerald-600/30`}>
        <svg viewBox="0 0 24 24" fill="none" className="size-[62%] text-white" aria-hidden="true">
          <path
            d="M3 12h4l2.5-6 4 12L16 12h5"
            stroke="currentColor"
            strokeWidth="2.4"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </span>
      <span className={`${text} font-bold tracking-tight ${tone === 'light' ? 'text-white' : 'text-slate-900'}`}>
        NewCo<span className="text-emerald-600"> Health</span>
      </span>
    </span>
  )
}
