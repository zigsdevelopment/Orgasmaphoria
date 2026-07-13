(() => {
  "use strict";
  const A = () => window.ORG_APP;
  const qs = (selector, scope = document) => scope.querySelector(selector);
  const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  function inlineStatus(target, message, type = "info") {
    if (!target) return;
    target.textContent = message;
    target.className = `form-status form-status--${type}`;
    target.hidden = false;
  }

  async function requireStaff() {
    await A().refreshSession();
    const session = await A().requireSession(location.href);
    if (!session) return null;
    const allowed = await Promise.all([
      A().hasPermission("manage_accounts"),
      A().hasPermission("manage_content"),
      A().hasPermission("view_orders"),
      A().hasPermission("view_audit")
    ]);
    if (!allowed.some(Boolean)) {
      location.href = "dashboard.html";
      return null;
    }
    return { session, permissions: { accounts: allowed[0], content: allowed[1], orders: allowed[2], audit: allowed[3] } };
  }

  function initTabs(permissions) {
    qsa("[data-admin-tab]").forEach((button) => {
      const key = button.dataset.adminTab;
      if (permissions[key] === false) button.hidden = true;
      button.addEventListener("click", () => {
        qsa("[data-admin-tab]").forEach((item) => item.classList.toggle("is-active", item === button));
        qsa("[data-admin-panel]").forEach((panel) => { panel.hidden = panel.dataset.adminPanel !== key; });
      });
    });
    const first = qsa("[data-admin-tab]").find((button) => !button.hidden);
    first?.click();
  }

  async function loadAccounts() {
    const target = qs("[data-account-table]");
    if (!target) return;
    const { data, error } = await A().client.rpc("admin_list_accounts");
    if (error) {
      target.innerHTML = '<div class="empty-state"><strong>Accounts could not be loaded.</strong></div>';
      return;
    }
    const accounts = data || [];
    target.innerHTML = accounts.length ? `
      <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Account</th><th>Status</th><th>Membership</th><th>Role</th><th>Actions</th></tr></thead><tbody>
      ${accounts.map((account) => `<tr>
        <td><strong>${A().escapeHTML(account.display_name || account.email || "Member")}</strong><small>${A().escapeHTML(account.email || "")}${account.protected_admin ? " · Protected administrator" : ""}</small></td>
        <td><span class="status-chip status-chip--${A().escapeHTML(account.account_status)}">${A().escapeHTML(account.account_status)}</span></td>
        <td><select data-membership-user="${account.id}" ${account.protected_admin ? "disabled" : ""}><option value="listener" ${account.tier_slug === "listener" ? "selected" : ""}>Listener</option><option value="velvet-patron" ${account.tier_slug === "velvet-patron" ? "selected" : ""}>Velvet Patron</option><option value="inner-circle" ${account.tier_slug === "inner-circle" ? "selected" : ""}>Inner Circle</option></select></td>
        <td>${A().escapeHTML(account.role_name || "member")}</td>
        <td><div class="table-actions"><button class="button button--small button--ghost" type="button" data-permissions-user="${account.id}" ${account.protected_admin ? "disabled" : ""}>Permissions</button><button class="button button--small button--ghost" type="button" data-status-user="${account.id}" data-next-status="${account.account_status === "disabled" ? "active" : "disabled"}" ${account.protected_admin ? "disabled" : ""}>${account.account_status === "disabled" ? "Enable" : "Disable"}</button></div></td>
      </tr>`).join("")}</tbody></table></div>` : '<div class="empty-state"><strong>No accounts have been created.</strong></div>';

    qsa("[data-membership-user]", target).forEach((select) => select.addEventListener("change", async () => {
      select.disabled = true;
      const { error: updateError } = await A().client.rpc("admin_set_membership", { target_user: select.dataset.membershipUser, requested_tier: select.value });
      select.disabled = false;
      if (updateError) A().toast("Membership could not be changed.", "error");
      else A().toast("Membership access updated.", "success");
    }));

    qsa("[data-status-user]", target).forEach((button) => button.addEventListener("click", async () => {
      const action = button.dataset.nextStatus === "disabled" ? "disable" : "enable";
      if (!confirm(`Are you sure you want to ${action} this account?`)) return;
      button.disabled = true;
      const { error: updateError } = await A().client.rpc("admin_set_account_status", { target_user: button.dataset.statusUser, requested_status: button.dataset.nextStatus });
      if (updateError) A().toast("Account status could not be changed.", "error");
      else { A().toast("Account status updated.", "success"); await loadAccounts(); }
    }));

    qsa("[data-permissions-user]", target).forEach((button) => button.addEventListener("click", () => openPermissions(button.dataset.permissionsUser)));
  }

  async function openPermissions(userId) {
    const modal = qs("[data-permission-modal]");
    const form = qs("[data-permission-form]");
    const target = qs("[data-permission-list]");
    if (!modal || !form || !target) return;
    const { data, error } = await A().client.rpc("admin_get_permissions", { target_user: userId });
    if (error) return A().toast("Permissions could not be loaded.", "error");
    const values = new Map((data || []).map((item) => [item.permission_key, item.allowed]));
    target.innerHTML = A().CONTENT.permissions.map((permission) => `<label class="check-row"><input type="checkbox" name="${permission.key}" ${values.get(permission.key) ? "checked" : ""}><span>${A().escapeHTML(permission.label)}</span></label>`).join("");
    form.dataset.userId = userId;
    modal.hidden = false;
  }

  function initPermissionModal() {
    const modal = qs("[data-permission-modal]");
    const form = qs("[data-permission-form]");
    if (!modal || !form) return;
    const close = () => { modal.hidden = true; };
    qsa("[data-modal-close]", modal).forEach((button) => button.addEventListener("click", close));
    qs(".modal__backdrop", modal)?.addEventListener("click", close);
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const userId = form.dataset.userId;
      const updates = A().CONTENT.permissions.map((permission) => ({ permission_key: permission.key, allowed: form.elements[permission.key].checked }));
      const submit = qs('button[type="submit"]', form);
      submit.disabled = true;
      const { error } = await A().client.rpc("admin_replace_permissions", { target_user: userId, permission_values: updates });
      submit.disabled = false;
      if (error) return A().toast("Permissions could not be saved.", "error");
      close();
      A().toast("Account permissions updated.", "success");
    });
  }

  async function loadResources() {
    const target = qs("[data-resource-table]");
    if (!target) return;
    const { data, error } = await A().client.from("resources").select("id,title,content_type,format,access_level,status,created_at").order("created_at", { ascending: false });
    if (error) {
      target.innerHTML = '<div class="empty-state"><strong>Resources could not be loaded.</strong></div>';
      return;
    }
    target.innerHTML = (data || []).length ? `<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Resource</th><th>Type</th><th>Access</th><th>Status</th><th>Added</th></tr></thead><tbody>${data.map((item) => `<tr><td><strong>${A().escapeHTML(item.title)}</strong></td><td>${A().escapeHTML(item.content_type || item.format || "File")}</td><td>${A().escapeHTML(item.access_level)}</td><td>${A().escapeHTML(item.status)}</td><td>${A().formatDate(item.created_at)}</td></tr>`).join("")}</tbody></table></div>` : '<div class="empty-state"><strong>No resources have been published.</strong></div>';
  }

  function initResourceUpload(session) {
    const form = qs("[data-resource-upload]");
    if (!form) return;
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const statusTarget = qs("[data-upload-status]");
      const data = new FormData(form);
      const file = data.get("file");
      if (!(file instanceof File) || !file.size) return inlineStatus(statusTarget, "Choose a file to upload.", "error");
      if (file.size > 25 * 1024 * 1024) return inlineStatus(statusTarget, "Files must be 25 MB or smaller.", "error");
      const safeExt = (file.name.split(".").pop() || "bin").toLowerCase().replace(/[^a-z0-9]/g, "");
      const path = `${new Date().getUTCFullYear()}/${crypto.randomUUID()}.${safeExt}`;
      const submit = qs('button[type="submit"]', form);
      submit.disabled = true;
      inlineStatus(statusTarget, "Uploading resource…");
      const { error: storageError } = await A().client.storage.from("member-files").upload(path, file, { upsert: false, contentType: file.type || "application/octet-stream" });
      if (storageError) {
        submit.disabled = false;
        return inlineStatus(statusTarget, storageError.message, "error");
      }
      const payload = {
        title: String(data.get("title") || "").trim(),
        subtitle: String(data.get("subtitle") || "").trim(),
        description: String(data.get("description") || "").trim(),
        content_type: String(data.get("contentType") || "Document"),
        format: safeExt.toUpperCase(),
        access_level: String(data.get("accessLevel") || "listener"),
        status: String(data.get("status") || "draft"),
        tags: String(data.get("tags") || "").split(",").map((tag) => tag.trim()).filter(Boolean).slice(0, 15),
        storage_path: path,
        created_by: session.user.id
      };
      const { error: recordError } = await A().client.from("resources").insert(payload);
      submit.disabled = false;
      if (recordError) {
        await A().client.storage.from("member-files").remove([path]);
        return inlineStatus(statusTarget, recordError.message, "error");
      }
      form.reset();
      inlineStatus(statusTarget, "Resource uploaded and saved.", "success");
      await loadResources();
    });
  }

  async function loadOrders() {
    const target = qs("[data-order-table]");
    if (!target) return;
    const { data, error } = await A().client.rpc("admin_list_orders");
    if (error) {
      target.innerHTML = '<div class="empty-state"><strong>Orders could not be loaded.</strong></div>';
      return;
    }
    target.innerHTML = (data || []).length ? `<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Customer</th><th>Total</th><th>Status</th><th>Created</th></tr></thead><tbody>${data.map((order) => `<tr><td><strong>${A().escapeHTML(order.display_name || order.email || "Customer")}</strong></td><td>${A().formatMoney(order.total_cents / 100)}</td><td>${A().escapeHTML(order.status)}</td><td>${A().formatDate(order.created_at)}</td></tr>`).join("")}</tbody></table></div>` : '<div class="empty-state"><strong>No completed orders yet.</strong></div>';
  }

  async function loadAudit() {
    const target = qs("[data-audit-table]");
    if (!target) return;
    const { data, error } = await A().client.from("audit_logs").select("action,target_type,target_id,created_at,actor_id").order("created_at", { ascending: false }).limit(100);
    if (error) {
      target.innerHTML = '<div class="empty-state"><strong>Audit records could not be loaded.</strong></div>';
      return;
    }
    target.innerHTML = (data || []).length ? `<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Action</th><th>Target</th><th>Date</th></tr></thead><tbody>${data.map((item) => `<tr><td>${A().escapeHTML(item.action)}</td><td>${A().escapeHTML(item.target_type)} ${A().escapeHTML(item.target_id || "")}</td><td>${A().formatDate(item.created_at, { hour: "numeric", minute: "2-digit" })}</td></tr>`).join("")}</tbody></table></div>` : '<div class="empty-state"><strong>No audit records yet.</strong></div>';
  }

  document.addEventListener("DOMContentLoaded", async () => {
    if (document.body.dataset.page !== "staff") return;
    if (!A().configured) {
      qs("[data-admin-shell]").innerHTML = '<div class="empty-state empty-state--wide"><strong>Administration is temporarily unavailable.</strong></div>';
      return;
    }
    const access = await requireStaff();
    if (!access) return;
    initTabs(access.permissions);
    initPermissionModal();
    if (access.permissions.accounts) await loadAccounts();
    if (access.permissions.content) { await loadResources(); initResourceUpload(access.session); }
    if (access.permissions.orders) await loadOrders();
    if (access.permissions.audit) await loadAudit();
  });
})();
