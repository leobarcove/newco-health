import { expect, test } from '@playwright/test'

/**
 * Golden journey #3: doctor prescribes from the formulary mid-consult →
 * patient sees the prescription card with a pickup code → the pharmacy
 * counter looks the code up and dispenses it.
 */
test('prescription flows from consult to pharmacy dispensing', async ({ browser }) => {
  const patientContext = await browser.newContext()
  const doctorContext = await browser.newContext()
  const pharmacyContext = await browser.newContext()
  const patient = await patientContext.newPage()
  const doctor = await doctorContext.newPage()
  const pharmacy = await pharmacyContext.newPage()

  // Patient starts a consult (with first-time consent)
  await patient.goto('/login')
  await patient.getByPlaceholder('801 234 5678').fill('8055556666')
  await patient.getByRole('button', { name: 'Send my code' }).click()
  await patient.getByPlaceholder('••••••').fill('000000')
  await patient.getByRole('button', { name: 'Sign in' }).click()
  await patient.getByRole('link', { name: 'Talk to a doctor now' }).click()
  await patient.getByPlaceholder(/Fever and headache/).fill('Malaria symptoms — fever and chills')
  await patient.getByRole('button', { name: 'See a doctor' }).click()
  await patient.getByRole('button', { name: 'I agree — continue' }).click()

  // Doctor accepts and prescribes from the formulary
  await doctor.goto('/login')
  await doctor.getByPlaceholder('801 234 5678').fill('8000000009')
  await doctor.getByRole('button', { name: 'Send my code' }).click()
  await doctor.getByPlaceholder('••••••').fill('000000')
  await doctor.getByRole('button', { name: 'Sign in' }).click()
  await doctor.getByRole('button', { name: 'Accept' }).first().click()

  await doctor.getByRole('button', { name: 'Prescribe' }).click()
  await doctor.getByPlaceholder('Search the formulary…').fill('Artemether')
  await doctor.getByRole('button', { name: /Artemether/ }).click()
  await doctor.getByPlaceholder(/Dosage, e.g./).fill('4 tablets twice daily')
  await doctor.getByRole('button', { name: /Issue prescription/ }).click()

  // Patient sees the prescription card and its pickup code
  const codeLocator = patient.getByText(/^RX-[A-Z0-9]+$/)
  await expect(codeLocator).toBeVisible({ timeout: 15_000 })
  const pickupCode = (await codeLocator.textContent())!.trim()

  // Pharmacy signs in, looks it up, dispenses
  await pharmacy.goto('/pharmacy/login')
  await pharmacy.getByPlaceholder('Counter email').fill('pharmacy@e2e.local')
  await pharmacy.getByPlaceholder('Password').fill('pharmacypass123')
  await pharmacy.getByRole('button', { name: 'Sign in' }).click()
  await pharmacy.getByPlaceholder('RX-XXXXXXXX').fill(pickupCode)
  await pharmacy.getByRole('button', { name: 'Look up' }).click()
  await expect(pharmacy.getByText(/Artemether/)).toBeVisible()
  await pharmacy.getByRole('button', { name: 'Mark as dispensed' }).click()
  await expect(pharmacy.getByText('✓ Collected — nothing more to do')).toBeVisible()

  await Promise.all([patientContext.close(), doctorContext.close(), pharmacyContext.close()])
})
