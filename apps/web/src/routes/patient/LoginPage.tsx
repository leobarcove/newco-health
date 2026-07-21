import { useState } from 'react'
import { useNavigate } from 'react-router'
import { api, setToken, type Me } from '../../lib/api'
import { Logo } from '../../ui/Logo'
import { btn, input } from '../../ui/primitives'

/** Phone → OTP sign-in. One thing per screen (design plan §2.2). */
export function LoginPage() {
  const [step, setStep] = useState<'phone' | 'code'>('phone')
  const [phone, setPhone] = useState('')
  const [code, setCode] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const navigate = useNavigate()

  const fullPhone = `+234${phone.replace(/^0/, '')}`

  async function requestCode() {
    setBusy(true)
    setError(null)
    try {
      await api('/auth/otp/request', { method: 'POST', body: JSON.stringify({ phone: fullPhone }) })
      setStep('code')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Please try again.')
    } finally {
      setBusy(false)
    }
  }

  async function verify() {
    setBusy(true)
    setError(null)
    try {
      const result = await api<{ token: string; user: Me }>('/auth/otp/verify', {
        method: 'POST',
        body: JSON.stringify({ phone: fullPhone, code }),
      })
      setToken(result.token)
      navigate(result.user.role === 'doctor' ? '/doctor' : '/', { replace: true })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Please try again.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="mx-auto flex min-h-dvh w-full max-w-md flex-col px-6">
      <div className="flex flex-1 flex-col justify-center gap-8 py-10">
        <div className="flex flex-col items-start gap-6">
          <Logo size="lg" />
          {step === 'phone' ? (
            <div>
              <h1 className="text-[2rem] font-bold leading-tight tracking-tight text-slate-900">
                A doctor,
                <br />
                wherever you are.
              </h1>
              <p className="mt-3 text-base leading-relaxed text-slate-500">
                Chat with a licensed Nigerian doctor in minutes. Enter your phone number and we'll text you a sign-in code.
              </p>
            </div>
          ) : (
            <div>
              <h1 className="text-[2rem] font-bold leading-tight tracking-tight text-slate-900">Check your SMS</h1>
              <p className="mt-3 text-base leading-relaxed text-slate-500">
                We sent a 6-digit code to <strong className="text-slate-800">{fullPhone}</strong>.
              </p>
            </div>
          )}
        </div>

        {step === 'phone' ? (
          <div className="flex flex-col gap-4">
            <div className="flex items-stretch gap-2">
              <span className="flex items-center rounded-2xl border border-slate-300/80 bg-white px-4 text-base font-medium text-slate-600">
                🇳🇬 +234
              </span>
              <input
                inputMode="tel"
                autoFocus
                value={phone}
                onChange={(e) => setPhone(e.target.value.replace(/[^0-9]/g, ''))}
                placeholder="801 234 5678"
                className={`${input} text-lg tracking-wide`}
              />
            </div>
            <button onClick={requestCode} disabled={busy || phone.replace(/^0/, '').length !== 10} className={btn.primary}>
              {busy ? 'Sending…' : 'Send my code'}
            </button>
          </div>
        ) : (
          <div className="flex flex-col gap-4">
            <input
              inputMode="numeric"
              autoFocus
              maxLength={6}
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/[^0-9]/g, ''))}
              placeholder="••••••"
              className={`${input} text-center text-2xl font-semibold tracking-[0.5em]`}
            />
            <button onClick={verify} disabled={busy || code.length !== 6} className={btn.primary}>
              {busy ? 'Checking…' : 'Sign in'}
            </button>
            <button onClick={() => setStep('phone')} className={btn.ghost}>
              Use a different number
            </button>
          </div>
        )}

        {error && <p className="rounded-2xl bg-red-50 p-4 text-[15px] text-red-800">{error}</p>}
      </div>

      <footer className="flex flex-col items-center gap-3 pb-8 text-center">
        {step === 'phone' && (
          <a href="/sponsor/login" className="text-[15px] font-medium text-emerald-700 underline-offset-4 hover:underline">
            Abroad and paying for family in Nigeria? →
          </a>
        )}
        <p className="text-xs text-slate-400">Licensed Nigerian doctors · NDPC-compliant · Fair, upfront prices</p>
      </footer>
    </div>
  )
}
