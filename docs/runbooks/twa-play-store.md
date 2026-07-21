# Runbook: TWA Play-Store wrapper (Phase 2)

The Android Play-Store listing is a **Trusted Web Activity** — a thin shell that
opens the live PWA in Chrome. One lightweight Play review for the shell, then
every product update remains a normal web deploy (dev plan §2.1).

## Prerequisites (founder-gated)
- Production PWA live on its final domain (HTTPS, passing Lighthouse PWA checks)
- Google Play Console developer account ($25 one-off)

## Steps
1. `npm i -g @bubblewrap/cli && bubblewrap init --manifest https://<domain>/manifest.webmanifest`
   — accept defaults; package id e.g. `ng.newcohealth.app`; Bubblewrap generates the Android project + signing key. **Back the keystore up in the password manager — losing it means losing the listing.**
2. Digital Asset Links: Bubblewrap prints the SHA-256 fingerprint. Put it in
   `apps/web/public/.well-known/assetlinks.json` (template below) and deploy —
   without this the app shows a browser bar instead of full-screen.
3. `bubblewrap build` → upload the `.aab` to Play Console (closed testing first).
4. Store listing: use the design-plan §8 Phase-2 assets; screenshots from a
   real mid-range device.

## assetlinks.json template
```json
[{
  "relation": ["delegate_permission/common.handle_all_urls"],
  "target": {
    "namespace": "android_app",
    "package_name": "ng.newcohealth.app",
    "sha256_cert_fingerprints": ["REPLACE_WITH_KEYSTORE_SHA256"]
  }
}]
```

## Rules
- The shell is versioned separately and should change ~never. Re-review is only
  triggered by shell changes, not web deploys.
- Keep the same keystore + package id forever.
