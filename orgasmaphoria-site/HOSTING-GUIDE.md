# Free Hosting and Project Hub Guide

## Best fit: GitHub Pages

For a collection of static test websites, use one GitHub repository named:

```text
YOUR-USERNAME.github.io
```

That repository becomes the main hub at:

```text
https://YOUR-USERNAME.github.io/
```

Place each test website in its own folder:

```text
YOUR-USERNAME.github.io/
├── index.html
├── styles.css
└── projects/
    ├── orgasmaphoria/
    │   ├── index.html
    │   ├── styles.css
    │   └── assets/
    ├── another-project/
    └── future-client-site/
```

The Orgasmaphoria preview would then be available at:

```text
https://YOUR-USERNAME.github.io/projects/orgasmaphoria/
```

A ready-made hub using this exact structure is included separately as `Website-Project-Hub.zip`.

## GitHub Pages setup

1. Create or sign into a GitHub account.
2. Create a public repository named `YOUR-USERNAME.github.io`.
3. Extract `Website-Project-Hub.zip`.
4. Upload all extracted files to the repository root.
5. Open the repository's **Settings**.
6. Open **Pages**.
7. Under **Build and deployment**, select **Deploy from a branch**.
8. Select the `main` branch and `/ (root)` folder.
9. Save and wait for the published address to appear.

## Updating a project

Replace the files inside that project's folder and commit the changes. GitHub Pages republishes the site automatically.

## Easiest one-off preview: Netlify Drop

For a quick temporary preview without setting up Git first:

1. Extract the Orgasmaphoria ZIP.
2. Sign into Netlify.
3. Open the manual deploy / drag-and-drop area.
4. Drag the entire website folder into the page.
5. Netlify creates a temporary `.netlify.app` address.

Use GitHub Pages as the permanent test hub and Netlify when you need to show someone a very fast one-off preview.

## Important privacy note

Anything published through a normal public GitHub Pages site can be viewed by anyone who has the address. Do not upload passwords, private client records, personal documents, API keys, secret tokens, or private source material.
