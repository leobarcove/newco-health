/**
 * k6 load test — dev plan §11: queue + consult flow under 500 concurrent users.
 *
 * Run against STAGING only (never production, never local sqlite):
 *   BASE_URL=https://staging.example.ng OTP_TEST_CODE=000000 k6 run infra/k6/consult-load.js
 *
 * Requires OTP_TEST_CODE set on the target (non-production only) and
 * PAYMENTS_REQUIRED=false, or pre-funded org membership for the test phones.
 */
import http from 'k6/http'
import { check, sleep } from 'k6'

const BASE = `${__ENV.BASE_URL || 'http://localhost:8000'}/api`

export const options = {
  stages: [
    { duration: '1m', target: 100 },
    { duration: '3m', target: 500 },
    { duration: '1m', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<800'], // dev plan §16: API responsiveness under load
  },
}

export default function () {
  const phone = `+23480${String(10000000 + (__VU % 899999)).padStart(8, '0')}`

  const verify = http.post(`${BASE}/auth/otp/verify`, JSON.stringify({ phone, code: __ENV.OTP_TEST_CODE || '000000' }), {
    headers: { 'Content-Type': 'application/json' },
  })
  check(verify, { 'signed in': (r) => r.status === 200 })
  const token = verify.json('token')
  const auth = { headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` } }

  // Consent (idempotent) + start a consult
  http.post(`${BASE}/consents`, JSON.stringify({ kind: 'telemedicine_terms', granted: true }), auth)
  const consult = http.post(`${BASE}/consults`, JSON.stringify({ complaint: 'Load test — fever and headache' }), auth)
  check(consult, { 'consult created': (r) => r.status === 201 })
  const consultId = consult.json('id')

  // Poll like the SPA does
  for (let i = 0; i < 3; i++) {
    const messages = http.get(`${BASE}/consults/${consultId}/messages`, auth)
    check(messages, { 'messages readable': (r) => r.status === 200 })
    sleep(3)
  }
}
