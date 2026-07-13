# Orgasmaphoria Production Website — V5

This release is the publishable multi-page Orgasmaphoria website with a working PHP account system, contact inbox, member library, direct messaging, memberships, permissions, staff controls, optional two-factor authentication, and Stripe Checkout integration.

The public website does not show developer instructions, test accounts, setup notices, fake messages, or prototype controls. Administration and deployment notes are kept only in this README.

## What changed in V5

- The header account action is now **Member Portal** instead of “Create Account / Login.”
- Registration and sign-in work directly through the included PHP backend; there is no Supabase configuration dependency or “account services unavailable” state.
- The sign-in and registration experience was redesigned as two balanced panels with the logo kept in its own correctly proportioned introduction area.
- Password reset is no longer a third panel. **Forgot password?** appears beneath the password field on the sign-in panel.
- The public contact page is a working server-validated form that stores messages in private storage and can also email a configured recipient.
- Optional authenticator-app two-factor authentication, encrypted TOTP secrets, one-time recovery codes, rate limiting, CSRF protection, protected administration, audit logging, and session invalidation were added based on the Woodmill Pond security architecture.
- Stripe Checkout can securely attach one-time purchases and paid memberships to the signed-in account after a verified webhook event.

## Hosting requirements

The full site requires a host that runs **PHP 8.1 or newer**. Static-only hosting such as GitHub Pages cannot run accounts, private files, messages, contact storage, permissions, or payment webhooks.

Required PHP extensions:

- OpenSSL
- Fileinfo
- cURL for Stripe Checkout
- JSON
- Sessions

The production site must use HTTPS.

## First installation

1. Upload the contents of `orgasmaphoria-site` to the website document root.
2. Set `ORG_PRIVATE_STORAGE` to a writable directory **outside the public document root** whenever the host permits it.
3. Set a strong one-time `ORG_SETUP_KEY` environment variable before initial setup.
4. Visit `/admin/setup.php` and create the protected administrator account.
5. Confirm that `/admin/setup.php` redirects to sign-in after setup.
6. Configure contact email and Stripe only after the core site is running.
7. Remove any server file-manager backups or ZIP files from the public document root.

The protected administrator cannot be demoted, disabled, or edited by ordinary staff accounts. The one-time setup page locks after the protected account is created.

## Environment variables

Use the host’s environment-variable or secrets panel. Do not put secret values in browser JavaScript.

```text
ORG_SITE_URL=https://example.com
ORG_PRIVATE_STORAGE=/absolute/private/path/orgasmaphoria
ORG_SETUP_KEY=use-a-long-random-one-time-value
ORG_CONTACT_RECIPIENT=business@example.com
ORG_CONTACT_FROM=no-reply@example.com
ORG_STRIPE_SECRET_KEY=sk_live_...
ORG_STRIPE_WEBHOOK_SECRET=whsec_...
```

`ORG_SITE_URL` should not end with a slash. `ORG_CONTACT_FROM` should be an address authorized by the domain’s mail provider.

## Contact form

`contact.php` validates CSRF, uses a honeypot, rate-limits submissions, validates every field on the server, and stores accepted messages in private JSON storage. Staff with `view_contacts` permission can review them at `/admin/contacts.php`.

When `ORG_CONTACT_RECIPIENT` is configured and the host supports PHP mail, the message is also emailed. Private storage remains the authoritative inbox even when email delivery fails.

## Accounts and permissions

Personal accounts are created at `/account/login.php` with free **Listener** access. Staff can manually change:

- Account status
- Membership level
- Member, staff, or administrator role
- Granular staff permissions
- Optional 2FA reset after identity verification

The membership order is:

1. Listener
2. Velvet Patron
3. Inner Circle — highest access

Private resource access is enforced by PHP before download. Hiding a link in the browser is never used as the security boundary.

## Two-factor authentication

2FA is optional for every account. Members can enable it from **My Account → Security** using any standard TOTP authenticator app.

The implementation includes:

- AES-256-GCM encryption for stored authenticator secrets
- Eight one-time recovery codes stored only as password hashes
- Rejection of reused TOTP counters
- Ten-minute sign-in challenges
- Twelve-hour second-factor session verification
- Rate limiting for incorrect codes
- Security-version invalidation after sensitive changes
- Administrative reset for ordinary accounts
- No staff reset of the protected administrator’s enrollment

