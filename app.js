const GA_MEASUREMENT_ID = "G-CEP45LES55";
const COOKIE_CONSENT_KEY = "fw_cookie_consent_v1";
let analyticsLoaded = false;

function getStoredConsent() {
  try {
    const raw = window.localStorage.getItem(COOKIE_CONSENT_KEY);
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw);
    if (typeof parsed?.analytics === "boolean") {
      return parsed.analytics;
    }
  } catch (error) {
    return null;
  }

  return null;
}

function saveConsent(analyticsEnabled) {
  const payload = {
    analytics: analyticsEnabled,
    timestamp: Date.now(),
  };

  try {
    window.localStorage.setItem(COOKIE_CONSENT_KEY, JSON.stringify(payload));
  } catch (error) {
    // Ignore storage errors and continue with runtime state.
  }
}

function loadAnalytics() {
  if (analyticsLoaded || !GA_MEASUREMENT_ID) {
    return;
  }

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtag() {
    window.dataLayer.push(arguments);
  };

  window.gtag("js", new Date());
  window.gtag("config", GA_MEASUREMENT_ID, {
    anonymize_ip: true,
  });

  const gaScript = document.createElement("script");
  gaScript.async = true;
  gaScript.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(GA_MEASUREMENT_ID)}`;
  document.head.appendChild(gaScript);
  analyticsLoaded = true;
}

function clearAnalyticsCookies() {
  const cookieNames = document.cookie
    .split(";")
    .map((cookie) => cookie.trim().split("=")[0])
    .filter((name) => name === "_ga" || name.startsWith("_ga_"));

  const host = window.location.hostname;
  const hostWithoutWww = host.startsWith("www.") ? host.slice(4) : host;
  const domains = [host, `.${host}`, hostWithoutWww, `.${hostWithoutWww}`];

  cookieNames.forEach((name) => {
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
    domains.forEach((domain) => {
      document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=${domain}`;
    });
  });
}

function removeCookieBanner() {
  const banner = document.querySelector(".cookie-banner");
  if (banner) {
    banner.remove();
  }
}

function setCookieConsent(analyticsEnabled) {
  const hadAnalytics = analyticsLoaded;
  saveConsent(analyticsEnabled);

  if (analyticsEnabled) {
    loadAnalytics();
  }

  if (!analyticsEnabled && hadAnalytics) {
    clearAnalyticsCookies();
  }

  removeCookieBanner();

  if (!analyticsEnabled && hadAnalytics) {
    window.location.reload();
  }
}

function renderCookieBanner() {
  if (document.querySelector(".cookie-banner")) {
    return;
  }

  const banner = document.createElement("section");
  banner.className = "cookie-banner";
  banner.setAttribute("role", "dialog");
  banner.setAttribute("aria-label", "Cookie-Einstellungen");
  banner.innerHTML = `
    <div class="cookie-banner-inner">
      <p>
        Wir verwenden optionale Statistik-Cookies (Google Analytics), um die Website zu verbessern.
        Diese werden erst nach Ihrer Einwilligung geladen.
        <a href="/privacy-policy/">Mehr erfahren</a>
      </p>
      <div class="cookie-banner-actions">
        <button type="button" class="cookie-btn cookie-btn-secondary" data-cookie-decline>Nur notwendige</button>
        <button type="button" class="cookie-btn cookie-btn-primary" data-cookie-accept>Alle akzeptieren</button>
      </div>
    </div>
  `;

  document.body.appendChild(banner);

  const acceptButton = banner.querySelector("[data-cookie-accept]");
  const declineButton = banner.querySelector("[data-cookie-decline]");

  acceptButton?.addEventListener("click", () => setCookieConsent(true));
  declineButton?.addEventListener("click", () => setCookieConsent(false));
}

function initConsentAndAnalytics() {
  const consent = getStoredConsent();

  if (consent === true) {
    loadAnalytics();
    return;
  }

  if (consent === null) {
    renderCookieBanner();
  }
}

initConsentAndAnalytics();

document.querySelectorAll("[data-cookie-settings]").forEach((link) => {
  link.addEventListener("click", (event) => {
    event.preventDefault();
    renderCookieBanner();
  });
});

const toggle = document.querySelector(".nav-toggle");
const nav = document.querySelector(".site-nav");
const siteHeader = document.querySelector(".site-header");

