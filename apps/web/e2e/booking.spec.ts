import { expect, test } from '@playwright/test'

async function signInPatient(page: import('@playwright/test').Page, phone: string) {
  await page.goto('/login')
  await page.getByPlaceholder('801 234 5678').fill(phone)
  await page.getByRole('button', { name: 'Send my code' }).click()
  await page.getByPlaceholder('••••••').fill('000000')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page.getByRole('heading', { name: 'How can we help today?' })).toBeVisible()
}

/** Golden journey #2: book an appointment → see it upcoming → cancel it. */
test('patient books an appointment and cancels it', async ({ page }) => {
  await signInPatient(page, '8033334444')

  await page.getByRole('link', { name: 'Book an appointment' }).click()
  await page.getByRole('button', { name: /Dr Amara Okafor/ }).click()
  await page.getByRole('button', { name: 'Tomorrow' }).click()

  // Pick the first offered time (full-day availability guarantees one).
  const timeButtons = page.locator('section', { hasText: '3. Pick a time' }).getByRole('button')
  await timeButtons.first().click()

  await page.getByPlaceholder(/Follow-up on my blood pressure/).fill('Routine check-up')
  await page.getByRole('button', { name: /^Confirm — / }).click()

  // Lands on appointments with the booking upcoming.
  await expect(page.getByRole('heading', { name: 'Your appointments' })).toBeVisible()
  await expect(page.getByText('Dr Amara Okafor').first()).toBeVisible()

  // Cancel (tomorrow ⇒ outside the 2h cutoff) — moves to past as cancelled.
  await page.getByRole('button', { name: 'Cancel' }).first().click()
  await expect(page.getByText('cancelled', { exact: false }).first()).toBeVisible({ timeout: 10_000 })
})
