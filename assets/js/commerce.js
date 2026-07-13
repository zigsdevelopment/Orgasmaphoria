(() => {
  "use strict";
  const A = () => window.ORG_APP;
  const qs = (selector, scope = document) => scope.querySelector(selector);
  const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  async function startCheckout(payload) {
    if (!A().configured) {
      A().toast("Checkout is temporarily unavailable.", "error");
      return;
    }
    const session = await A().requireSession(location.href);
    if (!session) return;
    try {
      const result = await A().invokeFunction(A().CONFIG.checkoutFunction || "create-checkout", payload);
      if (!result?.url) throw new Error("Checkout could not be started.");
      location.href = result.url;
    } catch (error) {
      console.error(error);
      A().toast("Checkout could not be started. Please try again.", "error");
    }
  }

  function renderCart() {
    const drawer = qs("[data-cart-drawer]");
    const list = qs("[data-cart-items]");
    const total = qs("[data-cart-total]");
    if (!drawer || !list || !total) return;
    const cart = A().getCart();
    const rows = cart.map((entry) => {
      const product = A().CONTENT.products.find((item) => item.slug === entry.slug);
      if (!product) return "";
      return `
        <div class="cart-row">
          <div><strong>${A().escapeHTML(product.title)}</strong><small>${A().escapeHTML(product.category)}</small></div>
          <label><span class="sr-only">Quantity for ${A().escapeHTML(product.title)}</span><input type="number" min="1" max="10" value="${entry.quantity}" data-cart-quantity="${product.slug}"></label>
          <span>${A().formatMoney(product.price * entry.quantity)}</span>
          <button type="button" class="icon-button" data-cart-remove="${product.slug}" aria-label="Remove ${A().escapeHTML(product.title)}">×</button>
        </div>`;
    }).join("");
    list.innerHTML = rows || '<div class="empty-state"><strong>Your bag is empty.</strong><p>Add a digital book, guide, game, activity, or collection to continue.</p></div>';
    const sum = cart.reduce((value, entry) => {
      const product = A().CONTENT.products.find((item) => item.slug === entry.slug);
      return value + (product ? product.price * entry.quantity : 0);
    }, 0);
    total.textContent = A().formatMoney(sum);
    qs("[data-cart-checkout]")?.toggleAttribute("disabled", cart.length === 0);

    qsa("[data-cart-remove]", list).forEach((button) => button.addEventListener("click", () => A().removeFromCart(button.dataset.cartRemove)));
    qsa("[data-cart-quantity]", list).forEach((input) => input.addEventListener("change", () => A().updateCart(input.dataset.cartQuantity, input.value)));
  }

  function openCart() {
    const drawer = qs("[data-cart-drawer]");
    if (!drawer) return;
    renderCart();
    drawer.hidden = false;
    document.body.classList.add("cart-open");
    qs("[data-cart-close]", drawer)?.focus();
  }

  function closeCart() {
    const drawer = qs("[data-cart-drawer]");
    if (!drawer) return;
    drawer.hidden = true;
    document.body.classList.remove("cart-open");
  }

  function initCommerce() {
    qsa("[data-add-product]").forEach((button) => button.addEventListener("click", () => A().addToCart(button.dataset.addProduct)));
    qsa("[data-buy-product]").forEach((button) => button.addEventListener("click", () => startCheckout({ kind: "products", items: [{ slug: button.dataset.buyProduct, quantity: 1 }] })));
    qsa("[data-membership-checkout]").forEach((button) => button.addEventListener("click", async () => {
      const slug = button.dataset.membershipCheckout;
      if (slug === "listener") {
        location.href = "auth.html#register";
        return;
      }
      await startCheckout({ kind: "membership", slug });
    }));
    qsa("[data-cart-open]").forEach((button) => button.addEventListener("click", openCart));
    qsa("[data-cart-close]").forEach((button) => button.addEventListener("click", closeCart));
    qs("[data-cart-drawer] .modal__backdrop")?.addEventListener("click", closeCart);
    qs("[data-cart-checkout]")?.addEventListener("click", () => startCheckout({ kind: "products", items: A().getCart() }));
    window.addEventListener("org:cart-change", renderCart);
    document.addEventListener("keydown", (event) => { if (event.key === "Escape") closeCart(); });
  }

  async function initBillingPortal() {
    qsa("[data-billing-portal]").forEach((button) => button.addEventListener("click", async () => {
      const session = await A().requireSession(location.href);
      if (!session) return;
      try {
        const result = await A().invokeFunction(A().CONFIG.portalFunction || "create-portal", {});
        if (!result?.url) throw new Error("Portal unavailable");
        location.href = result.url;
      } catch (error) {
        console.error(error);
        A().toast("Billing settings are temporarily unavailable.", "error");
      }
    }));
  }

  document.addEventListener("DOMContentLoaded", () => {
    setTimeout(() => {
      initCommerce();
      initBillingPortal();
      renderCart();
    }, 0);
  });
})();
