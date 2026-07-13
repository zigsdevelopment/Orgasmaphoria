# Orgasmaphoria Published Website

This folder is the cleaned public release of the Orgasmaphoria website. It is designed to be published as the official artist site rather than presented as a prototype.

## Public pages included

- `index.html` — homepage
- `music.html` — Spotify listening page
- `community.html` — membership vision and community standards
- `events.html` — official event announcements
- `store.html` — official digital store landing page
- `about.html` — artist and brand story
- `contact.html` — production contact form
- `accessibility.html` — accessibility statement
- `privacy.html` — privacy notice for the current public website
- `terms.html` — public-site terms
- `thanks.html` — contact confirmation
- `404.html` — not-found page

The public electronic press kit, public site guide, demo accounts, fake members, local messaging, local staff uploads, sample events, sample products, sample documents, sample invitations, and browser-only checkout have been removed.

## Contact form

The contact form is configured for Netlify Forms:

```html
<form name="contact" method="POST" action="thanks.html" data-netlify="true" netlify-honeypot="bot-field">
```

For the form to deliver messages:

1. Deploy the entire `orgasmaphoria-site` folder to Netlify.
2. Open the site dashboard in Netlify.
3. Confirm that the `contact` form appears under Forms.
4. Configure form-notification recipients for the parents’ real business email.
5. Submit one real test message and verify that it arrives before announcing the contact page.

Do not claim that a message was delivered unless the host confirms a successful submission. Keep the honeypot field and use the host’s spam controls. Add rate limiting or stronger anti-abuse protection if the form receives spam.

If the site is hosted only on GitHub Pages, the Netlify form will not process submissions. In that case, either host this site on Netlify and link to it from the Zigs Development GitHub Pages hub, or replace the form with another verified form endpoint.

## Recommended publishing structure

Use the Zigs Development GitHub Pages site as the portfolio hub and host Orgasmaphoria as its own production project. The hub can link to the final Orgasmaphoria URL.

Example:

```text
Zigs Development hub
└── Project card: Orgasmaphoria
    └── Links to the production Netlify site or custom domain
```

A custom domain can later point to the production host without changing the site layout.

## Before the first public deployment

Replace or confirm the following:

1. Final custom domain.
2. Netlify form notification email.
3. Final business name or legal entity details, if they need to appear in policies.
4. Final public social-media links, when available.
5. Final membership wording and availability.
6. Final event information before listing an event.
7. Final products and checkout provider before opening the store.
8. Professional review of policies before accounts, payments, subscriptions, private messages, or user uploads are enabled.

After the final domain is known, add:

- Absolute canonical URLs on every page.
- Absolute Open Graph image URLs.
- A production `sitemap.xml` using the final domain.
- The sitemap location in `robots.txt`.
- The verified domain in Spotify, search-console, analytics, and social profiles where appropriate.

## Updating the Spotify artist

The official Spotify artist URL and embed URL are stored near the top of:

```text
assets/js/app.js
```

Search for:

```js
const SPOTIFY_URL
```

The embed URL also appears in `index.html` and `music.html` inside `data-src` attributes. Update all three locations if the artist ID ever changes.

## Logo rule

The logo must retain its original `735 × 760` proportions.

Do not set unrelated fixed width and height values. The production CSS uses:

```css
width: auto;
height: 52px;
aspect-ratio: 735 / 760;
object-fit: contain;
```

Hero and page logos use `width: 100%` with `height: auto`. Keep this behavior to prevent horizontal compression.

## Adding real events

Do not create fictional public dates. When a real event is approved, add a proper event card with:

- Official title
- Confirmed date and time zone
- Start and end time
- Venue or online location
- Eligibility or membership requirement
- Price or free status
- Capacity, if relevant
- Accessibility information
- Recording and privacy expectations
- Cancellation or refund terms
- Verified registration link
- Contact route

Remove outdated events promptly or move them to a clearly labeled archive.

## Opening the store

Do not add a working cart until a secure checkout provider and order-delivery workflow are connected. A production store needs:

