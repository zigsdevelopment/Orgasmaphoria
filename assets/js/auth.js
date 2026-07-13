(() => {
  "use strict";
  const A = () => window.ORG_APP;
  const qs = (selector, scope = document) => scope.querySelector(selector);
  const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  function status(target, message, type = "info") {
    if (!target) return;
    target.textContent = message;
    target.className = `form-status form-status--${type}`;
    target.hidden = false;
  }

  function showAuthPanel(name) {
    qsa("[data-auth-panel]").forEach((panel) => { panel.hidden = panel.dataset.authPanel !== name; });
    qsa("[data-auth-tab]").forEach((tab) => {
      const selected = tab.dataset.authTab === name;
      tab.classList.toggle("is-active", selected);
      tab.setAttribute("aria-selected", String(selected));
    });
  }

  function initAuthTabs() {
    const requested = location.hash === "#register" ? "register" : location.hash === "#reset" ? "reset" : "login";
    showAuthPanel(requested);
    qsa("[data-auth-tab]").forEach((tab) => tab.addEventListener("click", () => {
      const name = tab.dataset.authTab;
      showAuthPanel(name);
      history.replaceState(null, "", name === "login" ? "auth.html" : `#${name}`);
    }));
  }

  function serviceState() {
    const banner = qs("[data-account-service-state]");
    if (!banner) return;
    if (A().configured) banner.remove();
    else {
      banner.hidden = false;
      qsa("form", qs("main")).forEach((form) => {
        qsa("input, button, select", form).forEach((field) => { field.disabled = true; });
      });
    }
  }

  async function initAuthForms() {
    const client = A().client;
    if (!client) return;
    const returnTo = new URLSearchParams(location.search).get("return") || "dashboard.html";

    qs("[data-login-form]")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const target = qs("[data-login-status]");
      const data = new FormData(form);
      status(target, "Signing in…");
      const { error } = await client.auth.signInWithPassword({ email: String(data.get("email") || "").trim(), password: String(data.get("password") || "") });
      if (error) return status(target, error.message, "error");
      status(target, "Signed in. Redirecting…", "success");
      location.href = returnTo;
    });

    qs("[data-register-form]")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const target = qs("[data-register-status]");
      const data = new FormData(form);
      const password = String(data.get("password") || "");
      const confirm = String(data.get("confirmPassword") || "");
      if (password.length < 10 || !/[A-Z]/.test(password) || !/[0-9]/.test(password)) return status(target, "Use at least 10 characters with one capital letter and one number.", "error");
      if (password !== confirm) return status(target, "The passwords do not match.", "error");
      if (!data.get("adult") || !data.get("terms")) return status(target, "Confirm the age and policy checkboxes to continue.", "error");
      status(target, "Creating your account…");
      const email = String(data.get("email") || "").trim();
      const { data: result, error } = await client.auth.signUp({
        email,
        password,
        options: {
          emailRedirectTo: `${location.origin}${location.pathname.replace(/auth\.html$/, "dashboard.html")}`,
          data: {
            display_name: String(data.get("displayName") || "").trim(),
            username: String(data.get("username") || "").trim().toLowerCase(),
            adult_confirmed: true
          }
        }
      });
      if (error) return status(target, error.message, "error");
      if (result.session) {
        status(target, "Account created. Redirecting…", "success");
        location.href = returnTo;
      } else {
        form.reset();
        status(target, "Check your email to confirm your account, then sign in.", "success");
      }
    });

    qs("[data-reset-form]")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const target = qs("[data-reset-status]");
      const email = String(new FormData(form).get("email") || "").trim();
      status(target, "Sending reset instructions…");
      const { error } = await client.auth.resetPasswordForEmail(email, { redirectTo: `${location.origin}${location.pathname.replace(/auth\.html$/, "account.html")}#password` });
      if (error) return status(target, error.message, "error");
      status(target, "Check your email for password reset instructions.", "success");
    });
  }

  async function requirePageSession() {
    await A().refreshSession();
    return A().requireSession(location.href);
  }

  function membershipName(tier) {
    return A().CONTENT.memberships.find((item) => item.slug === tier)?.name || "Listener";
  }

  async function initDashboard() {
    if (document.body.dataset.page !== "dashboard") return;
    const current = await requirePageSession();
    if (!current) return;
    const profile = A().getProfile();
    qsa("[data-profile-name]").forEach((element) => { element.textContent = profile?.display_name || current.user.email; });
    qsa("[data-profile-initials]").forEach((element) => {
      const name = profile?.display_name || current.user.email || "Member";
      element.textContent = name.split(/\s+/).map((part) => part[0]).join("").slice(0, 2).toUpperCase();
    });

    const client = A().client;
    const [membershipResult, resourceResult, orderResult] = await Promise.all([
      client.from("current_membership").select("*").maybeSingle(),
      client.from("resources").select("id,title,subtitle,content_type,access_level,created_at").eq("status", "published").order("created_at", { ascending: false }).limit(4),
      client.from("orders").select("id,total_cents,status,created_at").order("created_at", { ascending: false }).limit(4)
    ]);

    const membership = membershipResult.data;
    const tier = membership?.tier_slug || "listener";
    qs("[data-membership-name]").textContent = membershipName(tier);
    qs("[data-membership-status]").textContent = membership?.status === "active" ? "Active membership" : tier === "listener" ? "Free account" : "Membership status pending";
    const manage = qs("[data-membership-manage]");
    if (manage) manage.hidden = tier === "listener";

    const resources = resourceResult.data || [];
    const resourceTarget = qs("[data-dashboard-resources]");
    resourceTarget.innerHTML = resources.length ? resources.map((item) => `
      <a class="compact-row" href="library.html#${A().escapeHTML(item.id)}"><span class="mini-icon">${A().escapeHTML((item.content_type || "R")[0])}</span><span><strong>${A().escapeHTML(item.title)}</strong><small>${A().escapeHTML(item.subtitle || item.access_level)}</small></span><span>→</span></a>`).join("") : '<div class="empty-state"><strong>No resources are available yet.</strong><p>New member content will appear here when published.</p></div>';

    const orders = orderResult.data || [];
    const orderTarget = qs("[data-dashboard-orders]");
    orderTarget.innerHTML = orders.length ? orders.map((order) => `
      <div class="compact-row"><span class="mini-icon">$</span><span><strong>${A().formatMoney(order.total_cents / 100)}</strong><small>${A().formatDate(order.created_at)} · ${A().escapeHTML(order.status)}</small></span><span>✓</span></div>`).join("") : '<div class="empty-state"><strong>No purchases yet.</strong><p>Your completed digital purchases will appear here.</p><a class="button button--ghost" href="store.html">Browse the store</a></div>';

    const staffLink = qs("[data-staff-link]");
    if (staffLink) staffLink.hidden = !(await A().hasPermission("manage_accounts")) && !(await A().hasPermission("manage_content"));
  }

  async function initAccount() {
    if (document.body.dataset.page !== "account") return;
    const current = await requirePageSession();
    if (!current) return;
    const client = A().client;
    const profile = A().getProfile() || {};
    const form = qs("[data-profile-form]");
    if (form) {
      form.elements.displayName.value = profile.display_name || "";
      form.elements.username.value = profile.username || "";
      form.elements.bio.value = profile.bio || "";
      form.elements.interests.value = Array.isArray(profile.interests) ? profile.interests.join(", ") : "";
      form.elements.directoryVisibility.value = profile.directory_visibility || "members";
      form.elements.allowMessages.value = profile.allow_messages || "members";
      form.elements.showOnline.checked = profile.show_online !== false;
      form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const target = qs("[data-profile-status]");
        const data = new FormData(form);
        status(target, "Saving changes…");
        const payload = {
          display_name: String(data.get("displayName") || "").trim(),
          username: String(data.get("username") || "").trim().toLowerCase(),
          bio: String(data.get("bio") || "").trim(),
          interests: String(data.get("interests") || "").split(",").map((item) => item.trim()).filter(Boolean).slice(0, 12),
          directory_visibility: String(data.get("directoryVisibility") || "members"),
          allow_messages: String(data.get("allowMessages") || "members"),
          show_online: data.get("showOnline") === "on",
          updated_at: new Date().toISOString()
        };
        const { error } = await client.from("profiles").update(payload).eq("id", current.user.id);
        if (error) return status(target, error.message, "error");
        await A().refreshSession();
        status(target, "Profile and privacy settings saved.", "success");
      });
    }

    qs("[data-password-form]")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const target = qs("[data-password-status]");
      const data = new FormData(form);
      const password = String(data.get("password") || "");
      if (password.length < 10 || !/[A-Z]/.test(password) || !/[0-9]/.test(password)) return status(target, "Use at least 10 characters with one capital letter and one number.", "error");
      if (password !== data.get("confirmPassword")) return status(target, "The passwords do not match.", "error");
      status(target, "Updating password…");
      const { error } = await client.auth.updateUser({ password });
      if (error) return status(target, error.message, "error");
      form.reset();
      status(target, "Password updated.", "success");
    });

    qsa("[data-sign-out]").forEach((button) => button.addEventListener("click", async () => {
      await client.auth.signOut();
      location.href = "index.html";
    }));
  }

  document.addEventListener("DOMContentLoaded", async () => {
    initAuthTabs();
    serviceState();
    await initAuthForms();
    await initDashboard();
    await initAccount();
  });
})();
