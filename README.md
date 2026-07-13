# Orgasmaphoria Multi-Page Member Portal Prototype

This package is a full multi-page artist and community website demonstration for Orgasmaphoria.

## What is included

### Public pages

- Home
- Spotify listening page
- Membership overview
- Member library preview
- Events and invitations
- Separate-purchase storefront
- Normal contact page
- Media and collaboration page
- Site guide
- Accessibility statement
- Privacy starter
- Terms starter
- 404 page

### Member pages

- Sign in and local account creation
- Dashboard
- Member directory
- Member profiles
- Direct-message demonstration
- Privacy settings
- Accessibility and appearance settings
- Saved library items
- Event RSVPs
- Local data export

### Staff pages

- Staff-only portal demonstration
- Local document upload through IndexedDB
- Content type, access, status, tags, and descriptions
- Content inventory
- Local contact-form inbox
- Test report count
- Staff upload guidance

### Example content

- Signals & Stories printable conversation card game
- Listening Salon host and activity guide
- Midnight Pages reflection workbook
- After Dark Listening Salon invitation
- Staff release and publishing checklist
- Three downloadable calendar invitations
- Four sample store listings
- Fictional member profiles and messages

## Demo accounts

Open `login.html` and choose one of the one-click accounts:

- **Morgan Rose:** Inner Circle member
- **Alex Rowan:** Velvet Patron
- **Studio Admin:** Staff portal access

You may also create a local test account. Do not use a real password. The account exists only in that browser.

## Important production limitations

This package uses localStorage and IndexedDB to make the prototype interactive without a server. That makes it useful for design review and testing, but it is not secure production infrastructure.

The following require a real backend:

- Verified accounts and password recovery
- Secure sessions and multi-device access
- Membership billing and renewals
- One-time payments and refunds
- Private document storage
- Server-enforced access permissions
- Real direct messages
- Moderation and staff access controls
- Contact-email delivery
- Purchase history and protected downloads
- Audit logs, backups, and recovery

**Never upload real private documents to GitHub Pages or any public repository.** Files inside a static site can be discovered even when the interface labels them “staff only.”

See:

- `docs/BACKEND-ROADMAP.md`
- `docs/CONTENT-ADMIN-GUIDE.md`
- `backend/schema.sql`
- `guide.html`

## Files to edit first

### `assets/js/data.js`

Update:

- Contact email and form endpoint
- Membership names and pricing
- Sample events
- Products
- Library items
- Member examples
- Spotify links if they change

### Every policy page

The privacy and terms pages are starter text only. They must reflect the actual business, jurisdiction, payment provider, data practices, refund rules, moderation, and legal obligations before launch.

### `robots.txt` and `sitemap.xml`

Replace `YOUR-DOMAIN.example` with the final public domain.

## Running locally

Opening `index.html` directly works for most features. A local server is more reliable:

```bash
python -m http.server 8080
```

Then visit:

```text
http://localhost:8080/
```

## Free static testing

For design testing, deploy the entire `orgasmaphoria-site` folder to GitHub Pages, Cloudflare Pages, Netlify, or another static host.

GitHub Pages is suitable for the public prototype and can live inside your Zigs Development project hub at:

```text
projects/orgasmaphoria/
```

The secure production member platform should use a backend and private storage rather than relying on static hosting alone.
