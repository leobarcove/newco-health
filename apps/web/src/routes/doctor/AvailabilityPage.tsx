import { useEffect, useState, type ReactNode } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { ConsoleShell, PageTitle } from '../../ui/shells'
import { Card } from '../../ui/primitives'

interface TemplateRow {
  weekday: number
  start_time: string
  end_time: string
  slot_minutes: number
}

const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']

const control =
  'min-h-12 w-full rounded-xl border border-slate-300/80 bg-white px-3.5 text-[15px] text-slate-800 outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15'

/** Native selects restyled: our own chevron, pinned inside the box. */
function Select({
  value,
  onChange,
  children,
  label,
}: {
  value: number
  onChange: (value: number) => void
  children: ReactNode
  label: string
}) {
  return (
    <span className="relative block w-full">
      <select
        value={value}
        onChange={(e) => onChange(Number(e.target.value))}
        aria-label={label}
        className={`${control} appearance-none pr-10 font-medium`}
      >
        {children}
      </select>
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        className="pointer-events-none absolute right-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400"
        aria-hidden="true"
      >
        <path d="m6 9 6 6 6-6" />
      </svg>
    </span>
  )
}

/** Weekly availability editor — the whole week is edited and saved as one unit. */
export function AvailabilityPage() {
  const [rows, setRows] = useState<TemplateRow[]>([])
  const [dirty, setDirty] = useState(false)

  const { data } = useQuery({
    queryKey: ['availability'],
    queryFn: () => api<TemplateRow[]>('/doctor/availability'),
  })

  useEffect(() => {
    if (data && !dirty) setRows(data.map(({ weekday, start_time, end_time, slot_minutes }) => ({ weekday, start_time, end_time, slot_minutes })))
  }, [data, dirty])

  const save = useMutation({
    mutationFn: () => api('/doctor/availability', { method: 'PUT', body: JSON.stringify({ templates: rows }) }),
    onSuccess: () => setDirty(false),
  })

  function update(index: number, patch: Partial<TemplateRow>) {
    setRows(rows.map((row, i) => (i === index ? { ...row, ...patch } : row)))
    setDirty(true)
  }

  return (
    <ConsoleShell active="availability">
      <div className="mb-6">
        <PageTitle sub="Times are Lagos time. Patients can book up to 14 days ahead — keep this current.">
          Weekly availability
        </PageTitle>
      </div>

      <div className="space-y-3">
        {rows.map((row, i) => (
          <Card key={i} className="p-4">
            {/* Day + remove — one aligned header row */}
            <div className="mb-3 flex items-center gap-2">
              <div className="flex-1 md:max-w-52">
                <Select value={row.weekday} onChange={(weekday) => update(i, { weekday })} label="Day of the week">
                  {WEEKDAYS.map((name, w) => (
                    <option key={w} value={w + 1}>{name}</option>
                  ))}
                </Select>
              </div>
              <button
                onClick={() => { setRows(rows.filter((_, x) => x !== i)); setDirty(true) }}
                aria-label={`Remove ${WEEKDAYS[row.weekday - 1]} window`}
                className="grid size-12 shrink-0 place-items-center rounded-xl text-slate-400 transition hover:bg-red-50 hover:text-red-600"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="size-4.5" aria-hidden="true">
                  <path d="M18 6 6 18M6 6l12 12" />
                </svg>
              </button>
            </div>

            {/* Times + slot length — aligned grid, no ragged wrapping */}
            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 md:grid-cols-[1fr_auto_1fr_1fr]">
              <input
                type="time"
                value={row.start_time}
                onChange={(e) => update(i, { start_time: e.target.value })}
                aria-label="Start time"
                className={`${control} text-center tabular-nums`}
              />
              <span className="px-1 text-sm font-medium text-slate-400">to</span>
              <input
                type="time"
                value={row.end_time}
                onChange={(e) => update(i, { end_time: e.target.value })}
                aria-label="End time"
                className={`${control} text-center tabular-nums`}
              />
              <div className="col-span-3 md:col-span-1">
                <Select value={row.slot_minutes} onChange={(slot_minutes) => update(i, { slot_minutes })} label="Slot length">
                  {[10, 15, 20, 30, 45, 60].map((m) => (
                    <option key={m} value={m}>{m}-minute slots</option>
                  ))}
                </Select>
              </div>
            </div>
          </Card>
        ))}
      </div>

      <button
        onClick={() => { setRows([...rows, { weekday: 1, start_time: '09:00', end_time: '12:00', slot_minutes: 20 }]); setDirty(true) }}
        className="mt-3 flex min-h-13 w-full items-center justify-center gap-2 rounded-3xl border-2 border-dashed border-slate-300 text-base font-medium text-slate-500 transition hover:border-emerald-500 hover:text-emerald-700"
      >
        <span className="text-lg leading-none" aria-hidden="true">+</span> Add a window
      </button>

      <div className="sticky bottom-20 mt-6 md:bottom-4">
        <button
          onClick={() => save.mutate()}
          disabled={save.isPending || !dirty}
          className="min-h-13 w-full rounded-2xl bg-emerald-600 text-base font-semibold text-white shadow-lg shadow-emerald-600/30 transition hover:bg-emerald-700 disabled:opacity-45 disabled:shadow-none"
        >
          {save.isPending ? 'Saving…' : dirty ? 'Save changes' : 'All changes saved'}
        </button>
      </div>
    </ConsoleShell>
  )
}
