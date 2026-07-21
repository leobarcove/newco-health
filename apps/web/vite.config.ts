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
      workbox: {
        // Keep the service worker minimal: app-shell cache + offline queue only
        // (dev plan §16 — resist cleverness here).
        globPatterns: ['**/*.{js,css,html,svg,woff2}'],
      },
    }),
  ],
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
})
