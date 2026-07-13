# Hosting and Zigs Development Hub Guide

## Best setup for testing

Use your Zigs Development GitHub Pages repository as the central hub, then place each static project in its own folder.

Example:

```text
zigsdevelopment.github.io/
├── index.html
├── projects/
│   ├── orgasmaphoria/
│   │   ├── index.html
│   │   ├── music.html
│   │   ├── community.html
│   │   ├── assets/
│   │   └── ...
│   ├── woodmill-pond/
│   └── another-project/
```

The Orgasmaphoria test address becomes:

```text
https://YOUR-GITHUB-USERNAME.github.io/projects/orgasmaphoria/
```

## GitHub Pages steps

1. Open the repository used for the Zigs Development website.
2. Create `projects/orgasmaphoria/`.
3. Copy everything inside this `orgasmaphoria-site` folder into it.
4. Commit and push the files.
5. Open the repository’s Pages settings and publish from the branch containing the hub.
6. Wait for the deployment, then open the project path.

## Drag-and-drop testing

A static host that accepts folder uploads can publish this package quickly. Upload the `orgasmaphoria-site` folder, not the outer ZIP directory.

## What static hosting can test

- Responsive design
- Navigation
- Spotify embed
- Age entrance
- Local demo accounts
- Member access previews
- Local messages
- Privacy and accessibility settings
- Store bag
- Sample document downloads
- Local staff upload demonstration

## What static hosting cannot securely provide

- Real private documents
- Real authentication
- Real staff permissions
- Real member messaging
- Real memberships or payments
- Protected purchased downloads
- Secure contact delivery

Do not treat interface locks as security. A static host sends files directly to the browser and cannot enforce private access by itself.

## Production path

Keep the public site on a fast static or edge host and connect it to:

- Authentication and PostgreSQL database
- Private object storage
- Hosted checkout and subscription management
- Transactional email
- Server-side contact and moderation tools

Use `docs/BACKEND-ROADMAP.md` and `backend/schema.sql` as a starting plan.
