import { useState } from 'react'
import { useNavigate } from 'react-router'
import { api, setToken } from '../../lib/api'

/** Diaspora sponsor sign-in/sign-up — email based, unlike the patient OTP flow. */
export function SponsorLoginPage() {
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const navigate = useNavigate()

  async function submit() {
    setBusy(true)
    setError(null)
    try {
      const path = mode === 'login' ? '/auth/sponsor/login' : '/auth/sponsor/register'
      const body = mode === 'login' ? { email, password } : { name, email, password }
      const result = await api<{ token: string }>(path, { method: 'POST', body: JSON.stringify(body) })
      setToken(result.token)
      navigate('/sponsor', { replace: true })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Please try again.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-5 p-6">
      <h1 className="text-2xl font-bold text-slate-900">Care for your family back home</h1>
      <p className="text-base text-slate-600">
        Pay from anywhere in the world. Your family sees a licensed Nigerian doctor — you see that they're okay.
      </p>

      <div className="flex rounded-xl bg-slate-100 p-1">
        {(['login', 'register'] as const).map((m) => (
          <button
            key={m}
            onClick={() => setMode(m)}
            className={`min-h-11 flex-1 rounded-lg text-base font-medium ${
              mode === m ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'
            }`}
          >
            {m === 'login' ? 'Sign in' : 'Create account'}
          </button>
        ))}
      </div>

      {mode === 'register' && (
        <input
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="Your name"
          className="min-h-13 rounded-xl border border-slate-300 px-4 text-base outline-none focus:border-emerald-600"
        />
      )}
      <input
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="Email address"
        className="min-h-13 rounded-xl border border-slate-300 px-4 text-base outline-none focus:border-emerald-600"
      />
      <input
        type="password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        placeholder={mode === 'register' ? 'Password (10+ characters)' : 'Password'}
        className="min-h-13 rounded-xl border border-slate-300 px-4 text-base outline-none focus:border-emerald-600"
      />

      <button
        onClick={submit}
        disabled={busy || !email || !password || (mode === 'register' && !name)}
        className="min-h-14 rounded-xl bg-emerald-600 text-lg font-semibold text-white disabled:opacity-50"
      >
        {busy ? 'One moment…' : mode === 'login' ? 'Sign in' : 'Create account'}
      </button>

      {error && <p className="rounded-xl bg-red-50 p-3 text-base text-red-700">{error}</p>}
    </main>
  )
}
