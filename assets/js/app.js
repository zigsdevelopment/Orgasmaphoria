(() => {
  "use strict";

  const CONFIG = window.ORG_CONFIG || {};
  const CONTENT = window.ORG_CONTENT || { site: {} };
  const STORAGE = {
    age: "org_age_confirmed_v4",
    preferences: "org_accessibility_v4",
    cart: "org_cart_v4"
  };
  const root = document.documentElement;
  const qs = (selector, scope = document) => scope.querySelector(selector);
  const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  const configured = Boolean(
    CONFIG.supabaseUrl &&
    CONFIG.supabaseAnonKey &&
    /^https:\/\//.test(CONFIG.supabaseUrl) &&
    CONFIG.supabaseAnonKey.length > 20
  );

  const client = configured && window.supabase?.createClient
    ? window.supabase.createClient(CONFIG.supabaseUrl, CONFIG.supabaseAnonKey, {
        auth: { persistSession: true, autoRefreshToken: true, detectSessionInUrl: true }
      })
    : null;

  let session = null;
  let profile = null;

  function escapeHTML(value = "") {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function readJSON(key, fallback) {
    try {
      const value = localStorage.getItem(key);
      return value ? JSON.parse(value) : fallback;
    } catch {
      return fallback;
    }
  }

  function writeJSON(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* no-op */ }
  }

  function formatMoney(value, currency = CONFIG.currency || "USD") {
    return new Intl.NumberFormat("en-US", { style: "currency", currency, maximumFractionDigits: 2 }).format(Number(value || 0));
  }

  function formatDate(value, options = {}) {
    if (!value) return "";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric", year: "numeric", ...options }).format(date);
  }

  function toast(message, type = "info") {
    let region = qs(".toast-region");
    if (!region) {
      region = document.createElement("div");
      region.className = "toast-region";
      region.setAttribute("aria-live", "polite");
      document.body.append(region);
    }
    const item = document.createElement("div");
    item.className = `toast toast--${type}`;
    item.textContent = message;
    region.append(item);
    requestAnimationFrame(() => item.classList.add("is-visible"));
    setTimeout(() => {
      item.classList.remove("is-visible");
      setTimeout(() => item.remove(), 220);
    }, 3200);
  }

  function pageName() {
    return document.body.dataset.page || "";
  }

  function navLink(href, label, page) {
    const active = pageName() === page;
    return `<a href="${href}"${active ? ' class="is-active" aria-current="page"' : ""}>${label}</a>`;
  }

  function renderHeader() {
    const target = qs("[data-site-header]");
    if (!target) return;
    target.className = "site-header";
    target.innerHTML = `
      <div class="site-header__inner">
        <a class="brand" href="index.html" aria-label="Orgasmaphoria home">
          <img src="assets/images/logo.webp" width="735" height="760" alt="">
          <span><strong>Orgasmaphoria</strong><small>Music · Mystery · Connection</small></span>
        </a>
        <nav class="primary-nav" data-primary-nav aria-label="Primary navigation">
          ${navLink("index.html", "Home", "home")}
          ${navLink("music.html", "Listen", "music")}
          ${navLink("membership.html", "Membership", "membership")}
          ${navLink("events.html", "Events", "events")}
          ${navLink("store.html", "Store", "store")}
          ${navLink("about.html", "About", "about")}
          ${navLink("contact.html", "Contact", "contact")}
        </nav>
        <div class="header-actions">
          <a class="button button--account" href="auth.html" data-account-link><span data-account-label>Create account / Login</span></a>
          <button class="icon-button menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-label="Open navigation">☰</button>
        </div>
      </div>`;
  }

  function renderFooter() {
    const target = qs("[data-site-footer]");
    if (!target) return;
    target.className = "site-footer";
    target.innerHTML = `
      <div class="site-footer__inner">
        <div class="footer-brand">
          <a class="brand" href="index.html" aria-label="Orgasmaphoria home">
            <img src="assets/images/logo.webp" width="735" height="760" alt="">
            <span><strong>Orgasmaphoria</strong><small>Music · Mystery · Connection</small></span>
          </a>
          <p>An immersive artist world centered on music, spoken storytelling, mystery, mature romance, and meaningful connection.</p>
        </div>
        <div class="footer-links">
          <div><h3>Explore</h3><a href="music.html">Listen</a><a href="membership.html">Membership</a><a href="events.html">Events</a><a href="store.html">Store</a></div>
          <div><h3>Community</h3><a href="auth.html">Account</a><a href="library.html">Library</a><a href="members.html">Members</a><a href="messages.html">Messages</a></div>
          <div><h3>Orgasmaphoria</h3><a href="about.html">About</a><a href="contact.html">Contact</a><a href="${CONTENT.site.spotifyUrl}" target="_blank" rel="noreferrer">Spotify ↗</a></div>
        </div>
      </div>
      <div class="site-footer__bottom">
        <span>© <span data-current-year></span> Orgasmaphoria. All rights reserved.</span>
        <div class="footer-policy-buttons"><a href="privacy.html">Privacy</a><a href="accessibility.html">Accessibility</a><a href="terms.html">Terms</a></div>
        <span>Intended for adults 18 and older.</span>
      </div>`;
  }

  function renderGlobalModals() {
    if (!qs("[data-age-gate]")) {
      document.body.insertAdjacentHTML("beforeend", `
        <div class="modal age-gate" data-age-gate hidden>
          <div class="modal__backdrop"></div>
          <section class="modal__panel age-gate__panel" role="dialog" aria-modal="true" aria-labelledby="age-title">
            <img src="assets/images/logo.webp" width="735" height="760" alt="">
            <p class="eyebrow">Welcome to Orgasmaphoria</p>
            <h2 id="age-title">An experience for adults.</h2>
            <p>This website explores mature romantic themes through music, stories, art, and community. Confirm that you are 18 or older to enter.</p>
            <div class="modal__actions"><button class="button button--ghost" type="button" data-age-exit>Exit</button><button class="button button--primary" type="button" data-age-enter>I am 18 or older</button></div>
          </section>
        </div>`);
    }
    if (!qs("[data-accessibility-launcher]")) {
      document.body.insertAdjacentHTML("beforeend", `
        <button class="accessibility-launcher" type="button" data-accessibility-launcher aria-label="Open accessibility settings">Aa</button>
        <div class="modal" data-accessibility-modal hidden>
          <div class="modal__backdrop"></div>
          <section class="modal__panel modal__panel--wide" role="dialog" aria-modal="true" aria-labelledby="accessibility-title">
            <button class="icon-button modal__close" type="button" data-modal-close aria-label="Close accessibility settings">×</button>
            <p class="eyebrow">Display preferences</p>
            <h2 id="accessibility-title">Accessibility settings</h2>
            <form class="preference-grid" data-accessibility-form>
              <div class="preference-row"><label for="pref-text-size">Text size</label><select id="pref-text-size" name="textSize"><option value="normal">Standard</option><option value="large">Large</option><option value="larger">Larger</option></select></div>
              <div class="preference-row"><label for="pref-theme">Appearance</label><select id="pref-theme" name="theme"><option value="midnight">Midnight</option><option value="twilight">Twilight</option></select></div>
              <label class="check-row"><input type="checkbox" name="contrast"><span>Use high contrast</span></label>
              <label class="check-row"><input type="checkbox" name="reducedMotion"><span>Reduce animation and motion</span></label>
              <label class="check-row"><input type="checkbox" name="underlineLinks"><span>Underline text links</span></label>
              <label class="check-row"><input type="checkbox" name="readableFont"><span>Use a simpler reading font</span></label>
              <label class="check-row"><input type="checkbox" name="comfortableSpacing"><span>Increase reading spacing</span></label>
              <div class="modal__actions"><button class="button button--ghost" type="button" data-accessibility-reset>Reset</button><button class="button button--primary" type="submit">Save settings</button></div>
            </form>
          </section>
        </div>`);
    }
  }

  function initNavigation() {
    const toggle = qs("[data-menu-toggle]");
    const nav = qs("[data-primary-nav]");
    if (!toggle || !nav) return;
    const close = () => { nav.classList.remove("is-open"); toggle.setAttribute("aria-expanded", "false"); };
    toggle.addEventListener("click", () => {
      const open = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", String(open));
    });
    qsa("a", nav).forEach((link) => link.addEventListener("click", close));
    document.addEventListener("click", (event) => {
      if (!nav.contains(event.target) && !toggle.contains(event.target)) close();
    });
    document.addEventListener("keydown", (event) => { if (event.key === "Escape") close(); });
  }

  function initAgeGate() {
    const gate = qs("[data-age-gate]");
    if (!gate) return;
    if (readJSON(STORAGE.age, false) !== true) {
      gate.hidden = false;
      document.body.classList.add("is-gated");
      setTimeout(() => qs("[data-age-enter]", gate)?.focus(), 20);
    }
    qs("[data-age-enter]", gate)?.addEventListener("click", () => {
      writeJSON(STORAGE.age, true);
      gate.hidden = true;
      document.body.classList.remove("is-gated");
    });
    qs("[data-age-exit]", gate)?.addEventListener("click", () => {
      if (history.length > 1) history.back();
      else location.href = "https://www.google.com/";
    });
  }

  const preferenceDefaults = {
    textSize: "normal",
    theme: "midnight",
    contrast: false,
    reducedMotion: false,
    underlineLinks: false,
    readableFont: false,
    comfortableSpacing: false
  };

  function getPreferences() {
    return { ...preferenceDefaults, ...readJSON(STORAGE.preferences, {}) };
  }

  function applyPreferences(preferences = getPreferences()) {
    root.classList.toggle("pref-text-large", preferences.textSize === "large");
    root.classList.toggle("pref-text-larger", preferences.textSize === "larger");
    root.dataset.theme = preferences.theme === "twilight" ? "twilight" : "midnight";
    root.classList.toggle("pref-contrast", Boolean(preferences.contrast));
    root.classList.toggle("pref-reduced-motion", Boolean(preferences.reducedMotion));
    root.classList.toggle("pref-underline-links", Boolean(preferences.underlineLinks));
    root.classList.toggle("pref-readable-font", Boolean(preferences.readableFont));
    root.classList.toggle("pref-comfortable-spacing", Boolean(preferences.comfortableSpacing));
  }

  function initAccessibility() {
    const launcher = qs("[data-accessibility-launcher]");
    const modal = qs("[data-accessibility-modal]");
    const form = qs("[data-accessibility-form]");
    if (!launcher || !modal || !form) return;
    const close = () => { modal.hidden = true; launcher.focus(); };
    const sync = () => {
      const prefs = getPreferences();
      Object.entries(prefs).forEach(([key, value]) => {
        const field = form.elements.namedItem(key);
        if (!field) return;
        if (field.type === "checkbox") field.checked = Boolean(value);
        else field.value = String(value);
      });
    };
    launcher.addEventListener("click", () => { sync(); modal.hidden = false; qs("button, select, input", modal)?.focus(); });
    qs("[data-modal-close]", modal)?.addEventListener("click", close);
    qs(".modal__backdrop", modal)?.addEventListener("click", close);
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const data = new FormData(form);
      const prefs = {
        textSize: String(data.get("textSize") || "normal"),
        theme: String(data.get("theme") || "midnight"),
        contrast: data.get("contrast") === "on",
        reducedMotion: data.get("reducedMotion") === "on",
        underlineLinks: data.get("underlineLinks") === "on",
        readableFont: data.get("readableFont") === "on",
        comfortableSpacing: data.get("comfortableSpacing") === "on"
      };
      writeJSON(STORAGE.preferences, prefs);
      applyPreferences(prefs);
      close();
      toast("Accessibility preferences saved.", "success");
    });
    qs("[data-accessibility-reset]", modal)?.addEventListener("click", () => {
      try { localStorage.removeItem(STORAGE.preferences); } catch { /* no-op */ }
      applyPreferences(preferenceDefaults);
      sync();
      toast("Accessibility preferences reset.");
    });
    document.addEventListener("keydown", (event) => { if (event.key === "Escape" && !modal.hidden) close(); });
  }

  function getCart() {
    return readJSON(STORAGE.cart, []).filter((item) => item?.slug && Number(item.quantity) > 0);
  }

  function setCart(cart) {
    writeJSON(STORAGE.cart, cart);
    updateCartBadge();
    window.dispatchEvent(new CustomEvent("org:cart-change", { detail: cart }));
  }

  function addToCart(slug, quantity = 1) {
    const product = CONTENT.products?.find((item) => item.slug === slug);
    if (!product) return;
    const cart = getCart();
    const existing = cart.find((item) => item.slug === slug);
    if (existing) existing.quantity = Math.min(10, existing.quantity + quantity);
    else cart.push({ slug, quantity: Math.max(1, Math.min(10, quantity)) });
    setCart(cart);
    toast(`${product.title} added to your bag.`, "success");
  }

  function removeFromCart(slug) {
    setCart(getCart().filter((item) => item.slug !== slug));
  }

  function updateCart(slug, quantity) {
    const cart = getCart();
    const item = cart.find((entry) => entry.slug === slug);
    if (!item) return;
    item.quantity = Math.max(0, Math.min(10, Number(quantity) || 0));
    setCart(cart.filter((entry) => entry.quantity > 0));
  }

  function updateCartBadge() {
    const count = getCart().reduce((sum, item) => sum + Number(item.quantity || 0), 0);
    qsa("[data-cart-count]").forEach((element) => {
      element.textContent = String(count);
      element.hidden = count === 0;
    });
  }

  async function loadProfile(userId) {
    if (!client || !userId) return null;
    const { data, error } = await client.from("profiles").select("*").eq("id", userId).maybeSingle();
    if (error) return null;
    return data;
  }

  async function refreshSession() {
    if (!client) {
      session = null;
      profile = null;
      updateAccountButton();
      return null;
    }
    const { data } = await client.auth.getSession();
    session = data.session || null;
    profile = session?.user?.id ? await loadProfile(session.user.id) : null;
    updateAccountButton();
    window.dispatchEvent(new CustomEvent("org:session", { detail: { session, profile } }));
    return session;
  }

  function updateAccountButton() {
    const link = qs("[data-account-link]");
    const label = qs("[data-account-label]");
    if (!link || !label) return;
    if (session?.user) {
      link.href = "dashboard.html";
      label.textContent = profile?.display_name ? `My Dashboard` : "My Dashboard";
      link.classList.add("is-signed-in");
    } else {
      link.href = "auth.html";
      label.textContent = "Create account / Login";
      link.classList.remove("is-signed-in");
    }
  }

  async function requireSession(returnUrl = location.href) {
    if (!session) await refreshSession();
    if (session?.user) return session;
    location.href = `auth.html?return=${encodeURIComponent(returnUrl)}`;
    return null;
  }

  async function hasPermission(permissionKey) {
    if (!client || !session?.user) return false;
    const { data, error } = await client.rpc("has_permission", { requested_permission: permissionKey });
    return !error && data === true;
  }

  async function invokeFunction(name, body) {
    if (!client) throw new Error("Service unavailable");
    const { data, error } = await client.functions.invoke(name, { body });
    if (error) throw error;
    return data;
  }

  function initShare() {
    qsa("[data-share-artist]").forEach((button) => {
      button.addEventListener("click", async () => {
        try {
          if (navigator.share) await navigator.share({ title: "Orgasmaphoria on Spotify", url: CONTENT.site.spotifyUrl });
          else if (navigator.clipboard) { await navigator.clipboard.writeText(CONTENT.site.spotifyUrl); toast("Spotify link copied.", "success"); }
          else location.href = CONTENT.site.spotifyUrl;
        } catch (error) {
          if (error?.name !== "AbortError") toast("The link could not be shared.", "error");
        }
      });
    });
  }

  function initGlobalLinks() {
    qsa("[data-spotify-link]").forEach((link) => { link.href = CONTENT.site.spotifyUrl; });
    qsa("[data-current-year]").forEach((element) => { element.textContent = String(new Date().getFullYear()); });
  }

  applyPreferences();
  renderHeader();
  renderFooter();
  renderGlobalModals();

  window.ORG_APP = {
    CONFIG,
    CONTENT,
    STORAGE,
    client,
    configured,
    escapeHTML,
    formatMoney,
    formatDate,
    toast,
    getSession: () => session,
    getProfile: () => profile,
    refreshSession,
    requireSession,
    hasPermission,
    invokeFunction,
    getCart,
    setCart,
    addToCart,
    removeFromCart,
    updateCart
  };

  document.addEventListener("DOMContentLoaded", async () => {
    initNavigation();
    initAgeGate();
    initAccessibility();
    initShare();
    initGlobalLinks();
    updateCartBadge();
    await refreshSession();
    if (client) {
      client.auth.onAuthStateChange(() => { setTimeout(refreshSession, 0); });
    }
  });
})();
