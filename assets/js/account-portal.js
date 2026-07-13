(() => {
  "use strict";

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
      button.addEventListener("click", () => {
        const field = button.closest(".password-field")?.querySelector("[data-password-input]");
        if (!field) return;
        const showing = field.type === "text";
        field.type = showing ? "password" : "text";
        button.textContent = showing ? "Show" : "Hide";
        button.setAttribute("aria-label", showing ? "Show password" : "Hide password");
      });
    });

    const copyRecovery = document.querySelector("[data-copy-recovery]");
    const recoveryGrid = document.querySelector("[data-recovery-codes]");
    const copyStatus = document.querySelector("[data-copy-status]");
    copyRecovery?.addEventListener("click", async () => {
      const text = [...(recoveryGrid?.querySelectorAll("code") || [])].map((item) => item.textContent.trim()).join("\n");
      try {
        await navigator.clipboard.writeText(text);
        if (copyStatus) copyStatus.textContent = "Recovery codes copied.";
      } catch {
        if (copyStatus) copyStatus.textContent = "Copy was blocked. Select and copy the codes manually.";
      }
    });

    const qrTarget = document.querySelector("[data-totp-qr]");
    const uri = qrTarget?.dataset.totpQr;
    if (qrTarget && uri && window.QRCode) {
      qrTarget.textContent = "";
      new window.QRCode(qrTarget, { text: uri, width: 220, height: 220, correctLevel: window.QRCode.CorrectLevel.M });
    }

    document.querySelectorAll("[data-confirm]").forEach((button) => {
      button.addEventListener("click", (event) => {
        if (!window.confirm(button.dataset.confirm || "Continue?")) event.preventDefault();
      });
    });
  });
})();
