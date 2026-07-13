// ============================================================
// ORGASMAPHORIA SITE SETTINGS
// Update the values in this object before the public launch.
// Everything else on the site reads from these settings.
// ============================================================
const siteConfig = {
  spotifyArtistUrl: "https://open.spotify.com/artist/7JPxqyyzIP3N4YChFOtFvC?si=XERgnpYLQGC4KQZBFIPNdQ",
  podcastUrl: "", // Paste the dedicated Spotify show URL here when available.
  businessEmail: "hello@orgasmaphoria.com", // Replace with the real business email.
  newsletterEndpoint: "", // Paste a Formspree/Mailchimp/Brevo endpoint here when connected.
  social: {
    instagram: "",
    tiktok: "",
    youtube: "",
  },
};

const ageGate = document.querySelector("#ageGate");
const enterSite = document.querySelector("#enterSite");
const AGE_KEY = "orgasmaphoriaAgeConfirmedAt";
const THIRTY_DAYS = 30 * 24 * 60 * 60 * 1000;
const confirmedAt = Number(localStorage.getItem(AGE_KEY) || 0);
const hasValidConfirmation = confirmedAt && Date.now() - confirmedAt < THIRTY_DAYS;

if (hasValidConfirmation) {
  ageGate.classList.add("is-hidden");
} else {
  document.body.classList.add("age-locked");
}

enterSite.addEventListener("click", () => {
  localStorage.setItem(AGE_KEY, String(Date.now()));
  ageGate.classList.add("is-hidden");
  document.body.classList.remove("age-locked");
});

const menuToggle = document.querySelector("#menuToggle");
const siteNav = document.querySelector("#siteNav");

function closeMenu() {
  siteNav.classList.remove("is-open");
  menuToggle.setAttribute("aria-expanded", "false");
  document.body.classList.remove("menu-open");
}

menuToggle.addEventListener("click", () => {
  const isOpen = siteNav.classList.toggle("is-open");
  menuToggle.setAttribute("aria-expanded", String(isOpen));
  document.body.classList.toggle("menu-open", isOpen);
});

siteNav.querySelectorAll("a").forEach((link) => link.addEventListener("click", closeMenu));
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") closeMenu();
});

const siteHeader = document.querySelector(".site-header");
const scrollProgress = document.querySelector("#scrollProgress");
const backToTop = document.querySelector("#backToTop");

function updateScrollUI() {
  const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
  const progress = maxScroll > 0 ? (window.scrollY / maxScroll) * 100 : 0;
  scrollProgress.style.width = `${Math.min(progress, 100)}%`;
  siteHeader.classList.toggle("is-scrolled", window.scrollY > 16);
}

updateScrollUI();
window.addEventListener("scroll", updateScrollUI, { passive: true });
backToTop.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));

const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const revealElements = document.querySelectorAll(".reveal");

if (reducedMotion || !("IntersectionObserver" in window)) {
  revealElements.forEach((element) => element.classList.add("is-visible"));
} else {
  const observer = new IntersectionObserver(
    (entries, revealObserver) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        revealObserver.unobserve(entry.target);
      });
    },
    { threshold: 0.12 },
  );
  revealElements.forEach((element) => observer.observe(element));
}

const shareButton = document.querySelector("#shareButton");
const shareMessage = document.querySelector("#shareMessage");
shareButton.addEventListener("click", async () => {
  const shareData = {
    title: "Orgasmaphoria on Spotify",
    text: "Discover Orgasmaphoria: music, mystery, and liberation.",
    url: siteConfig.spotifyArtistUrl,
  };

  try {
    if (navigator.share) {
      await navigator.share(shareData);
      shareMessage.textContent = "Share menu opened.";
    } else {
      await navigator.clipboard.writeText(siteConfig.spotifyArtistUrl);
      shareMessage.textContent = "Spotify link copied to your clipboard.";
    }
  } catch (error) {
    if (error.name !== "AbortError") {
      shareMessage.textContent = "Use the Spotify button to copy or share the artist link.";
    }
  }
});

const podcastButton = document.querySelector("#podcastButton");
if (siteConfig.podcastUrl) {
  podcastButton.href = siteConfig.podcastUrl;
  podcastButton.target = "_blank";
  podcastButton.rel = "noreferrer";
  podcastButton.textContent = "Follow the podcast on Spotify ↗";
  podcastButton.classList.remove("is-placeholder");
}

const businessEmailLink = document.querySelector("#businessEmailLink");
businessEmailLink.href = `mailto:${siteConfig.businessEmail}?subject=Orgasmaphoria%20Business%20Inquiry`;

const socialLinks = document.querySelectorAll("#socialLinks a");
const socialKeys = ["spotify", "instagram", "tiktok", "youtube"];
socialLinks.forEach((link, index) => {
  const key = socialKeys[index];
  if (key === "spotify") return;
  const url = siteConfig.social[key];
  if (!url) return;
  link.href = url;
  link.target = "_blank";
  link.rel = "noreferrer";
  link.classList.remove("is-placeholder");
  link.querySelector("i").textContent = "↗";
});

const signupForm = document.querySelector("#signupForm");
const formMessage = document.querySelector("#formMessage");

signupForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const emailInput = signupForm.querySelector("input[type='email']");

  if (!emailInput.checkValidity()) {
    formMessage.textContent = "Please enter a valid email address.";
    emailInput.focus();
    return;
  }

  if (!siteConfig.newsletterEndpoint) {
    formMessage.textContent = "The form looks good. Connect a mailing-list provider before launch.";
    return;
  }

  const submitButton = signupForm.querySelector("button[type='submit']");
  submitButton.disabled = true;
  submitButton.textContent = "Joining…";

  try {
    const response = await fetch(siteConfig.newsletterEndpoint, {
      method: "POST",
      headers: { Accept: "application/json" },
      body: new FormData(signupForm),
    });
    if (!response.ok) throw new Error("Subscription failed");
    formMessage.textContent = "Welcome to the world of Orgasmaphoria.";
    signupForm.reset();
  } catch (error) {
    formMessage.textContent = "The signup could not be completed. Please try again later.";
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = "Join the list";
  }
});

document.querySelector("#year").textContent = new Date().getFullYear();
