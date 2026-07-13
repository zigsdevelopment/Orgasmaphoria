(() => {
  "use strict";
  const A = () => window.ORG_APP;
  const qs = (selector, scope = document) => scope.querySelector(selector);
  const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  function membershipCard(tier, compact = false) {
    const price = tier.price === 0 ? "Free" : `${A().formatMoney(tier.price)}<small>/${tier.interval}</small>`;
    return `
      <article class="plan-card ${tier.featured ? "plan-card--featured" : ""} ${compact ? "plan-card--compact" : ""}" id="${tier.slug}">
        <div class="plan-card__head"><span class="plan-card__label">${A().escapeHTML(tier.eyebrow)}</span>${tier.featured ? '<span class="featured-ribbon">Highest access</span>' : ""}</div>
        <h2>${A().escapeHTML(tier.name)}</h2>
        <div class="plan-price">${price}</div>
        <p>${A().escapeHTML(tier.description)}</p>
        <ul class="check-list">${tier.features.map((feature) => `<li>${A().escapeHTML(feature)}</li>`).join("")}</ul>
        <button class="button ${tier.featured ? "button--primary" : "button--ghost"}" type="button" data-membership-checkout="${tier.slug}">${A().escapeHTML(tier.cta)}</button>
      </article>`;
  }

  function productCard(product) {
    return `
      <article class="product-card ${product.featured ? "product-card--featured" : ""}" id="${product.slug}">
        <div class="product-cover product-cover--${product.slug}"><span>${A().escapeHTML(product.glyph)}</span><small>Orgasmaphoria</small></div>
        <div class="product-card__content">
          <div class="product-card__meta"><span>${A().escapeHTML(product.category)}</span><strong>${A().formatMoney(product.price)}</strong></div>
          <h2>${A().escapeHTML(product.title)}</h2>
          <p class="product-subtitle">${A().escapeHTML(product.subtitle)}</p>
          <p>${A().escapeHTML(product.description)}</p>
          <details><summary>What is included</summary><ul class="check-list">${product.includes.map((item) => `<li>${A().escapeHTML(item)}</li>`).join("")}</ul></details>
        </div>
        <div class="product-card__actions"><button class="button button--primary" type="button" data-add-product="${product.slug}">Add to bag</button><button class="text-button" type="button" data-buy-product="${product.slug}">Buy now</button></div>
      </article>`;
  }

  function renderMemberships() {
    qsa("[data-membership-grid]").forEach((target) => {
      const compact = target.hasAttribute("data-compact");
      target.innerHTML = A().CONTENT.memberships.map((tier) => membershipCard(tier, compact)).join("");
    });
  }

  function renderProducts() {
    qsa("[data-product-grid]").forEach((target) => {
      const limit = Number(target.dataset.limit || A().CONTENT.products.length);
      target.innerHTML = A().CONTENT.products.slice(0, limit).map(productCard).join("");
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
      privacy: "Privacy question",
      order: "Order support",
      events: "Events"
    };
    const wanted = map[requested] || requested;
    if (wanted && [...select.options].some((option) => option.value === wanted)) select.value = wanted;
  }

  function initFAQ() {
    qsa(".faq-list details").forEach((detail) => {
      detail.addEventListener("toggle", () => {
        if (!detail.open) return;
        qsa(".faq-list details").forEach((other) => { if (other !== detail) other.open = false; });
      });
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    renderMemberships();
    renderProducts();
    initContactTopic();
    initFAQ();
  });
})();
