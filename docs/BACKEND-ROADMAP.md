# Orgasmaphoria Backend Roadmap

The included website is a complete front-end prototype. Public pages can be hosted as static files, but secure accounts, memberships, private files, messages, moderation, and payments require trusted server-side services.

## Recommended production architecture

- **Frontend:** the included HTML/CSS/JavaScript, migrated to a component framework only if the team needs it.
- **Authentication and database:** a managed PostgreSQL authentication platform such as Supabase, or an equivalent service with server-enforced row-level permissions.
- **Private file storage:** authenticated object storage. Downloads should use short-lived signed URLs after server-side authorization.
- **Payments:** Stripe Checkout and Customer Portal, or another hosted PCI-compliant provider. Never collect raw card data in this static site.
- **Transactional email:** a dedicated provider for account verification, password reset, purchase receipts, invitations, and support notifications.
- **Contact and spam protection:** server endpoint with validation, CSRF protection, rate limiting, bot protection, and an internal queue.
- **Monitoring:** error logging, security alerts, audit records, backups, and uptime checks.

## Suggested build order

1. Configure authentication, verified email, password reset, and secure sessions.
2. Create profiles and privacy settings with row-level security.
3. Implement membership records and payment webhooks.
4. Move library metadata into the database and files into private storage.
5. Build secure entitlement checks for memberships and one-time purchases.
6. Implement events, RSVPs, capacity, waitlists, and invitation delivery.
7. Build conversations, messages, blocks, reports, and staff moderation.
8. Connect the contact form and staff support queue.
9. Add staff roles, approval workflows, versioning, and audit logs.
10. Complete policy, accessibility, security, backup, and recovery reviews.

## Non-negotiable security rules

- Do not place real private documents in a public repository or static hosting folder.
- Do not trust role, tier, price, or access values sent by the browser.
- Verify membership and purchase entitlements on the server for every protected file request.
- Verify payment-provider webhook signatures before granting access.
- Store only password hashes through the authentication provider; do not build custom password storage in localStorage.
- Validate uploaded extension, MIME type, signature, size, and filename on the server.
- Scan uploads for malware and block script execution in storage paths.
- Rate-limit login, registration, contact, report, message, RSVP, and upload actions.
- Record administrative changes and access grants in an audit log.
- Use least-privilege staff roles rather than one universal administrator account.

## Production data areas

The starter schema includes profiles, memberships, content, content files, events, RSVPs, products, orders, conversations, messages, blocks, reports, contact submissions, and audit records. It is a starting point, not a completed security implementation.
