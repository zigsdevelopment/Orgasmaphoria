(() => {
  "use strict";

  const SPOTIFY_URL = "https://open.spotify.com/artist/7JPxqyyzIP3N4YChFOtFvC?si=XERgnpYLQGC4KQZBFIPNdQ";
  const STORAGE = {
    age: "orgasmaphoria_age_confirmed_v1",
    preferences: "orgasmaphoria_accessibility_v1"
  };

  const root = document.documentElement;
  const body = document.body;
  const qs = (selector, scope = document) => scope.querySelector(selector);
  const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  function readJSON(key, fallback) {
    try {
      const stored = localStorage.getItem(key);
      return stored ? JSON.parse(stored) : fallback;
    } catch {
      return fallback;
    }
  }

  function writeJSON(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch {
      // Preferences remain usable for the current visit when storage is unavailable.
    }
  }

  function toast(message) {
    let region = qs(".toast-region");
    if (!region) {
      region = document.createElement("div");
      region.className = "toast-region";
      region.setAttribute("aria-live", "polite");
      document.body.append(region);
    }
    const item = document.createElement("div");
    item.className = "toast";
    item.textContent = message;
    region.append(item);
    requestAnimationFrame(() => item.classList.add("is-visible"));
    window.setTimeout(() => {
      item.classList.remove("is-visible");
      window.setTimeout(() => item.remove(), 220);
    }, 2800);
  }

  function initNavigation() {
    const toggle = qs("[data-menu-toggle]");
    const nav = qs("[data-primary-nav]");
    if (!toggle || !nav) return;

    const close = () => {
      nav.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
    };

    toggle.addEventListener("click", () => {
      const open = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", String(open));
    });

    qsa("a", nav).forEach((link) => link.addEventListener("click", close));
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") close();
    });
    document.addEventListener("click", (event) => {
      if (!nav.contains(event.target) && !toggle.contains(event.target)) close();
    });
  }

  function initAgeGate() {
    const gate = qs("[data-age-gate]");
    if (!gate) return;

    const accepted = readJSON(STORAGE.age, false) === true;
    if (!accepted) {
      gate.hidden = false;
      body.classList.add("is-gated");
      qs("[data-age-enter]", gate)?.focus();
    }

    qs("[data-age-enter]", gate)?.addEventListener("click", () => {
      writeJSON(STORAGE.age, true);
      gate.hidden = true;
      body.classList.remove("is-gated");
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

  function openModal(modal) {
    if (!modal) return;
    modal.hidden = false;
    const focusTarget = qs("button, input, select, textarea, a", modal);
    focusTarget?.focus();
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.hidden = true;
  }

  function initAccessibility() {
    const launcher = qs("[data-accessibility-launcher]");
    const modal = qs("[data-accessibility-modal]");
    const form = qs("[data-accessibility-form]");
    if (!launcher || !modal || !form) return;

    const syncForm = () => {
      const prefs = getPreferences();
      Object.entries(prefs).forEach(([key, value]) => {
        const field = form.elements.namedItem(key);
        if (!field) return;
        if (field.type === "checkbox") field.checked = Boolean(value);
        else field.value = String(value);
      });
    };

    launcher.addEventListener("click", () => {
      syncForm();
      openModal(modal);
    });
    qsa("[data-modal-close]", modal).forEach((button) => button.addEventListener("click", () => closeModal(modal)));
    qs(".modal__backdrop", modal)?.addEventListener("click", () => closeModal(modal));

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
      closeModal(modal);
      toast("Accessibility preferences saved.");
    });

    qs("[data-accessibility-reset]", modal)?.addEventListener("click", () => {
      try { localStorage.removeItem(STORAGE.preferences); } catch { /* no-op */ }
      applyPreferences(preferenceDefaults);
      syncForm();
      toast("Accessibility preferences reset.");
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !modal.hidden) closeModal(modal);
    });
  }

  function initSpotifyLinks() {
    qsa("[data-spotify-link]").forEach((link) => {
      link.href = SPOTIFY_URL;
    });

    qsa("[data-share-artist]").forEach((button) => {
      button.addEventListener("click", async () => {
        try {
          if (navigator.share) {
            await navigator.share({
              title: "Orgasmaphoria on Spotify",
              text: "Listen to Orgasmaphoria on Spotify.",
              url: SPOTIFY_URL
            });
          } else if (navigator.clipboard) {
            await navigator.clipboard.writeText(SPOTIFY_URL);
            toast("Spotify artist link copied.");
          } else {
            location.href = SPOTIFY_URL;
          }
        } catch (error) {
          if (error?.name !== "AbortError") toast("The artist link could not be shared.");
        }
      });
    });
  }

  function initSpotifyEmbeds() {
    qsa("[data-load-spotify]").forEach((button) => {
      button.addEventListener("click", () => {
        const frame = qs("[data-spotify-frame]", button.closest(".spotify-stage__frame"));
        const consent = button.closest(".embed-consent");
        if (!frame || !frame.dataset.src) return;
        frame.src = frame.dataset.src;
        frame.hidden = false;
        consent?.remove();
      }, { once: true });
    });
  }

  function initContactTopic() {
    const select = qs('select[name="topic"]');
    if (!select) return;
    const requested = new URLSearchParams(location.search).get("topic");
    const map = {
      membership: "Membership",
      licensing: "Licensing",
      collaboration: "Collaboration or guest feature",
      accessibility: "Accessibility request",
      privacy: "Privacy question"
    };
    const wanted = map[requested] || requested;
    if (wanted && [...select.options].some((option) => option.value === wanted)) select.value = wanted;
  }

  function initYear() {
    qsa("[data-current-year]").forEach((element) => {
      element.textContent = String(new Date().getFullYear());
    });
  }

  applyPreferences();
  document.addEventListener("DOMContentLoaded", () => {
    initNavigation();
    initAgeGate();
    initAccessibility();
    initSpotifyLinks();
    initSpotifyEmbeds();
    initContactTopic();
    initYear();
  });
})();
