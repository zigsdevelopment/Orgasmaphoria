# Orgasmaphoria Production Website V4

This is the public multi-page website plus the production account, membership, payment, private-library, member-directory, messaging, and staff-permission codebase.

The public pages can be uploaded immediately. Secure accounts and payments become active only after Supabase and Stripe are configured. No demo accounts, fictional users, browser-only permissions, electronic press kit, or public administrator guide is included.

## Main changes in this release

- Removed the two homepage boxes that covered the logo.
- Kept the logo at its original `735 × 760` proportions on every page.
- Made the Spotify artist embed load normally by default.
- Removed the homepage privacy/accessibility promotional section.
- Kept Privacy, Accessibility, and Terms as footer buttons.
- Replaced the header Spotify button with **Create account / Login**. It changes to **My Dashboard** after sign-in.
- Centralized the repeated header, footer, account button, age entrance, accessibility controls, catalog, and membership data.
- Added a populated digital store and shopping bag.
- Linked membership choices directly to store checkout.
- Changed membership order to Listener, Velvet Patron, then **Inner Circle as the highest level**.
- Added secure-account frontend pages: authentication, dashboard, library, member directory, messages, account settings, and staff dashboard.
- Added manual account permissions modeled after the Woodmill Pond system.
- Added a protected technical Administrator that ordinary managers cannot edit, demote, disable, or change.
- Added Supabase schema, row-level security, private storage rules, Stripe Checkout functions, webhook fulfillment, and billing portal integration.

## Public pages

- `index.html` — homepage
- `music.html` — Spotify catalog
- `membership.html` — membership comparison
- `store.html` — memberships and digital products
- `events.html` — confirmed public events and member-invitation explanation
- `about.html` — project story
- `contact.html` — Netlify contact form
- `privacy.html`, `accessibility.html`, `terms.html`
- `404.html`, `thanks.html`, checkout result pages

## Account pages

- `auth.html` — login, registration, password reset
- `dashboard.html` — membership, purchases, resources, quick links
- `library.html` — permitted private resources and purchased products
- `members.html` — visible member directory
- `messages.html` — private participant-only conversations
- `account.html` — profile, privacy, messages, password, billing, accessibility
- `staff.html` — permission-sensitive administration

## Store catalog and draft prices

The initial catalog is centralized in `assets/js/data.js` and mirrored in `backend/supabase/schema.sql`.

Memberships:

- Listener — free
- Velvet Patron — $9/month
- Inner Circle — $19/month, highest membership

Digital products:

- Midnight Pages — $9
- Signals & Stories — $12
- The Listening Salon — $14
- After Dark Invitation Kit — $8
- Rituals of Connection — $15
- Collector's Library · Volume I — $39

These are editable starting prices. The client should approve product names, descriptions, prices, refund rules, and finished files before Stripe products are activated.

## Recommended hosting

Use Netlify for the static website and contact form, Supabase for accounts/database/private files, and Stripe for payments. Keep the Zigs Development GitHub Pages site as the public portfolio hub that links to the production domain.

GitHub Pages alone cannot securely run the private account and payment features.

## 1. Deploy the public website

1. Create a new Netlify site from this folder or Git repository.
2. Set the publish directory to the folder containing `index.html`.
3. Confirm the `contact` form appears in Netlify Forms.
4. Add the real contact-notification recipient.
5. Set the custom domain before configuring authentication redirects and Stripe URLs.
6. Remove the optional sitemap plugin from `netlify.toml` if it is not installed for the account.

## 2. Create the Supabase project

1. Create a Supabase project.
2. Open SQL Editor.
3. Run `backend/supabase/schema.sql` once.
4. In Authentication settings, enable email/password accounts and email confirmation.
5. Add the production site URL and allowed redirect URLs:
   - `/dashboard.html`
   - `/account.html`
   - `/auth.html`
6. Configure password strength and leaked-password protection.
7. Confirm the private `member-files` storage bucket exists.
8. Do not make that bucket public.

## 3. Connect the browser to Supabase

Open `assets/js/config.js` and set only the public values:

```js
window.ORG_CONFIG = Object.freeze({
  supabaseUrl: "https://PROJECT.supabase.co",
  supabaseAnonKey: "PUBLIC_ANON_KEY",
  checkoutFunction: "create-checkout",
  portalFunction: "create-portal",
  siteUrl: "https://www.example.com",
  contactEndpoint: "",
  currency: "USD"
});
```

The anon key is designed for browser use when Row Level Security is correctly configured. Never place the service-role key or Stripe secret in this file.

## 4. Create the protected Administrator

1. Create and verify the intended technical administrator through `auth.html`.
2. Open `backend/supabase/first-admin.sql`.
3. Replace `REPLACE_WITH_ADMIN_EMAIL`.
4. Run it once in Supabase SQL Editor.
5. Sign out and back in.
6. Confirm the Staff Dashboard appears from My Dashboard.

The protected Administrator cannot be modified by normal account managers. Managers can grant or remove permissions on ordinary accounts without forcing duplicate staff accounts.

## 5. Create Stripe products and prices

Create one recurring monthly Stripe Price for each paid membership and one one-time Price for each digital product. Copy the resulting `price_...` IDs into the `products` table.

