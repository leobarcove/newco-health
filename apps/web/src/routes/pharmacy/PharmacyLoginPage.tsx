import { useState } from 'react'
import { useNavigate } from 'react-router'
import { api, setToken } from '../../lib/api'

export function PharmacyLoginPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const navigate = useNavigate()

  async function submit() {
    setBusy(true)
    setError(null)
    try {
      const result = await api<{ token: string }>('/auth/pharmacy/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      })
      setToken(result.token)
      navigate('/pharmacy', { replace: true })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Please try again.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-5 p-6">
      <h1 className="text-2xl font-bold text-slate-900">Pharmacy counter</h1>
      <p className="text-base text-slate-600">Sign in to look up and dispense NewCo Health prescriptions.</p>

      <input
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="Counter email"
        className="min-h-13 rounded-xl border border-slate-300 px-4 text-base outline-none focus:border-emerald-600"
      />
      <input
        type="password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        placeholder="Password"
        className="min-h-13 rounded-xl border border-slate-300 px-4 text-base outline-none focus:border-emerald-600"
      />
      <button
        onClick={submit}
        disabled={busy || !email || !password}
        className="min-h-14 rounded-xl bg-emerald-600 text-lg font-semibold text-white disabled:opacity-50"
      >
        {busy ? 'Signing in…' : 'Sign in'}
      </button>

      {error && <p className="rounded-xl bg-red-50 p-3 text-base text-red-700">{error}</p>}
    </main>
  )
}
