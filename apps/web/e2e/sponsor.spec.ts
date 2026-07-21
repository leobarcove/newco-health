import { expect, test } from '@playwright/test'

/**
 * Golden journey #4 (the diaspora wedge): sponsor registers → funds the care
 * wallet → invites a beneficiary → beneficiary accepts → sponsor sees them
 * active with care visibility.
 */
test('sponsor funds a wallet and links a beneficiary who accepts', async ({ browser }) => {
  const sponsorContext = await browser.newContext()
  const beneficiaryContext = await browser.newContext()
  const sponsor = await sponsorContext.newPage()
  const beneficiary = await beneficiaryContext.newPage()

  const email = `sponsor-${Date.now()}@e2e.local`

  // Register + land on the dashboard
  await sponsor.goto('/sponsor/login')
  await sponsor.getByRole('button', { name: 'Create account' }).click()
  await sponsor.getByPlaceholder('Your name').fill('Ngozi in Houston')
  await sponsor.getByPlaceholder('Email address').fill(email)
  await sponsor.getByPlaceholder(/Password/).fill('a-long-sponsor-pass')
  await sponsor.getByRole('button', { name: 'Create account' }).last().click()
  await expect(sponsor.getByRole('heading', { name: "Your family's care" })).toBeVisible()

  // Fund the wallet (fake gateway settles instantly)
  await sponsor.getByRole('button', { name: 'Top up' }).click()
  await expect(sponsor.getByText('₦10,000.00')).toBeVisible({ timeout: 10_000 })

  // Invite "Mum"
  await sponsor.getByPlaceholder(/Who are they to you/).fill('Mum')
  await sponsor.getByPlaceholder('Their phone number').fill('8077778888')
  await sponsor.getByRole('button', { name: 'Send invitation by SMS' }).click()
  await expect(sponsor.getByText('Awaiting accept')).toBeVisible({ timeout: 10_000 })

  // Beneficiary signs in on their own phone and accepts
  await beneficiary.goto('/login')
  await beneficiary.getByPlaceholder('801 234 5678').fill('8077778888')
  await beneficiary.getByRole('button', { name: 'Send my code' }).click()
  await beneficiary.getByPlaceholder('••••••').fill('000000')
  await beneficiary.getByRole('button', { name: 'Sign in' }).click()
  await expect(beneficiary.getByText(/wants to sponsor your healthcare/)).toBeVisible()
  await beneficiary.getByRole('button', { name: 'Accept' }).click()
  await expect(beneficiary.getByText(/wants to sponsor your healthcare/)).not.toBeVisible({ timeout: 10_000 })

  // Sponsor refreshes: beneficiary is active
  await sponsor.reload()
  await expect(sponsor.getByText('active', { exact: false }).first()).toBeVisible({ timeout: 10_000 })

  await Promise.all([sponsorContext.close(), beneficiaryContext.close()])
})
