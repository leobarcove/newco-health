import { useState } from 'react'
import { useNavigate } from 'react-router'
import { api, setToken } from '../../lib/api'
import { Logo } from '../../ui/Logo'
import { btn, input } from '../../ui/primitives'

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
    <div className="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center gap-7 px-6 py-10">
      <div className="flex flex-col items-start gap-6">
        <Logo size="lg" />
        <div>
          <h1 className="text-[2rem] font-bold leading-tight tracking-tight text-slate-900">
            Care for your family
            <br />
            back home.
          </h1>
          <p className="mt-3 text-base leading-relaxed text-slate-500">
            Pay from anywhere in the world. Your family sees a licensed Nigerian doctor — you see that they're okay.
          </p>
        </div>
      </div>

      <div className="flex rounded-2xl bg-slate-900/5 p-1">
        {(['login', 'register'] as const).map((m) => (
          <button
            key={m}
            onClick={() => setMode(m)}
            className={`min-h-11 flex-1 rounded-xl text-[15px] font-semibold transition ${
              mode === m ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'
            }`}
          >
            {m === 'login' ? 'Sign in' : 'Create account'}
          </button>
        ))}
      </div>

      <div className="flex flex-col gap-3">
        {mode === 'register' && (
          <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Your name" className={input} />
        )}
        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Email address" className={input} />
        <input
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder={mode === 'register' ? 'Password (10+ characters)' : 'Password'}
          className={input}
        />
        <button onClick={submit} disabled={busy || !email || !password || (mode === 'register' && !name)} className={btn.primary}>
          {busy ? 'One moment…' : mode === 'login' ? 'Sign in' : 'Create account'}
        </button>
      </div>

      {error && <p className="rounded-2xl bg-red-50 p-4 text-[15px] text-red-800">{error}</p>}

      <p className="text-center text-xs text-slate-400">Pay in £ $ € — your family never handles the money.</p>
    </div>
  )
}
