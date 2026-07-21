import type { ReactNode } from 'react'
import { Link, useNavigate } from 'react-router'
import { setToken } from '../lib/api'
import { Logo } from './Logo'
import { btn } from './primitives'

const TAB_ICONS: Record<string, ReactNode> = {
  queue: (
    <>
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    </>
  ),
  agenda: (
    <>
      <rect x="3" y="4" width="18" height="18" rx="2" />
      <path d="M16 2v4M8 2v4M3 10h18" />
    </>
  ),
  availability: (
    <>
      <circle cx="12" cy="12" r="10" />
      <path d="M12 6v6l4 2" />
    </>
  ),
  earnings: (
    <>
      <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" />
      <path d="M3 5v14a2 2 0 0 0 2 2h16v-5" />
      <path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />
    </>
  ),
}

const TABS = [
  { key: 'queue', label: 'Queue', to: '/doctor' },
  { key: 'agenda', label: 'Agenda', to: '/doctor/agenda' },
  { key: 'availability', label: 'Schedule', to: '/doctor/availability' },
  { key: 'earnings', label: 'Earnings', to: '/doctor/earnings' },
] as const

/**
 * Doctor console shell (design plan §4.2). Responsive navigation:
 * desktop = pill nav in the top bar; mobile = thumb-zone bottom tab bar
 * (design plan §5 — primary actions in the bottom third, one-handed).
 */
export function ConsoleShell({
  active,
  children,
  wide = false,
  flush = false,
}: {
  active: 'queue' | 'agenda' | 'availability' | 'earnings' | 'consult'
  children: ReactNode
  wide?: boolean
  /** Full-height content (the consult workspace) — children own the space; tabs hidden. */
  flush?: boolean
}) {
  const navigate = useNavigate()

  return (
    <div className="flex min-h-dvh flex-col">
      <header className="sticky top-0 z-20 border-b border-slate-900/8 bg-white/90 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-5">
          <Link to="/doctor" aria-label="NewCo Health — doctor console">
            <Logo size="sm" />
          </Link>

          {/* Desktop navigation */}
          <nav className="hidden items-center gap-1 rounded-full bg-slate-900/5 p-1 md:flex" aria-label="Console">
            {TABS.map((tab) => (
              <Link
                key={tab.key}
                to={tab.to}
                aria-current={active === tab.key ? 'page' : undefined}
                className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                  active === tab.key ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                {tab.label}
              </Link>
            ))}
          </nav>

          <button
            onClick={() => {
              setToken(null)
              navigate('/login')
            }}
            className="text-sm font-medium text-slate-400 transition hover:text-slate-700"
          >
            Sign out
          </button>
        </div>
      </header>

      {flush ? (
        <main className="mx-auto flex w-full max-w-5xl min-h-0 flex-1 flex-col">{children}</main>
      ) : (
        <main className={`mx-auto w-full flex-1 px-5 pt-8 pb-28 md:pb-8 ${wide ? 'max-w-5xl' : 'max-w-3xl'}`}>
          {children}
        </main>
      )}

      {/* Mobile bottom tab bar — hidden in the consult workspace (composer owns the bottom) */}
      {!flush && (
        <nav
          aria-label="Console"
          className="fixed inset-x-0 bottom-0 z-20 border-t border-slate-900/8 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        >
          <div className="mx-auto grid max-w-md grid-cols-4">
            {TABS.map((tab) => {
              const current = active === tab.key
              return (
                <Link
                  key={tab.key}
                  to={tab.to}
                  aria-current={current ? 'page' : undefined}
                  className={`flex min-h-16 flex-col items-center justify-center gap-1 text-[11px] font-semibold transition ${
                    current ? 'text-emerald-700' : 'text-slate-400 hover:text-slate-600'
                  }`}
                >
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={current ? 2.2 : 1.8}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="size-5.5"
                    aria-hidden="true"
                  >
                    {TAB_ICONS[tab.key]}
                  </svg>
                  {tab.label}
                </Link>
              )
            })}
          </div>
        </nav>
      )}
    </div>
  )
}

/**
 * Patient/sponsor/pharmacy shell: phone-frame width, brand bar, generous
 * whitespace — one thing per screen (design plan §2.2).
 */
export function Shell({
  title,
  back,
  children,
  brand = false,
}: {
  title?: string
  back?: string
  children: ReactNode
  brand?: boolean
}) {
  return (
    <div className="mx-auto flex min-h-dvh w-full max-w-md flex-col">
      <header className="flex min-h-16 items-center gap-2 px-5 pt-2">
        {back && (
          <Link
            to={back}
            aria-label="Back"
            className="-ml-2 grid size-10 place-items-center rounded-full text-slate-500 transition hover:bg-slate-900/5 hover:text-slate-900"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" className="size-5">
              <path d="m15 18-6-6 6-6" />
            </svg>
          </Link>
        )}
        {brand ? <Logo size="sm" /> : title ? <h1 className="text-xl font-bold tracking-tight text-slate-900">{title}</h1> : null}
      </header>

      <main className="flex w-full flex-1 flex-col gap-5 px-5 pb-10 pt-2">{children}</main>
    </div>
  )
}

/** Full-bleed page title with optional supporting line. */
export function PageTitle({ children, sub }: { children: ReactNode; sub?: string }) {
  return (
    <div className="mb-1">
      <h1 className="text-[1.7rem] font-bold leading-tight tracking-tight text-slate-900">{children}</h1>
      {sub && <p className="mt-1 text-[15px] leading-relaxed text-slate-500">{sub}</p>}
    </div>
  )
}

export { btn }
