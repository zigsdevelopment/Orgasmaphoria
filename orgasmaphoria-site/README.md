# Orgasmaphoria Website — Version 2

A responsive static website for Orgasmaphoria, built around the supplied purple, black, and rose-gold logo and the official Spotify artist profile.

## What is included

- Responsive desktop, tablet, and mobile layouts
- Background-removed transparent logo and optimized web versions
- Refined 18+ entrance notice remembered for 30 days
- Official Spotify artist profile and embedded live catalog
- Share button with Web Share / clipboard support
- Music, spoken-word, podcast, values, collaboration, and contact sections
- Electronic press kit with downloadable brand assets
- Licensing, media, guest feature, and event inquiry areas
- Newsletter form prepared for Formspree, Mailchimp, Brevo, or another provider
- Editable social links and podcast URL
- Privacy and terms starter templates
- Social sharing image, favicon, app icons, web manifest, sitemap, robots file, and 404 page
- Search-engine and structured-data starter metadata
- Keyboard navigation, reduced-motion support, semantic headings, and accessible labels

## Files to edit before launch

### `script.js`
At the top of the file, update:

- `podcastUrl`
- `businessEmail`
- `newsletterEndpoint`
- Instagram, TikTok, and YouTube links

The official Spotify artist link is already installed.

### `index.html`
Update the following once the live domain is known:

- The `MusicGroup` structured-data URL
- Any biography or wording your parents want changed
- The business email if you prefer editing it directly as well

### `press-kit.html`
Replace the placeholder email and add:

- Parent/artist names, if they want them public
- City or region
- A more specific origin story
- Manager, booking, licensing, or publicist contacts
- Approved photos or album artwork

### `privacy.html` and `terms.html`
These are starter templates, not legal advice. They must be updated to reflect the final mailing-list provider, analytics services, business location, and actual practices.

### `robots.txt` and `sitemap.xml`
Replace `https://YOUR-DOMAIN.example` with the final website address.

## Logo files

- `assets/orgasmaphoria-logo-transparent.png` — full-resolution transparent master
- `assets/orgasmaphoria-logo.webp` — optimized website version
- `assets/icon-192.png` and `assets/icon-512.png` — profile/app icons
- `assets/orgasmaphoria-og.jpg` — social sharing card

## Newsletter setup

The current form is intentionally in preview mode. To make it work:

1. Create a form or audience with Formspree, Mailchimp, Brevo, ConvertKit, or a similar provider.
2. Copy the provider's form endpoint.
3. Paste it into `newsletterEndpoint` near the top of `script.js`.
4. Test with an email address you control.
5. Update the privacy notice to name the provider.

## Opening locally

Double-click `index.html`, or run a local server from the folder:

```bash
python -m http.server 8000
```

Then open `http://localhost:8000`.

## Deployment

The site is static and works on GitHub Pages, Cloudflare Pages, Netlify, Bluehost, or most ordinary web hosts. See `HOSTING-GUIDE.md` for the recommended free testing setup.
