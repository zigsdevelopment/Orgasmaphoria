(() => {
  "use strict";

  const DATA = window.ORG_DATA;
  const KEYS = {
    session: "org_session_v3",
    customUsers: "org_custom_users_v3",
    preferences: "org_preferences_v3",
    privacy: "org_privacy_v3",
    cart: "org_cart_v3",
    saves: "org_saves_v3",
    rsvps: "org_rsvps_v3",
    messages: "org_messages_v3",
    contact: "org_contact_outbox_v3",
    contentMeta: "org_uploaded_content_v3",
    age: "org_age_verified_v3",
    reports: "org_reports_v3",
    blocked: "org_blocked_v3"
  };

  const tierLevel = { guest: 0, listener: 1, inner: 2, patron: 3, staff: 4 };

  function parse(key, fallback) {
    try {
      const value = localStorage.getItem(key);
      return value ? JSON.parse(value) : fallback;
    } catch (error) {
      console.warn(`Could not read ${key}`, error);
      return fallback;
    }
  }

  function store(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
      return true;
    } catch (error) {
      console.warn(`Could not store ${key}`, error);
      return false;
    }
  }

  function escapeHTML(value = "") {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function formatDate(input, options = {}) {
    const date = new Date(`${input}T12:00:00`);
    if (Number.isNaN(date.getTime())) return input;
    return new Intl.DateTimeFormat("en-US", {
      month: "long",
      day: "numeric",
      year: "numeric",
      ...options
    }).format(date);
  }

  function formatMoney(value) {
    return new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(value);
  }

  function getCustomUsers() {
    return parse(KEYS.customUsers, []);
  }

  function getAllUsers() {
    return [...DATA.users, ...getCustomUsers()];
  }

  function getUserById(id) {
    return getAllUsers().find((user) => user.id === id) || null;
  }

  function getSession() {
    return parse(KEYS.session, null);
  }

  function getCurrentUser() {
    const session = getSession();
    return session?.userId ? getUserById(session.userId) : null;
  }

  function signInAs(userId) {
    const user = getUserById(userId);
    if (!user) return false;
    store(KEYS.session, { userId, signedInAt: new Date().toISOString() });
    document.dispatchEvent(new CustomEvent("org:session-changed"));
    return true;
  }

  function signOut() {
    localStorage.removeItem(KEYS.session);
    document.dispatchEvent(new CustomEvent("org:session-changed"));
  }

  async function hashText(text) {
    if (!window.crypto?.subtle) return btoa(unescape(encodeURIComponent(text)));
    const bytes = new TextEncoder().encode(text);
    const hash = await crypto.subtle.digest("SHA-256", bytes);
    return [...new Uint8Array(hash)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
  }

  async function registerUser({ displayName, username, email, password }) {
    const normalizedEmail = email.trim().toLowerCase();
    const normalizedUsername = username.trim().toLowerCase().replace(/[^a-z0-9_.-]/g, "");
    const users = getAllUsers();
    if (users.some((user) => user.email?.toLowerCase() === normalizedEmail)) {
      throw new Error("An account already uses that email address.");
    }
    if (users.some((user) => user.username.toLowerCase() === normalizedUsername)) {
      throw new Error("That username is already in use.");
    }
    const id = `local-${Date.now()}`;
    const initials = displayName.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join("").toUpperCase() || "OM";
    const user = {
      id,
      displayName: displayName.trim(),
      username: normalizedUsername,
      email: normalizedEmail,
      passwordHash: await hashText(password),
      initials,
      tier: "listener",
      role: "member",
      city: "",
      joined: new Intl.DateTimeFormat("en-US", { month: "long", year: "numeric" }).format(new Date()),
      bio: "New Orgasmaphoria community member.",
      interests: ["Music"],
      online: true,
      profileVisibility: "members",
      allowMessages: "members",
      localDemoAccount: true
    };
    const customUsers = getCustomUsers();
    customUsers.push(user);
    store(KEYS.customUsers, customUsers);
    signInAs(id);
    return user;
  }

  async function authenticateLocal(emailOrUsername, password) {
    const value = emailOrUsername.trim().toLowerCase();
    const user = getCustomUsers().find((candidate) => candidate.email?.toLowerCase() === value || candidate.username.toLowerCase() === value);
    if (!user) throw new Error("No local demo account matches those details.");
    const provided = await hashText(password);
    if (provided !== user.passwordHash) throw new Error("The password is incorrect.");
    signInAs(user.id);
    return user;
  }

  function canAccess(access, user = getCurrentUser()) {
    const current = user?.role === "staff" ? "staff" : user?.tier || "guest";
    return (tierLevel[current] || 0) >= (tierLevel[access] || 0);
  }

  function getSavedItems() {
    return parse(KEYS.saves, []);
  }

  function toggleSaved(id) {
    const items = getSavedItems();
    const next = items.includes(id) ? items.filter((item) => item !== id) : [...items, id];
    store(KEYS.saves, next);
    document.dispatchEvent(new CustomEvent("org:saves-changed"));
    return next.includes(id);
  }

  function getCart() {
    return parse(KEYS.cart, []);
  }

  function addToCart(productId) {
    const cart = getCart();
    const existing = cart.find((item) => item.productId === productId);
    if (existing) existing.quantity += 1;
    else cart.push({ productId, quantity: 1 });
    store(KEYS.cart, cart);
    document.dispatchEvent(new CustomEvent("org:cart-changed"));
    toast("Added to the demo cart.");
  }

  function updateCart(productId, quantity) {
    const cart = getCart();
    const item = cart.find((entry) => entry.productId === productId);
    if (!item) return;
    item.quantity = Math.max(0, Number(quantity) || 0);
    const next = cart.filter((entry) => entry.quantity > 0);
    store(KEYS.cart, next);
    document.dispatchEvent(new CustomEvent("org:cart-changed"));
  }

  function clearCart() {
    store(KEYS.cart, []);
    document.dispatchEvent(new CustomEvent("org:cart-changed"));
  }

  function toast(message, kind = "info") {
    let region = document.querySelector(".toast-region");
    if (!region) {
      region = document.createElement("div");
      region.className = "toast-region";
      region.setAttribute("aria-live", "polite");
      region.setAttribute("aria-atomic", "false");
      document.body.append(region);
    }
    const item = document.createElement("div");
    item.className = `toast toast--${kind}`;
    item.textContent = message;
    region.append(item);
    requestAnimationFrame(() => item.classList.add("is-visible"));
    setTimeout(() => {
      item.classList.remove("is-visible");
      setTimeout(() => item.remove(), 240);
    }, 3800);
  }

  function openDialog({ title, body, actions = [], wide = false }) {
    const existing = document.querySelector(".app-dialog");
    if (existing) existing.remove();
    const wrapper = document.createElement("div");
    wrapper.className = "app-dialog";
    wrapper.innerHTML = `
      <div class="app-dialog__backdrop" data-dialog-close></div>
      <section class="app-dialog__panel ${wide ? "app-dialog__panel--wide" : ""}" role="dialog" aria-modal="true" aria-labelledby="appDialogTitle">
        <button class="icon-button app-dialog__close" type="button" data-dialog-close aria-label="Close dialog">×</button>
        <h2 id="appDialogTitle">${escapeHTML(title)}</h2>
        <div class="app-dialog__body"></div>
        <div class="app-dialog__actions"></div>
      </section>`;
    const bodyTarget = wrapper.querySelector(".app-dialog__body");
    if (body instanceof Node) bodyTarget.append(body);
    else bodyTarget.innerHTML = body;
    const actionTarget = wrapper.querySelector(".app-dialog__actions");
    actions.forEach((action) => {
      const button = document.createElement(action.href ? "a" : "button");
      button.className = `button ${action.className || "button--ghost"}`;
      button.textContent = action.label;
      if (action.href) button.href = action.href;
      else button.type = "button";
      if (action.onClick) button.addEventListener("click", () => action.onClick(wrapper));
      actionTarget.append(button);
    });
    const previousFocus = document.activeElement;
    const close = () => {
      wrapper.remove();
      previousFocus?.focus?.();
    };
    wrapper.querySelectorAll("[data-dialog-close]").forEach((element) => element.addEventListener("click", close));
    wrapper.addEventListener("keydown", (event) => {
      if (event.key === "Escape") close();
      if (event.key === "Tab") {
        const focusable = [...wrapper.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')];
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable.at(-1);
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    });
    document.body.append(wrapper);
    wrapper.querySelector(".app-dialog__close")?.focus();
    return { wrapper, close };
  }

  function navItem(href, label, page, activePage) {
    const active = page === activePage ? ' aria-current="page" class="is-active"' : "";
    return `<a href="${href}"${active}>${label}</a>`;
  }

  function renderShell() {
    const page = document.body.dataset.page || "home";
    const header = document.querySelector("[data-site-header]");
    const footer = document.querySelector("[data-site-footer]");
    const user = getCurrentUser();
    if (header) {
      header.className = "site-header";
      header.innerHTML = `
        <div class="site-header__inner">
          <a class="brand" href="index.html" aria-label="Orgasmaphoria home">
            <img src="assets/images/logo.webp" width="735" height="760" alt="">
            <span><strong>Orgasmaphoria</strong><small>Music · Mystery · Connection</small></span>
          </a>
          <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primaryNav">
            <span></span><span></span><span></span><span class="sr-only">Toggle navigation</span>
          </button>
          <nav class="primary-nav" id="primaryNav" aria-label="Primary navigation">
            ${navItem("index.html", "Home", "home", page)}
            ${navItem("music.html", "Listen", "music", page)}
            ${navItem("community.html", "Membership", "community", page)}
            ${navItem("library.html", "Library", "library", page)}
            ${navItem("events.html", "Events", "events", page)}
            ${navItem("store.html", "Store", "store", page)}
            ${navItem("contact.html", "Contact", "contact", page)}
          </nav>
          <div class="header-actions">
            <a class="cart-link" href="store.html#cart" aria-label="Open shopping cart">Bag <span data-cart-count>0</span></a>
            <div class="account-menu">
              <button class="account-button" type="button" aria-expanded="false" aria-controls="accountMenuPanel">
                <span class="account-avatar">${escapeHTML(user?.initials || "O")}</span>
                <span class="account-label">${escapeHTML(user?.displayName || "Sign in")}</span>
              </button>
              <div class="account-menu__panel" id="accountMenuPanel" hidden>
                ${user ? `
                  <div class="account-menu__identity"><strong>${escapeHTML(user.displayName)}</strong><span>${escapeHTML(user.tier === "staff" ? "Staff" : DATA.tiers.find((tier) => tier.id === user.tier)?.name || "Member")}</span></div>
                  <a href="dashboard.html">Dashboard</a>
                  <a href="profile.html">My profile</a>
                  <a href="messages.html">Messages</a>
                  <a href="settings.html">Settings</a>
                  ${user.role === "staff" ? '<a href="staff.html">Staff portal</a>' : ""}
                  <button type="button" data-sign-out>Sign out</button>` : `
                  <a href="login.html">Sign in</a>
                  <a href="login.html#register">Create account</a>
                  <a href="guide.html#demo-accounts">Demo accounts</a>`}
              </div>
            </div>
          </div>
        </div>`;
    }
    if (footer) {
      footer.className = "site-footer";
      footer.innerHTML = `
        <div class="site-footer__inner">
          <div class="footer-brand">
            <img src="assets/images/logo.webp" width="735" height="760" alt="">
            <div><strong>Orgasmaphoria</strong><p>An adult creative community for music, stories, events, and thoughtful connection.</p></div>
          </div>
          <div class="footer-links">
            <div><strong>Explore</strong><a href="music.html">Listen</a><a href="community.html">Membership</a><a href="library.html">Library</a><a href="events.html">Events</a></div>
            <div><strong>Account</strong><a href="dashboard.html">Dashboard</a><a href="members.html">Member directory</a><a href="messages.html">Messages</a><a href="settings.html">Settings</a></div>
            <div><strong>Help</strong><a href="guide.html">Site guide</a><a href="contact.html">Contact</a><a href="accessibility.html">Accessibility</a><a href="privacy.html">Privacy</a></div>
            <div><strong>More</strong><a href="press-kit.html">Media & collaborations</a><a href="terms.html">Terms</a><a href="staff.html">Staff portal</a><a href="${DATA.site.spotifyArtist}" target="_blank" rel="noreferrer">Spotify ↗</a></div>
          </div>
        </div>
        <div class="site-footer__bottom"><span>© ${new Date().getFullYear()} Orgasmaphoria. Demo website package.</span><span>${escapeHTML(DATA.site.matureNotice)}</span></div>`;
    }

    const menuToggle = document.querySelector(".menu-toggle");
    const nav = document.querySelector(".primary-nav");
    menuToggle?.addEventListener("click", () => {
      const open = menuToggle.getAttribute("aria-expanded") === "true";
      menuToggle.setAttribute("aria-expanded", String(!open));
      nav?.classList.toggle("is-open", !open);
    });

    const accountButton = document.querySelector(".account-button");
    const accountPanel = document.querySelector(".account-menu__panel");
    accountButton?.addEventListener("click", () => {
      const open = accountButton.getAttribute("aria-expanded") === "true";
      accountButton.setAttribute("aria-expanded", String(!open));
      accountPanel.hidden = open;
    });
    document.addEventListener("click", (event) => {
      const menu = document.querySelector(".account-menu");
      if (!menu?.contains(event.target) && accountPanel && !accountPanel.hidden) {
        accountPanel.hidden = true;
        accountButton?.setAttribute("aria-expanded", "false");
      }
    });
    document.querySelector("[data-sign-out]")?.addEventListener("click", () => {
      signOut();
      toast("You have signed out.");
      setTimeout(() => (window.location.href = "index.html"), 250);
    });
    updateCartCount();
  }

  function updateCartCount() {
    const total = getCart().reduce((sum, item) => sum + item.quantity, 0);
    document.querySelectorAll("[data-cart-count]").forEach((element) => {
      element.textContent = String(total);
      element.hidden = total === 0;
    });
  }

  function getPreferences() {
    return {
      fontScale: 100,
      contrast: false,
      reducedMotion: window.matchMedia("(prefers-reduced-motion: reduce)").matches,
      underlineLinks: false,
      readableFont: false,
      comfortableSpacing: false,
      theme: "midnight",
      ...parse(KEYS.preferences, {})
    };
  }

  function applyPreferences(preferences = getPreferences()) {
    const root = document.documentElement;
    root.style.setProperty("--font-scale", `${preferences.fontScale / 100}`);
    root.dataset.theme = preferences.theme || "midnight";
    root.classList.toggle("pref-contrast", Boolean(preferences.contrast));
    root.classList.toggle("pref-reduced-motion", Boolean(preferences.reducedMotion));
    root.classList.toggle("pref-underline-links", Boolean(preferences.underlineLinks));
    root.classList.toggle("pref-readable-font", Boolean(preferences.readableFont));
    root.classList.toggle("pref-comfortable-spacing", Boolean(preferences.comfortableSpacing));
  }

  function savePreferences(next) {
    const merged = { ...getPreferences(), ...next };
    store(KEYS.preferences, merged);
    applyPreferences(merged);
    document.dispatchEvent(new CustomEvent("org:preferences-changed", { detail: merged }));
    return merged;
  }

  function renderAccessibilityLauncher() {
    if (document.querySelector(".accessibility-launcher")) return;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "accessibility-launcher";
    button.setAttribute("aria-label", "Open accessibility settings");
    button.innerHTML = '<span aria-hidden="true">Aa</span>';
    button.addEventListener("click", () => {
      const prefs = getPreferences();
      const form = document.createElement("form");
      form.className = "quick-accessibility";
      form.innerHTML = `
        <label class="field"><span>Text size</span><select name="fontScale"><option value="100">Default</option><option value="110">Large</option><option value="120">Larger</option><option value="130">Largest</option></select></label>
        <label class="check-row"><input type="checkbox" name="contrast"> <span>High contrast</span></label>
        <label class="check-row"><input type="checkbox" name="reducedMotion"> <span>Reduce motion</span></label>
        <label class="check-row"><input type="checkbox" name="underlineLinks"> <span>Underline links</span></label>
        <label class="check-row"><input type="checkbox" name="readableFont"> <span>Use a simpler reading font</span></label>
        <label class="check-row"><input type="checkbox" name="comfortableSpacing"> <span>Increase reading spacing</span></label>
        <p class="form-note">More privacy and accessibility controls are available on the <a href="settings.html">Settings page</a>.</p>`;
      form.elements.fontScale.value = String(prefs.fontScale);
      ["contrast", "reducedMotion", "underlineLinks", "readableFont", "comfortableSpacing"].forEach((name) => {
        form.elements[name].checked = Boolean(prefs[name]);
      });
      const dialog = openDialog({
        title: "Accessibility",
        body: form,
        actions: [
          { label: "Reset", onClick: () => { localStorage.removeItem(KEYS.preferences); applyPreferences(); dialog.close(); toast("Accessibility preferences reset."); } },
          { label: "Save settings", className: "button--primary", onClick: () => {
            savePreferences({
              fontScale: Number(form.elements.fontScale.value),
              contrast: form.elements.contrast.checked,
              reducedMotion: form.elements.reducedMotion.checked,
              underlineLinks: form.elements.underlineLinks.checked,
              readableFont: form.elements.readableFont.checked,
              comfortableSpacing: form.elements.comfortableSpacing.checked
            });
            dialog.close();
            toast("Accessibility preferences saved.", "success");
          }}
        ]
      });
    });
    document.body.append(button);
  }

  function initAgeGate() {
    const saved = parse(KEYS.age, null);
    const valid = saved?.verified && Date.now() - new Date(saved.at).getTime() < 30 * 24 * 60 * 60 * 1000;
    if (valid || document.body.dataset.skipAgeGate === "true") return;
    const gate = document.createElement("div");
    gate.className = "age-gate";
    gate.innerHTML = `
      <section class="age-gate__panel" role="dialog" aria-modal="true" aria-labelledby="ageTitle" aria-describedby="ageDescription">
        <img src="assets/images/logo.webp" width="735" height="760" alt="">
        <p class="eyebrow">Mature creative community</p>
        <h1 id="ageTitle">Welcome to Orgasmaphoria</h1>
        <p id="ageDescription">This website is intended for adults 18 and older and discusses mature themes through music, stories, and community conversation. The public site does not contain explicit imagery.</p>
        <div class="button-row"><button class="button button--primary" type="button" data-age-enter>I am 18 or older</button><a class="button button--ghost" href="https://www.google.com">Leave site</a></div>
        <small>Your selection is remembered on this device for 30 days.</small>
      </section>`;
    document.body.append(gate);
    document.body.classList.add("is-gated");
    gate.querySelector("[data-age-enter]").addEventListener("click", () => {
      store(KEYS.age, { verified: true, at: new Date().toISOString() });
      gate.classList.add("is-closing");
      setTimeout(() => {
        gate.remove();
        document.body.classList.remove("is-gated");
      }, 280);
    });
    gate.querySelector("[data-age-enter]").focus();
  }

  function requireUser(options = {}) {
    const user = getCurrentUser();
    if (!user) {
      const returnTo = encodeURIComponent(window.location.pathname.split("/").pop() + window.location.search);
      if (options.redirect !== false) window.location.href = `login.html?return=${returnTo}`;
      return null;
    }
    if (options.role && user.role !== options.role) {
      if (options.redirect !== false) window.location.href = "dashboard.html";
      return null;
    }
    return user;
  }

  function downloadJSON(filename, data) {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 500);
  }

  function getPrivacy(user = getCurrentUser()) {
    if (!user) return {};
    return {
      profileVisibility: user.profileVisibility || "members",
      allowMessages: user.allowMessages || "members",
      showOnline: true,
      showCity: true,
      showInterests: true,
      recommendations: true,
      marketingEmail: false,
      eventEmail: true,
      ...parse(`${KEYS.privacy}_${user.id}`, {})
    };
  }

  function savePrivacy(next, user = getCurrentUser()) {
    if (!user) return null;
    const merged = { ...getPrivacy(user), ...next };
    store(`${KEYS.privacy}_${user.id}`, merged);
    return merged;
  }

  // IndexedDB is used only so the static demonstration can persist uploaded sample files in this browser.
  const dbPromise = new Promise((resolve, reject) => {
    if (!("indexedDB" in window)) return resolve(null);
    try {
      const request = indexedDB.open("orgasmaphoria-demo-files", 1);
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains("files")) db.createObjectStore("files", { keyPath: "id" });
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    } catch (error) {
      console.warn("Local file storage is unavailable in this browsing context.", error);
      resolve(null);
    }
  });

  async function saveUploadedFile(record) {
    const db = await dbPromise;
    if (!db) throw new Error("This browser does not support local file storage.");
    return new Promise((resolve, reject) => {
      const transaction = db.transaction("files", "readwrite");
      transaction.objectStore("files").put(record);
      transaction.oncomplete = () => resolve(record);
      transaction.onerror = () => reject(transaction.error);
    });
  }

  async function getUploadedFile(id) {
    const db = await dbPromise;
    if (!db) return null;
    return new Promise((resolve, reject) => {
      const transaction = db.transaction("files", "readonly");
      const request = transaction.objectStore("files").get(id);
      request.onsuccess = () => resolve(request.result || null);
      request.onerror = () => reject(request.error);
    });
  }

  async function deleteUploadedFile(id) {
    const db = await dbPromise;
    if (!db) return;
    return new Promise((resolve, reject) => {
      const transaction = db.transaction("files", "readwrite");
      transaction.objectStore("files").delete(id);
      transaction.oncomplete = resolve;
      transaction.onerror = () => reject(transaction.error);
    });
  }

  function getUploadedMeta() {
    return parse(KEYS.contentMeta, []);
  }

  function setUploadedMeta(records) {
    store(KEYS.contentMeta, records);
  }

  document.addEventListener("DOMContentLoaded", () => {
    applyPreferences();
    renderShell();
    renderAccessibilityLauncher();
    initAgeGate();
    document.querySelectorAll("[data-current-year]").forEach((element) => (element.textContent = String(new Date().getFullYear())));
    document.querySelectorAll("[data-spotify-link]").forEach((element) => (element.href = DATA.site.spotifyArtist));
  });

  document.addEventListener("org:cart-changed", updateCartCount);
  document.addEventListener("org:session-changed", renderShell);

  window.ORG_APP = {
    DATA,
    KEYS,
    tierLevel,
    parse,
    store,
    escapeHTML,
    formatDate,
    formatMoney,
    getAllUsers,
    getUserById,
    getSession,
    getCurrentUser,
    signInAs,
    signOut,
    registerUser,
    authenticateLocal,
    canAccess,
    getSavedItems,
    toggleSaved,
    getCart,
    addToCart,
    updateCart,
    clearCart,
    toast,
    openDialog,
    getPreferences,
    applyPreferences,
    savePreferences,
    requireUser,
    downloadJSON,
    getPrivacy,
    savePrivacy,
    saveUploadedFile,
    getUploadedFile,
    deleteUploadedFile,
    getUploadedMeta,
    setUploadedMeta
  };
})();
