(() => {
  "use strict";
  const A = () => window.ORG_APP;
  const qs = (selector, scope = document) => scope.querySelector(selector);
  const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  async function ensureMemberPage() {
    await A().refreshSession();
    return A().requireSession(location.href);
  }

  function accessName(value) {
    return ({ listener: "All members", velvet: "Velvet Patron", inner: "Inner Circle", purchase: "Purchased access", staff: "Staff only" })[value] || value;
  }

  async function initLibrary() {
    if (document.body.dataset.page !== "library") return;
    const session = await ensureMemberPage();
    if (!session) return;
    const client = A().client;
    const target = qs("[data-library-grid]");
    const search = qs("[data-library-search]");
    const type = qs("[data-library-type]");
    let resources = [];

    async function load() {
      target.innerHTML = '<div class="loading-state">Loading your library…</div>';
      const { data, error } = await client.from("resources").select("id,title,subtitle,description,content_type,format,access_level,tags,storage_path,created_at").eq("status", "published").order("created_at", { ascending: false });
      if (error) {
        target.innerHTML = '<div class="empty-state empty-state--wide"><strong>The library could not be loaded.</strong><p>Please try again later.</p></div>';
        return;
      }
      resources = data || [];
      render();
    }

    function render() {
      const query = String(search?.value || "").trim().toLowerCase();
      const selectedType = type?.value || "";
      const items = resources.filter((item) => {
        const haystack = `${item.title} ${item.subtitle || ""} ${item.description || ""} ${(item.tags || []).join(" ")}`.toLowerCase();
        return (!query || haystack.includes(query)) && (!selectedType || item.content_type === selectedType);
      });
      qs("[data-library-count]").textContent = `${items.length} resource${items.length === 1 ? "" : "s"}`;
      if (!items.length) {
        target.innerHTML = '<div class="empty-state empty-state--wide"><strong>No resources match.</strong><p>Try a different search or check back as the library grows.</p></div>';
        return;
      }
      target.innerHTML = items.map((item) => `
        <article class="library-card" id="${A().escapeHTML(item.id)}">
          <div class="library-card__top"><span class="content-type">${A().escapeHTML(item.content_type || "Resource")}</span><span class="access-pill">${A().escapeHTML(accessName(item.access_level))}</span></div>
          <div class="library-card__art"><span>${A().escapeHTML((item.content_type || "R")[0])}</span><i>${A().escapeHTML(item.format || "FILE")}</i></div>
          <div class="library-card__content"><p class="eyebrow">${A().escapeHTML(item.subtitle || "Member resource")}</p><h2>${A().escapeHTML(item.title)}</h2><p>${A().escapeHTML(item.description || "")}</p><div class="tag-row">${(item.tags || []).map((tag) => `<span>${A().escapeHTML(tag)}</span>`).join("")}</div></div>
          <div class="library-card__actions"><button class="button button--primary" type="button" data-resource-download="${A().escapeHTML(item.id)}">Open resource</button></div>
        </article>`).join("");
      qsa("[data-resource-download]", target).forEach((button) => button.addEventListener("click", async () => {
        const item = resources.find((entry) => entry.id === button.dataset.resourceDownload);
        if (!item?.storage_path) return A().toast("This resource is not available yet.", "error");
        button.disabled = true;
        button.textContent = "Opening…";
        const { data, error } = await client.storage.from("member-files").download(item.storage_path);
        button.disabled = false;
        button.textContent = "Open resource";
        if (error || !data) return A().toast("This resource could not be opened.", "error");
        const url = URL.createObjectURL(data);
        window.open(url, "_blank", "noopener");
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      }));
    }

    search?.addEventListener("input", render);
    type?.addEventListener("change", render);
    await load();
  }

  async function initMembers() {
    if (document.body.dataset.page !== "members") return;
    const session = await ensureMemberPage();
    if (!session) return;
    const client = A().client;
    const target = qs("[data-member-grid]");
    const search = qs("[data-member-search]");
    let members = [];

    async function load() {
      const { data, error } = await client.from("member_directory").select("*").order("display_name");
      if (error) {
        target.innerHTML = '<div class="empty-state empty-state--wide"><strong>The member directory could not be loaded.</strong></div>';
        return;
      }
      members = (data || []).filter((member) => member.id !== session.user.id);
      render();
    }

    function render() {
      const query = String(search?.value || "").trim().toLowerCase();
      const visible = members.filter((member) => `${member.display_name} ${member.username || ""} ${member.bio || ""} ${(member.interests || []).join(" ")}`.toLowerCase().includes(query));
      target.innerHTML = visible.length ? visible.map((member) => {
        const initials = (member.display_name || "Member").split(/\s+/).map((part) => part[0]).join("").slice(0, 2).toUpperCase();
        return `<article class="member-card"><div class="avatar">${A().escapeHTML(initials)}</div><div><h2>${A().escapeHTML(member.display_name || "Member")}</h2><p class="member-handle">@${A().escapeHTML(member.username || "member")}</p><p>${A().escapeHTML(member.bio || "Orgasmaphoria community member.")}</p><div class="tag-row">${(member.interests || []).slice(0, 4).map((tag) => `<span>${A().escapeHTML(tag)}</span>`).join("")}</div></div><div class="member-card__actions">${member.allow_messages === "members" ? `<button class="button button--ghost" type="button" data-message-member="${member.id}">Message</button>` : '<span class="access-pill">Messages off</span>'}</div></article>`;
      }).join("") : '<div class="empty-state empty-state--wide"><strong>No members match that search.</strong></div>';
      qsa("[data-message-member]", target).forEach((button) => button.addEventListener("click", async () => {
        button.disabled = true;
        const { data, error } = await client.rpc("start_conversation", { other_user_id: button.dataset.messageMember });
        button.disabled = false;
        if (error || !data) return A().toast("A conversation could not be started.", "error");
        location.href = `messages.html?conversation=${encodeURIComponent(data)}`;
      }));
    }

    search?.addEventListener("input", render);
    await load();
  }

  async function initMessages() {
    if (document.body.dataset.page !== "messages") return;
    const session = await ensureMemberPage();
    if (!session) return;
    const client = A().client;
    const list = qs("[data-conversation-list]");
    const thread = qs("[data-message-thread]");
    const heading = qs("[data-thread-heading]");
    const form = qs("[data-message-form]");
    let selected = new URLSearchParams(location.search).get("conversation");
    let channel = null;

    async function loadConversations() {
      const { data, error } = await client.rpc("get_my_conversations");
      if (error) {
        list.innerHTML = '<div class="empty-state"><strong>Messages could not be loaded.</strong></div>';
        return [];
      }
      const conversations = data || [];
      list.innerHTML = conversations.length ? conversations.map((item) => `
        <button class="conversation-row ${selected === item.conversation_id ? "is-active" : ""}" type="button" data-conversation-id="${item.conversation_id}" data-conversation-name="${A().escapeHTML(item.other_display_name || "Member")}"><span class="avatar avatar--small">${A().escapeHTML((item.other_display_name || "M").split(/\s+/).map((part) => part[0]).join("").slice(0, 2))}</span><span><strong>${A().escapeHTML(item.other_display_name || "Member")}</strong><small>${A().escapeHTML(item.last_message || "Start the conversation")}</small></span><time>${A().formatDate(item.last_message_at, { month: "short", day: "numeric" })}</time></button>`).join("") : '<div class="empty-state"><strong>No conversations yet.</strong><p>Open the member directory to find someone who accepts messages.</p><a class="button button--ghost" href="members.html">Browse members</a></div>';
      qsa("[data-conversation-id]", list).forEach((button) => button.addEventListener("click", () => openConversation(button.dataset.conversationId, button.dataset.conversationName)));
      return conversations;
    }

    async function openConversation(id, name = "Conversation") {
      selected = id;
      history.replaceState(null, "", `messages.html?conversation=${encodeURIComponent(id)}`);
      heading.textContent = name;
      form.hidden = false;
      qsa("[data-conversation-id]", list).forEach((button) => button.classList.toggle("is-active", button.dataset.conversationId === id));
      const { data, error } = await client.from("messages").select("id,sender_id,content,created_at").eq("conversation_id", id).order("created_at", { ascending: true });
      if (error) {
        thread.innerHTML = '<div class="empty-state"><strong>This conversation could not be opened.</strong></div>';
        return;
      }
      thread.innerHTML = (data || []).length ? data.map((message) => `<div class="message-bubble ${message.sender_id === session.user.id ? "message-bubble--mine" : ""}"><p>${A().escapeHTML(message.content)}</p><time>${A().formatDate(message.created_at, { hour: "numeric", minute: "2-digit" })}</time></div>`).join("") : '<div class="empty-state"><strong>No messages yet.</strong><p>Write the first message below.</p></div>';
      thread.scrollTop = thread.scrollHeight;
      if (channel) client.removeChannel(channel);
      channel = client.channel(`conversation:${id}`).on("postgres_changes", { event: "INSERT", schema: "public", table: "messages", filter: `conversation_id=eq.${id}` }, () => openConversation(id, name)).subscribe();
    }

    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!selected) return;
      const textarea = form.elements.message;
      const content = String(textarea.value || "").trim();
      if (!content) return;
      textarea.disabled = true;
      const { error } = await client.from("messages").insert({ conversation_id: selected, sender_id: session.user.id, content });
      textarea.disabled = false;
      textarea.focus();
      if (error) return A().toast("Your message could not be sent.", "error");
      textarea.value = "";
      await loadConversations();
    });

    const conversations = await loadConversations();
    if (selected) {
      const match = conversations.find((item) => item.conversation_id === selected);
      await openConversation(selected, match?.other_display_name || "Conversation");
    } else if (conversations[0]) {
      await openConversation(conversations[0].conversation_id, conversations[0].other_display_name);
    }
  }

  document.addEventListener("DOMContentLoaded", async () => {
    await initLibrary();
    await initMembers();
    await initMessages();
  });
})();
