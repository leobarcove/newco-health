import type { ReactNode } from 'react'

/* Shared class recipes — one visual language everywhere (design plan §3.2). */

export const btn = {
  primary:
    'inline-flex min-h-13 items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-6 text-base font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 active:scale-[0.99] disabled:pointer-events-none disabled:opacity-45',
  secondary:
    'inline-flex min-h-13 items-center justify-center gap-2 rounded-2xl border-[1.5px] border-emerald-600/70 bg-white px-6 text-base font-semibold text-emerald-700 transition hover:bg-emerald-50 active:scale-[0.99] disabled:pointer-events-none disabled:opacity-45',
  ghost:
    'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-base font-medium text-slate-600 transition hover:bg-slate-900/5 hover:text-slate-900',
  danger:
    'inline-flex min-h-11 items-center justify-center rounded-xl border border-red-200 bg-white px-4 text-sm font-semibold text-red-700 transition hover:bg-red-50',
}

export const input =
  'min-h-13 w-full rounded-2xl border border-slate-300/80 bg-white px-4 text-base text-slate-900 shadow-xs outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15'

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div className={`rounded-3xl border border-slate-900/8 bg-white p-5 shadow-[0_1px_2px_rgb(16_35_29/0.04),0_8px_24px_-12px_rgb(16_35_29/0.10)] ${className}`}>
      {children}
    </div>
  )
}

export function Badge({ children, tone = 'neutral' }: { children: ReactNode; tone?: 'success' | 'warning' | 'danger' | 'neutral' | 'brand' }) {
  const tones = {
    success: 'bg-emerald-100 text-emerald-800',
    warning: 'bg-amber-100 text-amber-800',
    danger: 'bg-red-100 text-red-700',
    neutral: 'bg-slate-100 text-slate-600',
    brand: 'bg-emerald-600 text-white',
  }

  return (
    <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold tracking-wide ${tones[tone]}`}>
      {children}
    </span>
  )
}

const AVATAR_HUES = ['bg-emerald-600', 'bg-teal-600', 'bg-cyan-700', 'bg-lime-700', 'bg-green-700']

/** Initials avatar — deterministic hue per name, no image bytes. */
export function Avatar({ name, size = 'md' }: { name: string; size?: 'sm' | 'md' | 'lg' }) {
  const initials = name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]!.toUpperCase())
    .join('') || '?'
  const hue = AVATAR_HUES[(name.charCodeAt(0) || 0) % AVATAR_HUES.length]
  const box = size === 'lg' ? 'size-14 text-lg' : size === 'md' ? 'size-11 text-base' : 'size-9 text-sm'

  return (
    <span className={`${box} ${hue} grid shrink-0 place-items-center rounded-full font-semibold text-white`}>
      {initials}
    </span>
  )
}

const ICONS: Record<string, ReactNode> = {
  queue: (
    <>
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    </>
  ),
  calendar: (
    <>
      <rect x="3" y="4" width="18" height="18" rx="2" />
      <path d="M16 2v4M8 2v4M3 10h18" />
    </>
  ),
  chat: <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" />,
  pill: (
    <>
      <path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z" />
      <path d="m8.5 8.5 7 7" />
    </>
  ),
  wallet: (
    <>
      <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" />
      <path d="M3 5v14a2 2 0 0 0 2 2h16v-5" />
      <path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />
    </>
  ),
}

/**
 * Designed empty state — every screen earns one (six-states doctrine,
 * design plan §3.3). Calm, explains itself, offers the next action.
 */
export function EmptyState({
  icon,
  title,
  hint,
  action,
}: {
  icon: keyof typeof ICONS
  title: string
  hint?: string
  action?: ReactNode
}) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-slate-300/80 bg-white/60 px-6 py-12 text-center">
      <span className="grid size-16 place-items-center rounded-2xl bg-emerald-600/10">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="size-8 text-emerald-700">
          {ICONS[icon]}
        </svg>
      </span>
      <p className="text-base font-semibold text-slate-900">{title}</p>
      {hint && <p className="max-w-xs text-sm leading-relaxed text-slate-500">{hint}</p>}
      {action}
    </div>
  )
}
