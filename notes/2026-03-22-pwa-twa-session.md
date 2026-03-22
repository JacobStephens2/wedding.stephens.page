# Session Notes — 2026-03-22

## PWA + TWA/APK for wedding.stephens.page

### PWA Setup

Converted the wedding site into a Progressive Web App:

- **`public/manifest.json`** — Web app manifest (name, colors, icons)
- **`public/service-worker.js`** — Caching strategy:
  - Cache-first for static assets (CSS, JS, images, fonts)
  - Network-first for HTML pages (falls back to cache offline)
  - Admin and API routes bypass the cache entirely
- **`public/app-icons/`** — 192x192, 512x512, and apple-touch-icon PNGs generated from the existing favicon.ico
- **`public/includes/header.php`** — Added manifest link, apple-touch-icon, `theme-color` meta, `mobile-web-app-capable` meta
- **`public/includes/footer.php`** — Service worker registration
- **`public/includes/theme_init.php`** and **`public/js/main.js`** — Theme toggle now syncs the `theme-color` meta tag for dark/light mode
- **`public/.htaccess`** — `Service-Worker-Allowed` header, no-cache for SW, manifest+json compression, APK MIME type

### Issues Encountered & Fixed

1. **Icons 404** — Apache has a global `Alias /icons/ "/usr/share/apache2/icons/"` that hijacks the `/icons/` path. Fixed by renaming to `/app-icons/`.
2. **Service worker `addAll` failure** — `addAll` is atomic; one 404 kills the whole install. Changed to individual `cache.add()` calls with `.catch()`.
3. **Deprecated meta tag** — `apple-mobile-web-app-capable` replaced with `mobile-web-app-capable`.
4. **`.htaccess` invalid `<Directory>` directive** — Can't use `<Directory>` in `.htaccess`; removed it.

### TWA / Android APK

Built a Trusted Web Activity wrapper using Bubblewrap:

- **Package ID**: `page.stephens.wedding`
- **APK**: `twa/app-release-signed.apk` (1.4MB)
- **AAB**: `twa/app-release-bundle.aab` (1.5MB, for Play Store)
- **Signing key**: `twa/android.keystore` (alias: `android`, password: `wedding2026`)
- **Digital Asset Links**: `public/.well-known/assetlinks.json` — SHA-256 fingerprint so Chrome hides the browser bar in the TWA
- **`.gitignore`** — `twa/` and `public/downloads/*.apk` excluded from repo

The `bubblewrap init` command is heavily interactive. Solved by writing a Node.js script (`generate.mjs`) that calls the `@bubblewrap/core` API directly, bypassing all CLI prompts.

The APK is **not publicly accessible** — it lives in `twa/` outside the web root. Use `scp` to download it.

### Commits

1. `361f610` — Add PWA support with manifest, service worker, and app icons
2. `7b898bb` — Add TWA/APK build and Digital Asset Links for Android app
