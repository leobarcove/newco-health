import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

// Low-bandwidth doctrine (docs/nigeria-telehealth-dev-plan.md §8):
// first-load JS budget ≤ 200 KB gzipped — CI fails the build beyond it.
export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      strategies: 'injectManifest',
      srcDir: 'src',
      filename: 'sw.ts',
      injectManifest: {
        globPatterns: ['**/*.{js,css,html,svg,woff2}'],
      },
      manifest: {
        name: 'NewCo Health',
        short_name: 'NewCo',
        description: 'Talk to a licensed Nigerian doctor — chat, voice or video.',
        theme_color: '#ffffff',
        background_color: '#ffffff',
        display: 'standalone',
        start_url: '/',
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
        ],
      },
      // Worker logic lives in src/sw.ts (precache + offline intake queue +
      // push) — injectManifest so we control the code, kept minimal.
      devOptions: {
        // SW active under `make web` too — offline intake and push are
        // testable locally, not just in production builds.
        enabled: true,
        type: 'module',
      },
    }),
  ],
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
  preview: {
    // The offline e2e journey runs against a production preview — the real
    // SW with a real precache (dev lazy-chunks break under offline).
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
})
