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