The automatically created `two-factor.key` file must remain in private storage and must be included in encrypted server backups. Losing it makes existing TOTP enrollments unreadable.

## Password and session security

- Passwords are hashed with PHP `password_hash()` using the current default algorithm.
- New passwords require at least 12 characters and reject a small list of common values.
- Sign-in, registration, password recovery, password changes, and 2FA attempts are rate-limited by hashed identifier and IP.
- Session IDs rotate at sign-in, sign-out, and successful 2FA completion.
- Session cookies use `HttpOnly`, `SameSite=Lax`, and `Secure` on HTTPS.
- Password changes and “sign out everywhere” increment the account security version, invalidating other sessions.
- Password-reset tokens are random, stored only as SHA-256 hashes, expire after one hour, and are single-use.
- Password-reset responses do not reveal whether an email address exists.

Password reset email requires working outbound mail on the server. Configure SMTP or a transactional mail service at the hosting layer when PHP `mail()` is unavailable.

## Stripe payments

The store sends only signed-in, server-authoritative catalog items to `/api/checkout.php`. The browser cannot set prices.

Configure Stripe as follows:

1. Set `ORG_STRIPE_SECRET_KEY`.
2. Create a Stripe webhook endpoint pointing to:

```text
https://example.com/billing/webhook.php
```

3. Subscribe the webhook to:
   - `checkout.session.completed`
   - `checkout.session.async_payment_succeeded`
   - `customer.subscription.deleted`
4. Set the resulting signing secret as `ORG_STRIPE_WEBHOOK_SECRET`.
5. Complete one one-time product purchase and one membership purchase in Stripe test mode before switching to live keys.

Access is granted only after a correctly signed webhook. The checkout success page does not grant access by itself.

Current catalog prices are server-authoritative in `includes/config.php`. Update the matching display values in `assets/js/data.js` at the same time so the public store and checkout agree.

## Private resources

Staff with `manage_content` permission can upload PDFs, EPUB files, ZIP archives, JPG, PNG, WebP, or plain-text files up to 25 MB. Files are renamed to random identifiers, MIME-checked with Fileinfo, stored privately, and streamed only after an authenticated server permission check.

Resource access can be assigned to:

- Public / Listener
- Velvet Patron
- Inner Circle
- Staff only
- Purchasers of a specific product entitlement

For production, keep `ORG_PRIVATE_STORAGE` outside the public document root. The included `storage-private/.htaccess` is defense in depth, not a replacement for external private storage.

## Contact, message, and audit data

Private JSON files use file locks, atomic replacement, restrictive permissions, and bounded logs where appropriate. This architecture is suitable for a modest early-stage member site. Before very large membership volume, migrate private records to a managed relational database while preserving the same server-side permission rules.

## Security headers

PHP sends a restrictive Content Security Policy plus MIME-sniffing, framing, referrer, permissions, and HSTS headers. The included root `.htaccess` adds Apache-level protection, HTTPS redirects, route redirects, directory-index blocking, and denial of sensitive file extensions.

On Nginx or another server, translate the `.htaccess` rules into the host’s configuration and explicitly deny public access to:

```text
/includes/
/storage-private/
```

Only `billing/webhook.php` should be reachable inside `/billing/`.

## Local review

From the `orgasmaphoria-site` directory:

```bash
php -S 127.0.0.1:8080
```

Then open `http://127.0.0.1:8080/`. The PHP development server is for private review only and must not be used as the public production server.

## Final launch checklist

- Use HTTPS with automatic renewal.
- Move private storage outside the web root.
- Create and secure the protected administrator.
- Enable 2FA on administrator and staff accounts even though it remains optional.
- Configure reliable outbound email.
- Configure Stripe test keys and webhook, then verify fulfillment.
- Replace sample product artwork and descriptions with approved final assets.
- Upload actual member resources through the staff portal.
- Set the real contact recipient and sender.
- Review Privacy and Terms with the business owner and qualified counsel.
- Back up private storage, including `two-factor.key`, using encrypted backups.
- Test keyboard navigation, text resizing, contrast, mobile layouts, contact delivery, account recovery, permissions, downloads, checkout, and 2FA before announcing the site.
