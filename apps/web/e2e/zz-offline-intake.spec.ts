import { expect, test } from '@playwright/test'

/**
 * Golden journey #5 — the low-bandwidth promise itself (business plan §6
 * rule 3): an intake submitted with no signal is queued by the service
 * worker, the patient sees a calm "saved" state, and the request replays
 * once the connection returns.
 */
// Production preview build: real SW, real precache, no Vite dev client
// (whose websocket reconnect crashes lazy routes when offline).
test.use({ baseURL: 'http://localhost:4173' })

test('intake submitted offline queues and replays when the connection returns', async ({ browser }) => {
  const context = await browser.newContext()
  const page = await context.newPage()

  // Sign in and wait for the dev service worker to take control.
  await page.goto('/login')
  await page.getByPlaceholder('801 234 5678').fill('8066667777')
  await page.getByRole('button', { name: 'Send my code' }).click()
  await page.getByPlaceholder('••••••').fill('000000')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page.getByRole('heading', { name: /How can we help/ })).toBeVisible()

  // Ensure the SW actually CONTROLS this page (registration alone races the
  // first document) — reload after ready, then verify the controller exists.
  await page.evaluate(() => navigator.serviceWorker.ready.then(() => undefined))
  await page.reload()
  await expect
    .poll(() => page.evaluate(() => navigator.serviceWorker.controller !== null), { timeout: 10_000 })
    .toBe(true)

  // Consent while still online (consent is not offline-queued — only intake is).
  await page.evaluate(async () => {
    await fetch('/api/consents', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${localStorage.getItem('newco.token')}`,
      },
      body: JSON.stringify({ kind: 'telemedicine_terms', granted: true }),
    })
  })

  // Fill the intake, then lose the network before submitting.
  await page.getByRole('link', { name: 'Talk to a doctor now' }).click()
  await page.getByPlaceholder(/Fever and headache/).fill('Offline test — fever since yesterday')
  await context.setOffline(true)
  await page.getByRole('button', { name: 'See a doctor' }).click()

  // The designed offline state — never an error, never blame (design plan §2.1).
  await expect(page.getByText("Saved — you're offline right now")).toBeVisible({ timeout: 15_000 })

  // Connection returns; a reload restarts the SW, which replays the queue.
  await context.setOffline(false)

  let replayed = false
  for (let attempt = 0; attempt < 8 && !replayed; attempt++) {
    await page.goto('/')
    await expect(page.getByRole('heading', { name: /How can we help/ })).toBeVisible({ timeout: 10_000 })
    replayed = await page
      .getByRole('link', { name: 'Continue your consult' })
      .isVisible({ timeout: 3_000 })
      .catch(() => false)
    if (!replayed) await page.waitForTimeout(2_000)
  }
  expect(replayed, 'queued intake should replay and create the consult').toBe(true)

  await context.close()
})