Example SQL:

```sql
update public.products set stripe_price_id='price_REPLACE', active=true where slug='velvet-patron';
update public.products set stripe_price_id='price_REPLACE', active=true where slug='inner-circle';
update public.products set stripe_price_id='price_REPLACE', active=true where slug='midnight-pages';
```

Repeat for each approved item. Products remain unavailable to checkout while `active=false` or `stripe_price_id` is empty.

## 6. Deploy the Supabase Edge Functions

Deploy:

- `create-checkout`
- `create-portal`
- `stripe-webhook`

Set function secrets:

```text
STRIPE_SECRET_KEY
STRIPE_WEBHOOK_SECRET
SUPABASE_URL
SUPABASE_SERVICE_ROLE_KEY
SITE_URL
```

`SITE_URL` must be the final production origin without a trailing slash.

The checkout and portal functions require a signed-in Supabase user. The webhook uses the service role and must never be callable with that secret from browser code.

## 7. Configure the Stripe webhook

Create a Stripe webhook endpoint pointing to the deployed `stripe-webhook` function. Subscribe at minimum to:

- `checkout.session.completed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `charge.refunded`

Copy the webhook signing secret into `STRIPE_WEBHOOK_SECRET`.

The webhook is what grants membership and product permissions. Do not grant access based only on a success-page redirect.

## 8. Upload sellable and member files

Use Staff Dashboard → Resources after configuration.

For every item:

1. Enter title, subtitle, description, type, tags, status, and access level.
2. Upload the finished file.
3. Use `listener`, `velvet`, `inner`, or `staff` for membership access.
4. For a separately purchased product, set `access_level='purchase'` and connect `product_id` in Supabase.
5. Test with an account at each relevant membership level.

Private files must never be copied into the public website folder or public Git repository.

## Account roles and permissions

Roles:

- Member
- Staff
- Manager
- Protected Technical Administrator

Granular permissions:

- Manage accounts and approvals
- Manage roles and permissions
- Manage resources and files
- Manage products and memberships
- Manage events and invitations
- Review reports and moderation
- View orders and subscriptions
- View security and audit records

The dashboard shows only administration sections that the signed-in account is allowed to use.

## Payment and permission flow

1. The customer creates or signs into one personal account.
2. The customer chooses a membership or product.
3. The server creates Stripe Checkout using that user ID as trusted metadata.
4. Stripe sends a signed webhook after successful payment.
5. The webhook updates membership or purchase entitlements.
6. Supabase Row Level Security checks the entitlement before returning resource metadata or files.
7. Authorized staff may manually change membership or granular permissions when necessary.

## Member privacy

Members can choose:

- Whether the profile appears in the directory
- Whether new direct messages are accepted
- Whether online status is shown
- Biography and interests displayed to members
- Accessibility and reading preferences

The member directory does not display email addresses.

## Security checklist before launch

- Review every SQL policy with a second developer.
- Keep RLS enabled on every private table.
- Confirm anonymous users cannot query profiles, messages, orders, memberships, permissions, or resources.
- Confirm private Storage files cannot be opened without an authorized account.
- Require email verification.
- Require strong passwords and enable leaked-password protection.
- Require MFA for privileged staff accounts.
- Keep Stripe and Supabase secrets only in server-side secret storage.
- Verify Stripe webhook signatures.
- Test refund and subscription-cancel behavior.
- Add rate limiting and abuse controls for messages and reports.
- Establish backups, retention, moderation, refund, and account-deletion procedures.
- Have Privacy and Terms reviewed for the final business entity and jurisdiction.

## Testing checklist

Public:

- All links and images
- Desktop, tablet, and mobile layouts
- Header account button
- Logo proportions
- Default Spotify embed
- Age entrance
- Accessibility controls
- Contact form and actual delivery
- Store bag and empty states

Accounts:

- Registration and email verification
- Login, logout, password reset, password change
- Disabled account behavior
- Profile and directory visibility
- Message permission choices
- Conversation access isolation
- Membership checkout and webhook grant
- One-time product checkout and entitlement grant
- Billing portal
- Refund entitlement removal
- Staff permission visibility
- Protected Administrator restrictions
- Manual membership change
- Resource upload and private download
- Audit records

## Files that contain live data

Live data is not stored in this website ZIP. It remains in Supabase, Supabase Storage, Stripe, and Netlify Forms. Future website patches should not delete or recreate the production database unless a reviewed migration explicitly requires it.

## Updating repeated content

- Memberships and store cards: `assets/js/data.js`
- Public Supabase settings: `assets/js/config.js`
- Shared header, footer, age entrance, accessibility controls: `assets/js/app.js`
- Database and permissions: `backend/supabase/schema.sql`
- Checkout behavior: `supabase/functions/create-checkout/index.ts`
- Payment fulfillment: `supabase/functions/stripe-webhook/index.ts`

## Known requirements still needing client action

- Final business email and Netlify notification recipient
- Final domain
- Approved product names, prices, descriptions, refund terms, and finished files
- Stripe account and Price IDs
- Supabase project and keys
- First protected Administrator email
- Final social links
- Final events
- Legal review before accepting public accounts and payments
