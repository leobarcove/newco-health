import { expect, test } from '@playwright/test'

/**
 * Golden journey #1 (dev plan §11): patient registers → consents → intake →
 * queue; doctor accepts → both exchange messages → doctor concludes.
 * Runs against the real API (sqlite) with the fixed OTP test code.
 */
test('patient and doctor complete a chat consult end-to-end', async ({ browser }) => {
  const patientContext = await browser.newContext()
  const doctorContext = await browser.newContext()
  const patient = await patientContext.newPage()
  const doctor = await doctorContext.newPage()

  // — Patient signs in by phone + fixed OTP
  await patient.goto('/login')
  await patient.getByPlaceholder('801 234 5678').fill('8011112222')
  await patient.getByRole('button', { name: 'Send my code' }).click()
  await patient.getByPlaceholder('••••••').fill('000000')
  await patient.getByRole('button', { name: 'Sign in' }).click()
  await expect(patient.getByRole('heading', { name: /How can we help/ })).toBeVisible()

  // — Intake with first-time consent
  await patient.getByRole('link', { name: 'Talk to a doctor now' }).click()
  await patient.getByPlaceholder(/Fever and headache/).fill('Fever and body aches since Monday')
  await patient.getByRole('button', { name: 'See a doctor' }).click()
  await patient.getByRole('button', { name: 'I agree — continue' }).click()

  // — Queued
  await expect(patient.getByText(/queue/i).first()).toBeVisible({ timeout: 15_000 })

  // — Doctor signs in and accepts from the queue
  await doctor.goto('/login')
  await doctor.getByPlaceholder('801 234 5678').fill('8000000009')
  await doctor.getByRole('button', { name: 'Send my code' }).click()
  await doctor.getByPlaceholder('••••••').fill('000000')
  await doctor.getByRole('button', { name: 'Sign in' }).click()
  await expect(doctor.getByRole('heading', { name: 'Waiting patients' })).toBeVisible()
  await doctor.getByRole('button', { name: 'Accept' }).first().click()

  // — Doctor is in the consult workspace; sends a message
  await doctor.getByPlaceholder('Type your message…').fill('Hello, any vomiting or diarrhoea?')
  await doctor.getByRole('button', { name: 'Send' }).click()

  // — Patient sees the doctor's message and replies
  await expect(patient.getByText('any vomiting or diarrhoea?')).toBeVisible({ timeout: 15_000 })
  await patient.getByPlaceholder('Type your message…').fill('No vomiting, just headaches.')
  await patient.getByRole('button', { name: 'Send' }).click()
  await expect(doctor.getByText('No vomiting, just headaches.')).toBeVisible({ timeout: 15_000 })

  // — Doctor concludes; patient sees the 72h follow-up state
  await doctor.getByRole('button', { name: 'End consult' }).click()
  await expect(patient.getByText(/72 hours/).first()).toBeVisible({ timeout: 15_000 })

  await patientContext.close()
  await doctorContext.close()
})