if (toggle && nav) {
  toggle.setAttribute("aria-label", "Menü öffnen");
  toggle.addEventListener("click", () => {
    const expanded = toggle.getAttribute("aria-expanded") === "true";
    toggle.setAttribute("aria-expanded", String(!expanded));
    toggle.setAttribute("aria-label", expanded ? "Menü öffnen" : "Menü schließen");
    nav.classList.toggle("is-open", !expanded);
    document.body.classList.toggle("nav-open", !expanded);
  });
}

document.querySelectorAll(".nav-parent").forEach((link) => {
  link.addEventListener("click", (event) => {
    const dropdown = link.parentElement?.querySelector(".nav-dropdown");
    if (window.innerWidth <= 960 && dropdown) {
      event.preventDefault();
      event.stopPropagation();
      dropdown.classList.toggle("is-open");
    }
  });
});

if (siteHeader && document.body.classList.contains("home-page")) {
  const syncHeaderState = () => {
    siteHeader.classList.toggle("is-scrolled", window.scrollY > 48);
  };

  syncHeaderState();
  window.addEventListener("scroll", syncHeaderState, { passive: true });
}

document.querySelectorAll(".site-nav a").forEach((link) => {
  link.addEventListener("click", () => {
    if (link.classList.contains("nav-parent")) {
      return;
    }

    if (toggle && nav && window.innerWidth <= 960) {
      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-label", "Menü öffnen");
      nav.classList.remove("is-open");
      document.body.classList.remove("nav-open");
    }
  });
});

const statusTarget = document.querySelector("[data-form-status]");

if (statusTarget) {
  const params = new URLSearchParams(window.location.search);
  const status = params.get("status");
  const messages = {
    sent: {
      type: "success",
      text: "Ihre Anfrage wurde erfolgreich gesendet.",
    },
    missing: {
      type: "error",
      text: "Bitte füllen Sie alle Pflichtfelder aus.",
    },
    email: {
      type: "error",
      text: "Bitte geben Sie eine gültige E-Mail-Adresse ein.",
    },
    send: {
      type: "error",
      text: "Die Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es erneut.",
    },
    server: {
      type: "error",
      text: "Der Mailserver ist derzeit nicht erreichbar.",
    },
    invalid: {
      type: "error",
      text: "Ungültige Anfrage.",
    },
  };

  if (status && messages[status]) {
    statusTarget.textContent = messages[status].text;
    statusTarget.className = `form-status ${messages[status].type}`;
    statusTarget.classList.remove("is-hidden");

    if (status === "sent" && typeof window.gtag === "function") {
      window.gtag("event", "booking_request_sent", {
        event_category: "contact",
        event_label: "booking_form",
      });
    }
  }
}

const lightboxImages = Array.from(document.querySelectorAll("main img"));

if (lightboxImages.length > 0) {
  const overlay = document.createElement("div");
  overlay.className = "lightbox";
  overlay.innerHTML = `
    <button class="lightbox-close" type="button" aria-label="Bild schließen">×</button>
    <figure class="lightbox-figure">
      <img class="lightbox-image" alt="" />
      <figcaption class="lightbox-caption"></figcaption>
    </figure>
  `;

  const overlayImage = overlay.querySelector(".lightbox-image");
  const overlayCaption = overlay.querySelector(".lightbox-caption");
  const closeButton = overlay.querySelector(".lightbox-close");

  const closeLightbox = () => {
    overlay.classList.remove("is-open");
    document.body.classList.remove("lightbox-open");
    overlayImage.removeAttribute("src");
    overlayImage.removeAttribute("alt");
    overlayCaption.textContent = "";
  };

  lightboxImages.forEach((image) => {
    image.classList.add("is-lightbox-image");
    image.addEventListener("click", () => {
      overlayImage.src = image.currentSrc || image.src;
      overlayImage.alt = image.alt || "";
      overlayCaption.textContent = image.alt || "";
      overlay.classList.add("is-open");
      document.body.classList.add("lightbox-open");
    });
  });

  closeButton?.addEventListener("click", closeLightbox);
  overlay.addEventListener("click", (event) => {
    if (event.target === overlay) {
      closeLightbox();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && overlay.classList.contains("is-open")) {
      closeLightbox();
    }
  });

  document.body.appendChild(overlay);
}