- Real products and approved prices
- Product files stored outside the public repository
- Hosted checkout
- Taxes, refunds, cancellation, and delivery rules
- Purchase records
- Secure download entitlements
- Customer support process
- Updated privacy notice and terms

Never place paid files in the public website folder. Public files can be downloaded by anyone who discovers the URL.

## Membership, accounts, messages, and private files

The public release intentionally does not imitate secure accounts in browser storage. The requested member platform should be built as a separate secure application or protected area.

Required production components include:

- Email verification and password recovery
- Secure authentication sessions
- Role and membership permissions enforced on the server
- Private object storage with signed downloads
- Subscription billing and webhook verification
- Separate-purchase entitlements
- Member directory privacy controls
- Direct messaging with blocking and reporting
- Moderation queues and audit records
- Staff roles and least-privilege permissions
- File validation, malware scanning, versioning, and backups
- Data export and account deletion workflows
- Security logging and abuse controls

A suitable architecture could use a hosted authentication/database/storage platform, a hosted payment provider, and serverless functions. Do not expose service secrets in browser JavaScript or a public GitHub repository.

## Staff content workflow

When the secure portal is built, staff publishing should require:

1. Sign-in with a staff role.
2. Permission checks for the specific action.
3. File-type and size validation.
4. Malware scanning.
5. Title, description, tags, access level, and publication status.
6. Draft review before publication.
7. Version history and rollback.
8. Audit records showing who changed what and when.
9. Private storage for staff-only and member-only files.
10. Verification with an account at each access level.

## Accessibility maintenance

The floating `Aa` control is a real public feature. It stores only display preferences in the visitor’s browser.

Before each major release, test:

- Keyboard navigation and visible focus
- Menu opening and closing
- Skip link
- Heading order and landmarks
- Form labels and errors
- 200% and 400% zoom
- Mobile reflow
- High contrast
- Reduced motion
- Screen readers
- Spotify alternative link
- New documents for tagging and reading order
- Captions or transcripts for audiovisual material

## Privacy maintenance

The current privacy notice describes only the public site, local display preferences, contact submissions, hosting logs, and the optional Spotify embed.

Update the notice before enabling any of the following:

- Analytics or advertising
- Mailing lists
- Member accounts
- Profiles or directories
- Direct messages
- Subscriptions
- Purchases
- Private files
- Event registration
- Uploaded user content
- Moderation records

## Security headers

The `_headers` file contains a production-oriented baseline for Netlify, including a Content Security Policy, referrer policy, frame protection, and permissions restrictions.

Re-test the policy whenever a new third-party service is added. Add only the exact domains needed by the service.

## File updates

For a full replacement, upload the complete `orgasmaphoria-site` folder.

For a future patch, provide:

- A changed-files-only ZIP
- `CHANGED-FILES.txt`
- Backup instructions
- Upload paths
- Any policy, content, or configuration changes
- A rollback plan

## Final testing checklist

Before announcing the site:

- Open every navigation link.
- Test desktop, tablet, and phone widths.
- Confirm every local image, stylesheet, script, icon, and manifest path.
- Test the age entrance.
- Test all accessibility preferences.
- Test both Spotify load buttons and direct links.
- Test the share button.
- Submit the contact form and verify actual delivery.
- Test the thank-you page.
- Test the 404 page.
- Confirm no removed demo or press-kit page is linked.
- Confirm there are no fictional products, events, users, documents, or messages.
- Validate HTML and JavaScript.
- Review privacy, terms, and accessibility content.
- Check the deployed security headers.
- Verify the final ZIP opens correctly.

## Files intentionally absent

The following should not be restored to the public folder unless a secure production system replaces them:

- `press-kit.html`
- `guide.html`
- `login.html`
- `dashboard.html`
- `library.html`
- `members.html`
- `messages.html`
- `profile.html`
- `settings.html`
- `staff.html`
- Public sample documents
- Public sample invitations
- Browser-only account, message, upload, and checkout scripts
