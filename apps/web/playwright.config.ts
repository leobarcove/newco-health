import { defineConfig } from '@playwright/test'
import { resolve } from 'node:path'

const apiDir = resolve(import.meta.dirname, '../api')
const e2eDb = resolve(apiDir, 'database/e2e.sqlite')
const php = process.env.PHP_BIN ?? '/usr/local/opt/php@8.3/bin/php'

/** API env: isolated sqlite db, fixed OTP, no payment gate, no external services. */
const apiEnv = {
  ...process.env,
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: e2eDb,
  OTP_TEST_CODE: '000000',
  PAYMENTS_REQUIRED: 'false',
  BROADCAST_CONNECTION: 'log',
  QUEUE_CONNECTION: 'sync',
  CACHE_STORE: 'array',
  PAO_DISABLE: '1',
  APP_ENV: 'testing',
}

export default defineConfig({
  testDir: './e2e',
  timeout: 60_000,
  // One shared API + database + doctor across all journeys — parallel workers
  // would cross-contaminate consults (the doctor accepts .first() in queue).
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  use: {
    baseURL: 'http://localhost:5173',
  },
  webServer: [
    {
      command: `rm -f ${e2eDb} && touch ${e2eDb} && ${php} artisan migrate:fresh --force --seed --seeder=Database\\\\Seeders\\\\E2eSeeder && ${php} artisan serve --port=8000`,
      cwd: apiDir,
      env: apiEnv,
      port: 8000,
      reuseExistingServer: false,
      timeout: 60_000,
    },
    {
      command: 'npm run dev',
      port: 5173,
      reuseExistingServer: false,
      timeout: 60_000,
    },
    {
      // Production preview for the offline journey (real SW + precache).
      command: 'npm run build && npm run preview -- --port 4173 --strictPort',
      port: 4173,
      reuseExistingServer: false,
      timeout: 120_000,
    },
  ],
})
