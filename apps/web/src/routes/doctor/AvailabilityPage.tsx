import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'

interface TemplateRow {
  weekday: number
  start_time: string
  end_time: string
  slot_minutes: number
}

const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']

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
    <main className="mx-auto flex min-h-dvh max-w-2xl flex-col gap-4 p-6">
      <header className="flex items-center gap-3">
        <Link to="/doctor/agenda" className="text-2xl text-slate-500" aria-label="Back">‹</Link>
        <h1 className="text-2xl font-bold text-slate-900">Weekly availability</h1>
      </header>
      <p className="text-base text-slate-600">Times are Lagos time. Patients can book up to 14 days ahead.</p>

      <ul className="flex flex-col gap-3">
        {rows.map((row, i) => (
          <li key={i} className="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3">
            <select
              value={row.weekday}
              onChange={(e) => update(i, { weekday: Number(e.target.value) })}
              className="min-h-11 rounded-lg border border-slate-300 px-2 text-base"
            >
              {WEEKDAYS.map((name, w) => (
                <option key={w} value={w + 1}>{name}</option>
              ))}
            </select>
            <input
              type="time" value={row.start_time}
              onChange={(e) => update(i, { start_time: e.target.value })}
              className="min-h-11 rounded-lg border border-slate-300 px-2 text-base"
            />
            <span className="text-slate-400">to</span>
            <input
              type="time" value={row.end_time}
              onChange={(e) => update(i, { end_time: e.target.value })}
              className="min-h-11 rounded-lg border border-slate-300 px-2 text-base"
            />
            <select
              value={row.slot_minutes}
              onChange={(e) => update(i, { slot_minutes: Number(e.target.value) })}
              className="min-h-11 rounded-lg border border-slate-300 px-2 text-base"
            >
              {[10, 15, 20, 30, 45, 60].map((m) => (
                <option key={m} value={m}>{m} min slots</option>
              ))}
            </select>
            <button
              onClick={() => { setRows(rows.filter((_, x) => x !== i)); setDirty(true) }}
              className="ml-auto min-h-11 rounded-lg px-3 text-base text-red-600"
              aria-label={`Remove ${WEEKDAYS[row.weekday - 1]} window`}
            >
              Remove
            </button>
          </li>
        ))}
      </ul>

      <button
        onClick={() => { setRows([...rows, { weekday: 1, start_time: '09:00', end_time: '12:00', slot_minutes: 20 }]); setDirty(true) }}
        className="min-h-12 rounded-xl border border-dashed border-slate-300 text-base text-slate-600"
      >
        + Add a window
      </button>

      <button
        onClick={() => save.mutate()}
        disabled={save.isPending || !dirty}
        className="min-h-14 rounded-xl bg-emerald-600 text-lg font-semibold text-white disabled:opacity-50"
      >
        {save.isPending ? 'Saving…' : dirty ? 'Save changes' : 'Saved'}
      </button>
    </main>
  )
}
