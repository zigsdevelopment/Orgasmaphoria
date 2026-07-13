(() => {
  "use strict";

  const app = () => window.ORG_APP;

  function qs(selector, root = document) { return root.querySelector(selector); }
  function qsa(selector, root = document) { return [...root.querySelectorAll(selector)]; }

  function showInlineStatus(target, message, kind = "info") {
    if (!target) return;
    target.textContent = message;
    target.className = `form-status form-status--${kind}`;
    target.hidden = false;
  }

  function initHome() {
    const A = app();
    qs("[data-share-artist]")?.addEventListener("click", async () => {
      const shareData = { title: "Orgasmaphoria", text: "Listen to Orgasmaphoria on Spotify.", url: A.DATA.site.spotifyArtist };
      try {
        if (navigator.share) await navigator.share(shareData);
        else {
          await navigator.clipboard.writeText(A.DATA.site.spotifyArtist);
          A.toast("Spotify link copied.", "success");
        }
      } catch (error) {
        if (error.name !== "AbortError") A.toast("The link could not be shared.", "error");
      }
    });

    const featured = qs("[data-featured-library]");
    if (featured) {
      const items = A.DATA.library.filter((item) => item.featured).slice(0, 3);
      featured.innerHTML = items.map((item) => `
        <article class="resource-card">
          <div class="resource-card__art"><span>${A.escapeHTML(item.type)}</span><strong>${A.escapeHTML(item.title.slice(0, 1))}</strong></div>
          <div class="resource-card__content"><span class="access-pill">${A.escapeHTML(item.accessLabel)}</span><h3>${A.escapeHTML(item.title)}</h3><p>${A.escapeHTML(item.description)}</p><a class="text-link" href="library.html">Explore the library →</a></div>
        </article>`).join("");
    }

    const eventTarget = qs("[data-next-event]");
    if (eventTarget) {
      const event = [...A.DATA.events].sort((a, b) => a.date.localeCompare(b.date))[0];
      eventTarget.innerHTML = `
        <div class="event-preview__date"><strong>${new Date(`${event.date}T12:00:00`).toLocaleDateString("en-US", { day: "2-digit" })}</strong><span>${new Date(`${event.date}T12:00:00`).toLocaleDateString("en-US", { month: "short" })}</span></div>
        <div><span class="access-pill">${A.escapeHTML(event.accessLabel)}</span><h3>${A.escapeHTML(event.title)}</h3><p>${A.formatDate(event.date)} · ${A.escapeHTML(event.time)} · ${A.escapeHTML(event.location)}</p></div>
        <a class="button button--ghost" href="events.html">View invitation</a>`;
    }
  }

  function initMusic() {
    const A = app();
    const frame = qs("[data-spotify-embed]");
    if (frame) frame.src = A.DATA.site.spotifyEmbed;
    qs("[data-share-artist]")?.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(A.DATA.site.spotifyArtist);
        A.toast("Artist link copied.", "success");
      } catch {
        A.toast("Open Spotify and copy the artist link from there.");
      }
    });
  }

  function initLogin() {
    const A = app();
    const params = new URLSearchParams(location.search);
    const returnTo = params.get("return") || "dashboard.html";
    const tabs = qsa("[data-auth-tab]");
    const panels = qsa("[data-auth-panel]");

    function openTab(id) {
      tabs.forEach((tab) => {
        const active = tab.dataset.authTab === id;
        tab.classList.toggle("is-active", active);
        tab.setAttribute("aria-selected", String(active));
      });
      panels.forEach((panel) => (panel.hidden = panel.dataset.authPanel !== id));
      history.replaceState(null, "", id === "register" ? "#register" : location.pathname + location.search);
    }
    tabs.forEach((tab) => tab.addEventListener("click", () => openTab(tab.dataset.authTab)));
    if (location.hash === "#register") openTab("register");

    qsa("[data-demo-login]").forEach((button) => button.addEventListener("click", () => {
      A.signInAs(button.dataset.demoLogin);
      A.toast("Demo account opened.", "success");
      setTimeout(() => (location.href = returnTo), 250);
    }));

    const signInForm = qs("[data-sign-in-form]");
    signInForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const status = qs("[data-sign-in-status]");
      const formData = new FormData(signInForm);
      try {
        await A.authenticateLocal(formData.get("identity"), formData.get("password"));
        showInlineStatus(status, "Signed in. Redirecting...", "success");
        setTimeout(() => (location.href = returnTo), 300);
      } catch (error) {
        showInlineStatus(status, error.message, "error");
      }
    });

    const registerForm = qs("[data-register-form]");
    registerForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const status = qs("[data-register-status]");
      const formData = new FormData(registerForm);
      const password = String(formData.get("password"));
      if (password.length < 10 || !/[A-Z]/.test(password) || !/[0-9]/.test(password)) {
        showInlineStatus(status, "Use at least 10 characters with one capital letter and one number.", "error");
        return;
      }
      if (password !== formData.get("confirmPassword")) {
        showInlineStatus(status, "The passwords do not match.", "error");
        return;
      }
      if (!formData.get("adult") || !formData.get("terms")) {
        showInlineStatus(status, "Confirm the age and terms checkboxes to continue.", "error");
        return;
      }
      try {
        await A.registerUser({
          displayName: formData.get("displayName"),
          username: formData.get("username"),
          email: formData.get("email"),
          password
        });
        showInlineStatus(status, "Local demo account created. Redirecting...", "success");
        setTimeout(() => (location.href = returnTo), 350);
      } catch (error) {
        showInlineStatus(status, error.message, "error");
      }
    });
  }

  function initDashboard() {
    const A = app();
    const user = A.requireUser();
    if (!user) return;
    qsa("[data-user-name]").forEach((element) => (element.textContent = user.displayName));
    qsa("[data-user-tier]").forEach((element) => (element.textContent = user.role === "staff" ? "Staff" : A.DATA.tiers.find((tier) => tier.id === user.tier)?.name || "Member"));
    qs("[data-user-initials]").textContent = user.initials;

    const saves = A.getSavedItems();
    const savedTarget = qs("[data-dashboard-saved]");
    const savedItems = A.DATA.library.filter((item) => saves.includes(item.id));
    savedTarget.innerHTML = savedItems.length ? savedItems.slice(0, 3).map((item) => `
      <a class="compact-row" href="library.html#${item.id}"><span class="mini-icon">${A.escapeHTML(item.type.slice(0, 1))}</span><span><strong>${A.escapeHTML(item.title)}</strong><small>${A.escapeHTML(item.type)} · ${A.escapeHTML(item.accessLabel)}</small></span><span>→</span></a>`).join("") : '<div class="empty-state"><strong>No saved resources yet</strong><p>Use the save button in the member library to build your list.</p><a class="button button--ghost" href="library.html">Browse library</a></div>';

    const nextEvent = A.DATA.events.find((event) => A.canAccess(event.access, user)) || A.DATA.events[0];
    qs("[data-dashboard-event]").innerHTML = `
      <span class="access-pill">${A.escapeHTML(nextEvent.accessLabel)}</span><h3>${A.escapeHTML(nextEvent.title)}</h3><p>${A.formatDate(nextEvent.date)} · ${A.escapeHTML(nextEvent.time)}</p><p>${A.escapeHTML(nextEvent.description)}</p><a class="button button--ghost" href="events.html#${nextEvent.id}">View event</a>`;

    const available = [...A.DATA.library, ...A.getUploadedMeta()].filter((item) => A.canAccess(item.access, user)).slice(0, 4);
    qs("[data-dashboard-library]").innerHTML = available.map((item) => `<a class="dashboard-resource" href="library.html#${item.id}"><span>${A.escapeHTML(item.type || "Document")}</span><strong>${A.escapeHTML(item.title)}</strong><small>${A.escapeHTML(item.accessLabel || item.access)}</small></a>`).join("");

    const privacy = A.getPrivacy(user);
    const completion = [user.bio && user.bio !== "New Orgasmaphoria community member.", user.city, user.interests?.length, privacy.profileVisibility].filter(Boolean).length * 25;
    qs("[data-profile-progress]").style.setProperty("--progress", `${completion}%`);
    qs("[data-profile-percent]").textContent = `${completion}%`;
  }

  function mergedLibrary() {
    const A = app();
    const user = A.getCurrentUser();
    const uploads = A.getUploadedMeta()
      .filter((item) => item.status === "published" || user?.role === "staff")
      .map((item) => ({ ...item, uploaded: true }));
    return [...A.DATA.library, ...uploads];
  }

  function initLibrary() {
    const A = app();
    const target = qs("[data-library-grid]");
    const queryInput = qs("[data-library-search]");
    const typeFilter = qs("[data-library-type]");
    const accessFilter = qs("[data-library-access]");
    const user = A.getCurrentUser();

    function render() {
      const query = queryInput.value.trim().toLowerCase();
      const type = typeFilter.value;
      const access = accessFilter.value;
      const items = mergedLibrary().filter((item) => {
        const haystack = `${item.title} ${item.subtitle || ""} ${item.description || ""} ${(item.tags || []).join(" ")} ${item.type || ""}`.toLowerCase();
        return (!query || haystack.includes(query)) && (!type || item.type === type) && (!access || item.access === access);
      });
      qs("[data-library-count]").textContent = `${items.length} resource${items.length === 1 ? "" : "s"}`;
      if (!items.length) {
        target.innerHTML = '<div class="empty-state empty-state--wide"><strong>No resources match those filters.</strong><p>Try a broader search or clear one of the filters.</p></div>';
        return;
      }
      const saved = A.getSavedItems();
      target.innerHTML = items.map((item) => {
        const permitted = A.canAccess(item.access, user);
        return `
          <article class="library-card" id="${A.escapeHTML(item.id)}">
            <div class="library-card__top"><span class="content-type">${A.escapeHTML(item.type || "Document")}</span><span class="access-pill access-pill--${A.escapeHTML(item.access)}">${A.escapeHTML(item.accessLabel || item.access)}</span></div>
            <div class="library-card__art"><span>${A.escapeHTML((item.type || "D").slice(0, 1))}</span><i>${A.escapeHTML(item.format || "FILE")}</i></div>
            <div class="library-card__content"><p class="eyebrow">${A.escapeHTML(item.subtitle || "Member resource")}</p><h2>${A.escapeHTML(item.title)}</h2><p>${A.escapeHTML(item.description)}</p><div class="tag-row">${(item.tags || []).map((tag) => `<span>${A.escapeHTML(tag)}</span>`).join("")}</div></div>
            <div class="library-card__actions">
              <button class="button ${permitted ? "button--primary" : "button--locked"}" type="button" data-open-resource="${A.escapeHTML(item.id)}">${permitted ? "Open resource" : "Locked"}</button>
              <button class="save-button ${saved.includes(item.id) ? "is-saved" : ""}" type="button" data-save-resource="${A.escapeHTML(item.id)}" aria-pressed="${saved.includes(item.id)}">${saved.includes(item.id) ? "Saved" : "Save"}</button>
            </div>
          </article>`;
      }).join("");
      bindLibraryButtons();
    }

    async function openResource(item) {
      const current = A.getCurrentUser();
      if (!current) {
        const dialog = A.openDialog({ title: "Member account required", body: "<p>Create a free account or sign in to see member resources and access settings.</p>", actions: [{ label: "Not now", onClick: (wrapper) => wrapper.remove() }, { label: "Sign in", className: "button--primary", href: `login.html?return=${encodeURIComponent(`library.html#${item.id}`)}` }] });
        return dialog;
      }
      if (!A.canAccess(item.access, current)) {
        const tierName = item.access === "inner" ? "Inner Circle" : item.access === "patron" ? "Velvet Patron" : "Staff";
        A.openDialog({ title: `${tierName} access`, body: `<p>This resource is included with ${tierName} access. The static prototype demonstrates access rules, but subscription billing must be connected before launch.</p>`, actions: [{ label: "Close", onClick: (wrapper) => wrapper.remove() }, { label: "View memberships", className: "button--primary", href: "community.html#plans" }] });
        return;
      }
      if (item.uploaded) {
        const stored = await A.getUploadedFile(item.id);
        if (!stored?.blob) {
          A.toast("The local demo file is no longer available in this browser.", "error");
          return;
        }
        const url = URL.createObjectURL(stored.blob);
        window.open(url, "_blank", "noopener");
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      } else {
        window.open(item.file, "_blank", "noopener");
      }
    }

    function bindLibraryButtons() {
      qsa("[data-open-resource]").forEach((button) => button.addEventListener("click", () => {
        const item = mergedLibrary().find((entry) => entry.id === button.dataset.openResource);
        if (item) openResource(item);
      }));
      qsa("[data-save-resource]").forEach((button) => button.addEventListener("click", () => {
        if (!A.getCurrentUser()) {
          location.href = `login.html?return=${encodeURIComponent("library.html")}`;
          return;
        }
        const saved = A.toggleSaved(button.dataset.saveResource);
        button.classList.toggle("is-saved", saved);
        button.setAttribute("aria-pressed", String(saved));
        button.textContent = saved ? "Saved" : "Save";
      }));
    }

    [queryInput, typeFilter, accessFilter].forEach((control) => control?.addEventListener(control.tagName === "INPUT" ? "input" : "change", render));
    qs("[data-library-clear]")?.addEventListener("click", () => {
      queryInput.value = "";
      typeFilter.value = "";
      accessFilter.value = "";
      render();
    });
    render();
    if (location.hash) setTimeout(() => qs(location.hash)?.scrollIntoView({ behavior: "smooth", block: "center" }), 200);
  }

  function initEvents() {
    const A = app();
    const target = qs("[data-events-list]");
    const user = A.getCurrentUser();
    const rsvps = A.parse(A.KEYS.rsvps, []);

    target.innerHTML = A.DATA.events.map((event) => {
      const accessible = A.canAccess(event.access, user);
      const attending = rsvps.includes(event.id);
      const date = new Date(`${event.date}T12:00:00`);
      return `
        <article class="event-card" id="${event.id}">
          <div class="event-card__date"><strong>${date.toLocaleDateString("en-US", { day: "2-digit" })}</strong><span>${date.toLocaleDateString("en-US", { month: "short", year: "numeric" })}</span></div>
          <div class="event-card__body"><div class="event-card__meta"><span class="access-pill access-pill--${event.access}">${A.escapeHTML(event.accessLabel)}</span><span>${event.attending} interested · ${event.capacity} capacity</span></div><h2>${A.escapeHTML(event.title)}</h2><p class="event-card__when">${A.escapeHTML(event.time)} · ${A.escapeHTML(event.location)}</p><p>${A.escapeHTML(event.description)}</p><div class="button-row"><button class="button ${accessible ? "button--primary" : "button--locked"}" type="button" data-rsvp="${event.id}">${attending ? "RSVP saved" : accessible ? "RSVP" : "Membership required"}</button><a class="button button--ghost" href="${event.calendar}" download>Add to calendar</a>${event.invite ? `<a class="text-link" href="${event.invite}" target="_blank">View invitation PDF ↗</a>` : ""}</div></div>
        </article>`;
    }).join("");

    qsa("[data-rsvp]").forEach((button) => button.addEventListener("click", () => {
      const event = A.DATA.events.find((item) => item.id === button.dataset.rsvp);
      const current = A.getCurrentUser();
      if (!current) {
        location.href = `login.html?return=${encodeURIComponent(`events.html#${event.id}`)}`;
        return;
      }
      if (!A.canAccess(event.access, current)) {
        A.openDialog({ title: "Membership access required", body: `<p>${A.escapeHTML(event.title)} is available to ${A.escapeHTML(event.accessLabel)} members.</p>`, actions: [{ label: "Close", onClick: (wrapper) => wrapper.remove() }, { label: "View memberships", className: "button--primary", href: "community.html#plans" }] });
        return;
      }
      let next = A.parse(A.KEYS.rsvps, []);
      if (next.includes(event.id)) next = next.filter((id) => id !== event.id);
      else next.push(event.id);
      A.store(A.KEYS.rsvps, next);
      button.textContent = next.includes(event.id) ? "RSVP saved" : "RSVP";
      A.toast(next.includes(event.id) ? "RSVP saved to this browser." : "RSVP removed.", "success");
    }));
    if (location.hash) setTimeout(() => qs(location.hash)?.scrollIntoView({ behavior: "smooth", block: "center" }), 180);
  }

  function initStore() {
    const A = app();
    const target = qs("[data-product-grid]");
    target.innerHTML = A.DATA.products.map((product) => `
      <article class="product-card"><div class="product-card__art"><span>${A.escapeHTML(product.category)}</span><strong>${A.escapeHTML(product.title.slice(0, 1))}</strong></div><div class="product-card__body"><p class="eyebrow">Sample product</p><h2>${A.escapeHTML(product.title)}</h2><p>${A.escapeHTML(product.description)}</p><dl class="product-details"><div><dt>Format</dt><dd>${A.escapeHTML(product.format)}</dd></div><div><dt>Access</dt><dd>${A.escapeHTML(product.accessNote)}</dd></div></dl></div><div class="product-card__footer"><strong>${A.formatMoney(product.price)}</strong><button class="button button--primary" type="button" data-add-product="${product.id}">Add to bag</button></div></article>`).join("");
    qsa("[data-add-product]").forEach((button) => button.addEventListener("click", () => A.addToCart(button.dataset.addProduct)));

    function renderCart() {
      const cart = A.getCart();
      const list = qs("[data-cart-list]");
      if (!cart.length) {
        list.innerHTML = '<div class="empty-state"><strong>Your bag is empty</strong><p>Add a sample product to test the storefront flow.</p></div>';
      } else {
        list.innerHTML = cart.map((item) => {
          const product = A.DATA.products.find((entry) => entry.id === item.productId);
          return `<div class="cart-row"><div><strong>${A.escapeHTML(product.title)}</strong><small>${A.escapeHTML(product.format)}</small></div><label>Qty <input type="number" min="0" max="9" value="${item.quantity}" data-cart-quantity="${product.id}"></label><span>${A.formatMoney(product.price * item.quantity)}</span><button class="icon-button" type="button" data-cart-remove="${product.id}" aria-label="Remove ${A.escapeHTML(product.title)}">×</button></div>`;
        }).join("");
      }
      const total = cart.reduce((sum, item) => {
        const product = A.DATA.products.find((entry) => entry.id === item.productId);
        return sum + product.price * item.quantity;
      }, 0);
      qs("[data-cart-total]").textContent = A.formatMoney(total);
      qs("[data-demo-checkout]").disabled = cart.length === 0;
      qsa("[data-cart-quantity]").forEach((input) => input.addEventListener("change", () => { A.updateCart(input.dataset.cartQuantity, input.value); renderCart(); }));
      qsa("[data-cart-remove]").forEach((button) => button.addEventListener("click", () => { A.updateCart(button.dataset.cartRemove, 0); renderCart(); }));
    }
    document.addEventListener("org:cart-changed", renderCart);
    renderCart();

    qs("[data-demo-checkout]")?.addEventListener("click", () => {
      const cart = A.getCart();
      if (!cart.length) return;
      A.openDialog({
        title: "Checkout integration required",
        body: "<p>This storefront is fully interactive for testing, but it does not collect payment information. Connect Stripe Checkout or another approved payment provider before public launch.</p><p>No payment has been charged and no order has been created.</p>",
        actions: [{ label: "Keep testing", onClick: (wrapper) => wrapper.remove() }, { label: "Clear demo bag", className: "button--primary", onClick: (wrapper) => { A.clearCart(); wrapper.remove(); A.toast("Demo bag cleared."); } }]
      });
    });
  }

  function initCommunity() {
    const A = app();
    const target = qs("[data-plan-grid]");
    target.innerHTML = A.DATA.tiers.map((tier, index) => `
      <article class="plan-card ${index === 1 ? "plan-card--featured" : ""}">${index === 1 ? '<span class="plan-card__flag">Core membership</span>' : ""}<p class="eyebrow">Membership level ${index + 1}</p><h2>${A.escapeHTML(tier.name)}</h2><strong class="plan-price">${A.escapeHTML(tier.price)}</strong><p>${A.escapeHTML(tier.description)}</p><ul class="check-list">${tier.features.map((feature) => `<li>${A.escapeHTML(feature)}</li>`).join("")}</ul><button class="button ${index === 1 ? "button--primary" : "button--ghost"}" type="button" data-plan="${tier.id}">${tier.id === "listener" ? "Create free account" : "Preview membership"}</button></article>`).join("");
    qsa("[data-plan]").forEach((button) => button.addEventListener("click", () => {
      if (button.dataset.plan === "listener") location.href = "login.html#register";
      else A.openDialog({ title: "Membership billing is not connected", body: `<p>The ${A.escapeHTML(A.DATA.tiers.find((tier) => tier.id === button.dataset.plan).name)} tier is ready for a subscription provider, entitlement rules, renewals, cancellation, and member billing history. Those require a secure backend.</p>`, actions: [{ label: "Close", onClick: (wrapper) => wrapper.remove() }, { label: "Open demo account", className: "button--primary", href: "login.html" }] });
    }));
  }

  function visibleMembersFor(user) {
    const A = app();
    const blocked = A.parse(A.KEYS.blocked, []);
    return A.getAllUsers().filter((member) => {
      const privacy = A.getPrivacy(member);
      return member.id !== user?.id && !blocked.includes(member.id) && privacy.profileVisibility !== "hidden" && member.role !== "staff";
    });
  }

  function initMembers() {
    const A = app();
    const user = A.requireUser();
    if (!user) return;
    const target = qs("[data-member-grid]");
    const search = qs("[data-member-search]");
    const interest = qs("[data-member-interest]");

    const interests = [...new Set(A.getAllUsers().flatMap((member) => member.interests || []))].sort();
    interest.innerHTML += interests.map((item) => `<option value="${A.escapeHTML(item)}">${A.escapeHTML(item)}</option>`).join("");

    function render() {
      const query = search.value.trim().toLowerCase();
      const selected = interest.value;
      const members = visibleMembersFor(user).filter((member) => {
        const text = `${member.displayName} ${member.username} ${member.bio} ${(member.interests || []).join(" ")}`.toLowerCase();
        return (!query || text.includes(query)) && (!selected || member.interests?.includes(selected));
      });
      target.innerHTML = members.length ? members.map((member) => {
        const privacy = A.getPrivacy(member);
        return `<article class="member-card"><div class="member-card__top"><span class="profile-avatar profile-avatar--large">${A.escapeHTML(member.initials)}</span><span class="presence ${member.online && privacy.showOnline !== false ? "is-online" : ""}" aria-label="${member.online ? "Online" : "Offline"}"></span></div><div><span class="access-pill">${A.escapeHTML(member.tier === "patron" ? "Velvet Patron" : member.tier === "inner" ? "Inner Circle" : "Listener")}</span><h2>${A.escapeHTML(member.displayName)}</h2><p class="handle">@${A.escapeHTML(member.username)}</p><p>${A.escapeHTML(member.bio)}</p><div class="tag-row">${(member.interests || []).slice(0, 4).map((item) => `<span>${A.escapeHTML(item)}</span>`).join("")}</div></div><div class="member-card__actions"><a class="button button--ghost" href="profile.html?user=${encodeURIComponent(member.id)}">View profile</a>${member.allowMessages !== "nobody" ? `<a class="button button--primary" href="messages.html?user=${encodeURIComponent(member.id)}">Message</a>` : ""}</div></article>`;
      }).join("") : '<div class="empty-state empty-state--wide"><strong>No members match that search.</strong><p>Try another name or interest.</p></div>';
    }
    search.addEventListener("input", render);
    interest.addEventListener("change", render);
    render();
  }

  function initProfile() {
    const A = app();
    const current = A.requireUser();
    if (!current) return;
    const params = new URLSearchParams(location.search);
    const targetId = params.get("user") || current.id;
    const user = A.getUserById(targetId);
    if (!user) {
      qs("[data-profile-view]").innerHTML = '<div class="empty-state"><strong>Profile not found.</strong><p>This account may no longer be visible.</p><a href="members.html" class="button button--ghost">Return to directory</a></div>';
      return;
    }
    const mine = user.id === current.id;
    const privacy = A.getPrivacy(user);
    if (!mine && privacy.profileVisibility === "hidden") {
      qs("[data-profile-view]").innerHTML = '<div class="empty-state wrap"><strong>This profile is private.</strong><p>The member has chosen not to appear in the directory.</p><a class="button button--ghost" href="members.html">Return to directory</a></div>';
      return;
    }
    qs("[data-profile-view]").innerHTML = `
      <div class="profile-hero__identity"><span class="profile-avatar profile-avatar--xl">${A.escapeHTML(user.initials)}</span><div><span class="access-pill">${A.escapeHTML(user.role === "staff" ? "Staff" : user.tier === "patron" ? "Velvet Patron" : user.tier === "inner" ? "Inner Circle" : "Listener")}</span><h1>${A.escapeHTML(user.displayName)}</h1><p class="handle">@${A.escapeHTML(user.username)}</p>${privacy.showOnline !== false ? `<span class="presence-label"><i class="presence ${user.online ? "is-online" : ""}"></i>${user.online ? "Online" : "Offline"}</span>` : ""}</div></div>
      <div class="profile-layout"><section class="panel"><p class="eyebrow">About</p><h2>${mine ? "Your profile" : `About ${A.escapeHTML(user.displayName)}`}</h2><p>${A.escapeHTML(user.bio)}</p>${privacy.showInterests !== false ? `<div class="tag-row">${(user.interests || []).map((item) => `<span>${A.escapeHTML(item)}</span>`).join("")}</div>` : ""}<dl class="profile-facts">${privacy.showCity !== false && user.city ? `<div><dt>Location</dt><dd>${A.escapeHTML(user.city)}</dd></div>` : ""}<div><dt>Joined</dt><dd>${A.escapeHTML(user.joined)}</dd></div></dl></section><aside class="panel profile-actions"><p class="eyebrow">Actions</p>${mine ? '<a class="button button--primary" href="settings.html#profile">Edit profile and privacy</a><a class="button button--ghost" href="dashboard.html">Open dashboard</a>' : `${user.allowMessages !== "nobody" ? `<a class="button button--primary" href="messages.html?user=${encodeURIComponent(user.id)}">Send message</a>` : '<p class="muted">This member is not accepting messages.</p>'}<button class="button button--ghost" type="button" data-block-user="${user.id}">Block member</button><button class="text-button text-button--danger" type="button" data-report-user="${user.id}">Report profile</button>`}</aside></div>`;

    qs("[data-block-user]")?.addEventListener("click", () => {
      const blocked = A.parse(A.KEYS.blocked, []);
      if (!blocked.includes(user.id)) blocked.push(user.id);
      A.store(A.KEYS.blocked, blocked);
      A.toast("Member blocked in this demo browser.", "success");
      setTimeout(() => (location.href = "members.html"), 300);
    });
    qs("[data-report-user]")?.addEventListener("click", () => {
      const form = document.createElement("form");
      form.className = "stack-form";
      form.innerHTML = `<label class="field"><span>Reason</span><select name="reason"><option>Unwanted contact</option><option>Inappropriate profile content</option><option>Impersonation</option><option>Spam</option><option>Other</option></select></label><label class="field"><span>Details</span><textarea name="details" rows="4" required></textarea></label><p class="form-note">This saves a test report locally. Production reports must be delivered to a protected moderation queue.</p>`;
      const dialog = A.openDialog({ title: `Report ${user.displayName}`, body: form, actions: [{ label: "Cancel", onClick: (wrapper) => wrapper.remove() }, { label: "Submit report", className: "button--primary", onClick: (wrapper) => {
        if (!form.reportValidity()) return;
        const reports = A.parse(A.KEYS.reports, []);
        reports.push({ id: `report-${Date.now()}`, targetUser: user.id, reporter: current.id, reason: form.elements.reason.value, details: form.elements.details.value, createdAt: new Date().toISOString() });
        A.store(A.KEYS.reports, reports);
        wrapper.remove();
        A.toast("Test report submitted.", "success");
      } }] });
    });
  }

  function getConversations() {
    const A = app();
    return A.parse(A.KEYS.messages, A.DATA.conversations);
  }

  function saveConversations(conversations) {
    app().store(app().KEYS.messages, conversations);
  }

  function initMessages() {
    const A = app();
    const current = A.requireUser();
    if (!current) return;
    const list = qs("[data-conversation-list]");
    const thread = qs("[data-message-thread]");
    const composer = qs("[data-message-form]");
    const params = new URLSearchParams(location.search);
    let conversations = getConversations();
    let activeId = null;

    const requestedUser = params.get("user");
    if (requestedUser && requestedUser !== current.id) {
      let conversation = conversations.find((item) => item.participants.includes(current.id) && item.participants.includes(requestedUser));
      if (!conversation) {
        conversation = { id: `conv-${Date.now()}`, participants: [current.id, requestedUser], messages: [] };
        conversations.push(conversation);
        saveConversations(conversations);
      }
      activeId = conversation.id;
    }

    function relevant() {
      return conversations.filter((conversation) => conversation.participants.includes(current.id));
    }

    function otherUser(conversation) {
      return A.getUserById(conversation.participants.find((id) => id !== current.id));
    }

    function renderList() {
      const items = relevant().sort((a, b) => (b.messages.at(-1)?.sentAt || "").localeCompare(a.messages.at(-1)?.sentAt || ""));
      if (!activeId && items.length) activeId = items[0].id;
      list.innerHTML = items.length ? items.map((conversation) => {
        const other = otherUser(conversation);
        const last = conversation.messages.at(-1);
        return `<button class="conversation-row ${conversation.id === activeId ? "is-active" : ""}" type="button" data-conversation="${conversation.id}"><span class="profile-avatar">${A.escapeHTML(other?.initials || "?")}</span><span><strong>${A.escapeHTML(other?.displayName || "Unavailable member")}</strong><small>${A.escapeHTML(last?.text || "Start a conversation")}</small></span></button>`;
      }).join("") : '<div class="empty-state"><strong>No messages yet</strong><p>Open the member directory and choose Message to start a conversation.</p><a class="button button--ghost" href="members.html">Find members</a></div>';
      qsa("[data-conversation]").forEach((button) => button.addEventListener("click", () => { activeId = button.dataset.conversation; renderList(); renderThread(); }));
    }

    function renderThread() {
      const conversation = conversations.find((item) => item.id === activeId);
      if (!conversation) {
        thread.innerHTML = '<div class="empty-state empty-state--thread"><strong>Select a conversation</strong><p>Your messages will appear here.</p></div>';
        composer.hidden = true;
        return;
      }
      const other = otherUser(conversation);
      const privacy = other ? A.getPrivacy(other) : {};
      qs("[data-thread-name]").textContent = other?.displayName || "Unavailable member";
      qs("[data-thread-avatar]").textContent = other?.initials || "?";
      thread.innerHTML = conversation.messages.length ? conversation.messages.map((message) => {
        const sender = A.getUserById(message.sender);
        const mine = message.sender === current.id;
        return `<div class="message ${mine ? "message--mine" : ""}"><div class="message__bubble"><p>${A.escapeHTML(message.text)}</p><time>${new Date(message.sentAt).toLocaleString("en-US", { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" })}</time></div><span class="profile-avatar profile-avatar--small">${A.escapeHTML(sender?.initials || "?")}</span></div>`;
      }).join("") : '<div class="empty-state empty-state--thread"><strong>Start the conversation</strong><p>Keep messages respectful. You can block or report an account from its profile.</p></div>';
      thread.scrollTop = thread.scrollHeight;
      composer.hidden = !other || privacy.allowMessages === "nobody" || other.allowMessages === "nobody";
    }

    composer?.addEventListener("submit", (event) => {
      event.preventDefault();
      const field = composer.elements.message;
      const text = field.value.trim();
      if (!text || !activeId) return;
      const conversation = conversations.find((item) => item.id === activeId);
      conversation.messages.push({ id: `msg-${Date.now()}`, sender: current.id, text, sentAt: new Date().toISOString() });
      saveConversations(conversations);
      field.value = "";
      renderList();
      renderThread();
    });

    renderList();
    renderThread();
  }

  function initContact() {
    const A = app();
    const form = qs("[data-contact-form]");
    const status = qs("[data-contact-status]");
    const emailTargets = qsa("[data-contact-email]");
    if (A.DATA.site.contactEmail) {
      emailTargets.forEach((element) => {
        element.textContent = A.DATA.site.contactEmail;
        if (element.tagName === "A") element.href = `mailto:${A.DATA.site.contactEmail}`;
      });
    } else {
      emailTargets.forEach((element) => {
        element.textContent = "Business email coming soon";
        if (element.tagName === "A") element.removeAttribute("href");
      });
    }

    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      const data = Object.fromEntries(new FormData(form));
      const record = { id: `contact-${Date.now()}`, ...data, createdAt: new Date().toISOString(), status: "new" };
      if (A.DATA.site.formEndpoint) {
        try {
          const response = await fetch(A.DATA.site.formEndpoint, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(record) });
          if (!response.ok) throw new Error("The contact service rejected the message.");
          form.reset();
          showInlineStatus(status, "Thank you. Your message has been submitted.", "success");
        } catch (error) {
          showInlineStatus(status, `The message could not be sent: ${error.message}`, "error");
        }
      } else {
        const outbox = A.parse(A.KEYS.contact, []);
        outbox.push(record);
        A.store(A.KEYS.contact, outbox);
        form.reset();
        showInlineStatus(status, "Demo mode: your message was validated and saved to this browser for the Staff Portal preview. It was not emailed.", "success");
      }
    });
  }

  function initSettings() {
    const A = app();
    const user = A.requireUser();
    if (!user) return;
    const prefs = A.getPreferences();
    const privacy = A.getPrivacy(user);
    const accessibilityForm = qs("[data-accessibility-form]");
    const privacyForm = qs("[data-privacy-form]");
    const profileForm = qs("[data-profile-form]");

    accessibilityForm.elements.fontScale.value = String(prefs.fontScale);
    accessibilityForm.elements.theme.value = prefs.theme;
    ["contrast", "reducedMotion", "underlineLinks", "readableFont", "comfortableSpacing"].forEach((name) => (accessibilityForm.elements[name].checked = Boolean(prefs[name])));
    accessibilityForm.addEventListener("submit", (event) => {
      event.preventDefault();
      A.savePreferences({
        fontScale: Number(accessibilityForm.elements.fontScale.value),
        theme: accessibilityForm.elements.theme.value,
        contrast: accessibilityForm.elements.contrast.checked,
        reducedMotion: accessibilityForm.elements.reducedMotion.checked,
        underlineLinks: accessibilityForm.elements.underlineLinks.checked,
        readableFont: accessibilityForm.elements.readableFont.checked,
        comfortableSpacing: accessibilityForm.elements.comfortableSpacing.checked
      });
      A.toast("Accessibility settings saved.", "success");
    });

    privacyForm.elements.profileVisibility.value = privacy.profileVisibility;
    privacyForm.elements.allowMessages.value = privacy.allowMessages;
    ["showOnline", "showCity", "showInterests", "recommendations", "marketingEmail", "eventEmail"].forEach((name) => (privacyForm.elements[name].checked = Boolean(privacy[name])));
    privacyForm.addEventListener("submit", (event) => {
      event.preventDefault();
      A.savePrivacy({
        profileVisibility: privacyForm.elements.profileVisibility.value,
        allowMessages: privacyForm.elements.allowMessages.value,
        showOnline: privacyForm.elements.showOnline.checked,
        showCity: privacyForm.elements.showCity.checked,
        showInterests: privacyForm.elements.showInterests.checked,
        recommendations: privacyForm.elements.recommendations.checked,
        marketingEmail: privacyForm.elements.marketingEmail.checked,
        eventEmail: privacyForm.elements.eventEmail.checked
      }, user);
      A.toast("Privacy settings saved.", "success");
    });

    profileForm.elements.displayName.value = user.displayName;
    profileForm.elements.username.value = user.username;
    profileForm.elements.city.value = user.city || "";
    profileForm.elements.bio.value = user.bio || "";
    profileForm.elements.interests.value = (user.interests || []).join(", ");
    profileForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (!user.localDemoAccount) {
        A.toast("Built-in demo profiles are read-only. Create a local account to test profile editing.", "error");
        return;
      }
      const users = A.parse(A.KEYS.customUsers, []);
      const index = users.findIndex((item) => item.id === user.id);
      users[index] = {
        ...users[index],
        displayName: profileForm.elements.displayName.value.trim(),
        username: profileForm.elements.username.value.trim().toLowerCase(),
        city: profileForm.elements.city.value.trim(),
        bio: profileForm.elements.bio.value.trim(),
        interests: profileForm.elements.interests.value.split(",").map((item) => item.trim()).filter(Boolean).slice(0, 8)
      };
      A.store(A.KEYS.customUsers, users);
      A.toast("Profile updated.", "success");
      setTimeout(() => location.reload(), 350);
    });

    qs("[data-export-account]")?.addEventListener("click", () => {
      A.downloadJSON(`orgasmaphoria-account-${user.username}.json`, {
        exportedAt: new Date().toISOString(),
        profile: user,
        preferences: A.getPreferences(),
        privacy: A.getPrivacy(user),
        savedItems: A.getSavedItems(),
        rsvps: A.parse(A.KEYS.rsvps, []),
        messages: getConversations().filter((conversation) => conversation.participants.includes(user.id))
      });
    });
    qs("[data-reset-demo]")?.addEventListener("click", () => {
      A.openDialog({ title: "Reset local demo data?", body: "<p>This clears the saved cart, messages, RSVPs, contact test submissions, settings, and local demo accounts from this browser. It does not affect any online service.</p>", actions: [{ label: "Cancel", onClick: (wrapper) => wrapper.remove() }, { label: "Reset demo", className: "button--danger", onClick: (wrapper) => {
        const keys = [];
        for (let index = 0; index < localStorage.length; index += 1) {
          const key = localStorage.key(index);
          if (key?.startsWith("org_")) keys.push(key);
        }
        keys.forEach((key) => localStorage.removeItem(key));
        wrapper.remove();
        location.href = "index.html";
      } }] });
    });
  }

  function initStaff() {
    const A = app();
    const user = A.requireUser({ role: "staff" });
    if (!user) return;
    const uploadForm = qs("[data-staff-upload-form]");
    const tableBody = qs("[data-content-table]");
    const inbox = qs("[data-contact-inbox]");

    function renderContent() {
      const items = [...A.DATA.library, ...A.getUploadedMeta().map((item) => ({ ...item, uploaded: true }))];
      tableBody.innerHTML = items.map((item) => `<tr><td><strong>${A.escapeHTML(item.title)}</strong><small>${A.escapeHTML(item.subtitle || "")}</small></td><td>${A.escapeHTML(item.type || "Document")}</td><td><span class="access-pill access-pill--${A.escapeHTML(item.access)}">${A.escapeHTML(item.accessLabel || item.access)}</span></td><td>${item.uploaded ? "Local demo upload" : "Included sample"}</td><td>${item.uploaded ? `<button class="text-button text-button--danger" type="button" data-delete-upload="${item.id}">Delete</button>` : "Protected sample"}</td></tr>`).join("");
      qsa("[data-delete-upload]").forEach((button) => button.addEventListener("click", async () => {
        const meta = A.getUploadedMeta().filter((item) => item.id !== button.dataset.deleteUpload);
        A.setUploadedMeta(meta);
        await A.deleteUploadedFile(button.dataset.deleteUpload);
        A.toast("Local upload deleted.", "success");
        renderContent();
      }));
    }

    function renderInbox() {
      const messages = A.parse(A.KEYS.contact, []).sort((a, b) => b.createdAt.localeCompare(a.createdAt));
      inbox.innerHTML = messages.length ? messages.map((message) => `<article class="inbox-card"><div><span class="access-pill">${A.escapeHTML(message.topic || "General")}</span><h3>${A.escapeHTML(message.subject)}</h3><p><strong>${A.escapeHTML(message.name)}</strong> · ${A.escapeHTML(message.email)}</p><p>${A.escapeHTML(message.message)}</p></div><time>${new Date(message.createdAt).toLocaleString()}</time></article>`).join("") : '<div class="empty-state"><strong>No demo inquiries</strong><p>Submit the Contact form in demo mode and the message will appear here.</p><a class="button button--ghost" href="contact.html">Open contact page</a></div>';
    }

    uploadForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const status = qs("[data-upload-status]");
      const file = uploadForm.elements.file.files[0];
      if (!file) return showInlineStatus(status, "Choose a file first.", "error");
      if (file.size > 25 * 1024 * 1024) return showInlineStatus(status, "The demo upload limit is 25 MB.", "error");
      const allowed = ["application/pdf", "text/plain", "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "application/epub+zip", "image/png", "image/jpeg"];
      if (!allowed.includes(file.type) && !/\.(pdf|txt|docx|epub|png|jpe?g)$/i.test(file.name)) return showInlineStatus(status, "Use PDF, TXT, DOCX, EPUB, PNG, or JPG in this demo.", "error");
      const id = `upload-${Date.now()}`;
      const access = uploadForm.elements.access.value;
      const accessLabel = access === "listener" ? "All members" : access === "inner" ? "Inner Circle" : access === "patron" ? "Velvet Patron" : "Staff only";
      const record = {
        id,
        title: uploadForm.elements.title.value.trim(),
        subtitle: uploadForm.elements.subtitle.value.trim() || "Staff-published resource",
        description: uploadForm.elements.description.value.trim(),
        type: uploadForm.elements.type.value,
        format: file.name.split(".").pop().toUpperCase(),
        access,
        accessLabel,
        tags: uploadForm.elements.tags.value.split(",").map((tag) => tag.trim()).filter(Boolean),
        filename: file.name,
        size: file.size,
        added: new Date().toISOString().slice(0, 10),
        uploaded: true,
        status: uploadForm.elements.status.value
      };
      try {
        await A.saveUploadedFile({ id, blob: file, filename: file.name, mimeType: file.type, uploadedAt: new Date().toISOString() });
        const meta = A.getUploadedMeta();
        meta.push(record);
        A.setUploadedMeta(meta);
        uploadForm.reset();
        showInlineStatus(status, "File stored locally in this browser and published to the demo library.", "success");
        renderContent();
      } catch (error) {
        showInlineStatus(status, error.message, "error");
      }
    });

    qs("[data-clear-inbox]")?.addEventListener("click", () => {
      A.store(A.KEYS.contact, []);
      renderInbox();
      A.toast("Demo contact inbox cleared.");
    });

    renderContent();
    renderInbox();
    qs("[data-report-count]").textContent = String(A.parse(A.KEYS.reports, []).length);
  }

  document.addEventListener("DOMContentLoaded", () => {
    const page = document.body.dataset.page;
    const handlers = {
      home: initHome,
      music: initMusic,
      login: initLogin,
      dashboard: initDashboard,
      library: initLibrary,
      events: initEvents,
      store: initStore,
      community: initCommunity,
      members: initMembers,
      profile: initProfile,
      messages: initMessages,
      contact: initContact,
      settings: initSettings,
      staff: initStaff
    };
    try {
      handlers[page]?.();
    } catch (error) {
      console.error(`Page initialization failed for ${page}`, error);
      window.ORG_APP?.toast?.("A demo feature could not load. Check the browser console for details.", "error");
    }
  });
})();
