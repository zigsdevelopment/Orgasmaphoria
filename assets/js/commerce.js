(() => {
  "use strict";
  const A = () => window.ORG_APP;
  const qs = (selector, scope = document) => scope.querySelector(selector);
  const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];
  let checkoutBusy = false;

  async function startCheckout(payload) {
    if (checkoutBusy) return;
    const session = await A().requireSession(location.href);
    if (!session) return;
    checkoutBusy = true;
    qsa("[data-cart-checkout], [data-buy-product], [data-membership-checkout]").forEach((button) => { button.disabled = true; });
    try {
      const response = await fetch(A().sitePath("api/checkout.php"), {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-Token": session.csrfToken || ""
        },
        body: JSON.stringify(payload)
      });
      const result = await response.json().catch(() => ({}));
      if (response.status === 401 || result.loginRequired) {
        location.href = `${A().sitePath("account/login.php")}?return=${encodeURIComponent(location.href)}`;
        return;
      }
      if (!response.ok || !result.url) throw new Error(result.error || "Checkout could not be started.");
      location.href = result.url;
    } catch (error) {
      console.error(error);
      A().toast(error.message || "Checkout could not be started. Please try again.", "error");
    } finally {
      checkoutBusy = false;
      qsa("[data-cart-checkout], [data-buy-product], [data-membership-checkout]").forEach((button) => { button.disabled = false; });
      renderCart();
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
    const checkout = qs("[data-cart-checkout]");
    if (checkout) checkout.disabled = checkoutBusy || cart.length === 0;

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
        location.href = A().sitePath("account/login.php?view=register#register");
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

  document.addEventListener("DOMContentLoaded", () => {
    initCommerce();
    renderCart();
  });
})();
