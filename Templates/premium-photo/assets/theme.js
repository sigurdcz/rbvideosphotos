(() => {
if (document.body.dataset.premiumPhotoReady === "1") return;
document.body.dataset.premiumPhotoReady = "1";

/* RB Videos & Photos — sdílené chování */

// CMS markup stays data-driven; these classes only adapt its presentation to
// the designer's Premium Photo components.
const cmsHero = document.querySelector(".hp-hero");
if (cmsHero) {
  cmsHero.classList.add("video-hero");
  const heroMedia = cmsHero.querySelector(".hero-bg, .hero-video, .theme-hero-video");
  if (heroMedia) heroMedia.setAttribute("data-parallax", "-0.12");
}

const cmsTimeline = document.querySelector("#proces .ew-process-list");
if (cmsTimeline) {
  const section = cmsTimeline.closest("#proces");
  section.classList.add("timeline", "on-dark");
  cmsTimeline.classList.add("tl-list");
  cmsTimeline.querySelectorAll(":scope > article").forEach(item => item.classList.add("tl-item"));
  if (!section.querySelector(".tl-rail")) {
    section.insertAdjacentHTML("afterbegin", '<div class="tl-rail" aria-hidden="true"></div><div class="tl-playhead" aria-hidden="true"></div>');
  }
}

const premiumHome = document.querySelector(".premium-photo-home");
if (premiumHome) {
  const aboutVisualSource = document.querySelector(".jb-about-visual__source[data-src]");
  const services = premiumHome.querySelector("#sluzby");
  const portfolio = premiumHome.querySelector("#nase-prace");
  const blog = premiumHome.querySelector("#blog");
  const pricing = premiumHome.querySelector("#cenik");
  const faq = premiumHome.querySelector("#faq");
  const reviews = premiumHome.querySelector("#hodnoceni");
  const process = premiumHome.querySelector("#proces");
  const contact = premiumHome.querySelector("#kontakt");

  services?.querySelector(".container")?.classList.add("wrap");
  services?.querySelector(".hp-services-grid")?.classList.add("tiles", "tiles-4");
  portfolio?.classList.add("on-dark", "premium-portfolio-section");
  portfolio?.querySelector(".container")?.classList.add("wrap");
  blog?.querySelector(".container")?.classList.add("wrap");
  blog?.querySelector(".hp-blog-grid")?.classList.add("tiles", "tiles-3");
  reviews?.querySelector(".container")?.classList.add("wrap");
  contact?.classList.add("on-dark", "premium-contact-section");
  contact?.querySelector(".container")?.classList.add("wrap");
  process?.querySelector(".container")?.classList.add("wrap");

  // HomepageSectionRenderer resolves this URL from the CMS block
  // jb-about-visual__media. The point list reuses the existing DB-backed
  // service cards (title + description) rather than inventing new copy.
  if (aboutVisualSource && services && !premiumHome.querySelector(".premium-about-split")) {
    const src = aboutVisualSource.dataset.src || "";
    const serviceCards = [...services.querySelectorAll(".premium-service-card")].slice(0, 4);
    if (src && serviceCards.length) {
      const about = document.createElement("section");
      about.className = "premium-about-split wrap";
      about.setAttribute("aria-label", "O nas");

      const frame = document.createElement("div");
      frame.className = "premium-about-split__media";
      const photo = document.createElement("div");
      photo.className = "premium-about-split__frame";
      photo.style.backgroundImage = `url("${src.replace(/["\\]/g, "\\$&")}")`;
      frame.appendChild(photo);

      const body = document.createElement("div");
      body.className = "premium-about-split__body";
      const label = document.createElement("p");
      label.className = "section-label";
      label.textContent = "O nás";

      const list = document.createElement("div");
      list.className = "premium-about-split__list";
      serviceCards.forEach(card => {
        const num = card.querySelector(".t-num")?.textContent || "";
        const title = card.querySelector("h3")?.textContent || "";
        const desc = card.querySelector("p")?.textContent || "";
        const item = document.createElement("div");
        item.className = "premium-about-split__item";
        const itemNum = document.createElement("span");
        itemNum.className = "t-num";
        itemNum.textContent = num;
        const itemTitle = document.createElement("h3");
        itemTitle.textContent = title;
        const itemDesc = document.createElement("p");
        itemDesc.textContent = desc;
        item.append(itemNum, itemTitle, itemDesc);
        list.appendChild(item);
      });

      const cta = document.createElement("a");
      cta.href = "/kontakt";
      cta.className = "btn btn-ghost";
      cta.textContent = "Kontakt";

      body.append(label, list, cta);
      about.append(frame, body);
      services.before(about);
    }
  }

  if (pricing) pricing.hidden = true;

  const contactGrid = contact?.querySelector(".premium-contact-grid");
  const faqItems = faq ? [...faq.querySelectorAll("details.faq-item")].slice(0, 3) : [];
  if (contactGrid && faqItems.length) {
    const faqGroup = document.createElement("div");
    faqGroup.className = "premium-contact-faq";
    faqGroup.dataset.faqGroup = "";
    faqItems.forEach(item => faqGroup.appendChild(item));
    const allFaq = document.createElement("a");
    allFaq.className = "btn btn-ghost";
    allFaq.href = "/faq";
    allFaq.textContent = "Všechny otázky a odpovědi";
    faqGroup.appendChild(allFaq);
    contactGrid.appendChild(faqGroup);
    faq.hidden = true;
  }

  // The DB template owns sections; Premium Photo owns their visual order.
  if (portfolio && process) portfolio.after(process);
  if (process && blog) process.after(blog);
  if (blog && reviews) blog.after(reviews);
  if (reviews && contact) reviews.after(contact);
}

const reviewsRoute = document.querySelector(".premium-reviews-route");
if (reviewsRoute) {
  const hero = reviewsRoute.querySelector(".page-hero");
  const heroCopy = hero?.querySelector(":scope > .container");
  if (hero) hero.classList.add("video-band", "rv-hero");
  if (hero && heroCopy) {
    const pageHead = document.createElement("section");
    pageHead.className = "page-head wrap";
    pageHead.appendChild(heroCopy);
    hero.after(pageHead);
  }
  const listSection = reviewsRoute.querySelector(".premium-review-page")?.closest("section.section");
  listSection?.classList.add("premium-list-section");
}

const serviceHero = document.querySelector(".svc-hero");
if (serviceHero) {
  const serviceMedia = document.querySelector("main > .hero-bg, main > .theme-hero-video");
  if (serviceMedia) serviceHero.prepend(serviceMedia);
}

// ---------- mobilní menu ----------
const toggle = document.querySelector(".nav-toggle");
if (toggle) {
  toggle.addEventListener("click", () => {
    const open = document.body.classList.toggle("nav-open");
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
  });
  document.querySelectorAll(".site-nav a").forEach(a =>
    a.addEventListener("click", () => document.body.classList.remove("nav-open"))
  );
}

// Aktivni navigace je odvozena z routy. Detailni routy se mapuji na svou
// rodicovskou sekci, aby uzivatel neztratil kontext.
const path = window.location.pathname.replace(/\/+$/, "") || "/";
const pageKey = document.body.dataset.cmsPageKey || "";
let activeRoot = path;
if (pageKey === "blog-post" || document.querySelector(".bp-hero")) activeRoot = "/blog";
if (pageKey === "portfolio-detail" || document.querySelector(".pf-detail-hero")) activeRoot = "/nase-prace";
if (/^(svatebni|rodinne|reklamni|rande)/.test(pageKey) || document.querySelector(".svc-hero")) activeRoot = "/sluzby";
document.querySelectorAll(".site-nav .nav-links a").forEach(link => {
  const href = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, "") || "/";
  if (href === activeRoot) link.setAttribute("aria-current", "page");
  else link.removeAttribute("aria-current");
});

// ---------- paralax ----------
const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const pxEls = [...document.querySelectorAll("[data-parallax]")];
if (!reduced && pxEls.length) {
  let ticking = false;
  const updatePx = () => {
    const mid = window.innerHeight / 2;
    pxEls.forEach(el => {
      const host = el.closest(".video-hero, .video-band, section") || el;
      const r = host.getBoundingClientRect();
      if (r.bottom < -200 || r.top > window.innerHeight + 200) return;
      const speed = parseFloat(el.dataset.parallax) || 0.15;
      const offset = (r.top + r.height / 2 - mid) * -speed;
      el.style.transform = "translate3d(0," + offset.toFixed(1) + "px,0)";
    });
    ticking = false;
  };
  const onScroll = () => {
    if (!ticking) { ticking = true; requestAnimationFrame(updatePx); }
  };
  updatePx();
  window.addEventListener("scroll", onScroll, { passive: true });
  window.addEventListener("resize", updatePx);
}

// ---------- scroll reveal ----------
const revealEls = document.querySelectorAll(".reveal");
if (reduced) {
  revealEls.forEach(el => el.classList.add("in"));
} else if ("IntersectionObserver" in window) {
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
    });
  }, { threshold: 0.12 });
  revealEls.forEach(el => io.observe(el));
}

// ---------- timeline playhead + aktivní položky ----------
const tl = document.querySelector(".timeline");
if (tl) {
  const playhead = tl.querySelector(".tl-playhead");
  const list = tl.querySelector(".tl-list");
  const items = [...tl.querySelectorAll(".tl-item")];
  const update = () => {
    const rect = tl.getBoundingClientRect();
    const listRect = list?.getBoundingClientRect();
    const mid = window.innerHeight * 0.45;
    const trackStart = listRect ? listRect.top - rect.top : 0;
    const trackHeight = listRect?.height || rect.height;
    const progress = Math.min(1, Math.max(0, (mid - (rect.top + trackStart)) / trackHeight));
    const progressHeight = trackHeight * progress;
    tl.style.setProperty("--timeline-track-start", trackStart.toFixed(2) + "px");
    tl.style.setProperty("--timeline-track-height", trackHeight.toFixed(2) + "px");
    tl.style.setProperty("--timeline-progress-height", progressHeight.toFixed(2) + "px");
    if (playhead) {
      playhead.style.top = (trackStart + progressHeight).toFixed(2) + "px";
      playhead.classList.toggle("is-visible", progress > 0);
    }
    items.forEach(it => {
      const r = it.getBoundingClientRect();
      it.classList.toggle("on", r.top < mid + 40);
    });
  };
  update();
  window.addEventListener("scroll", update, { passive: true });
  window.addEventListener("resize", update);
}

// Genericke detailni stranky sluzeb nemaji vlastni Premium Photo archetyp.
// Tema proto doplni pouze prezentacni CTA; nadpis a data zustavaji v DB hero.
if (document.querySelector(".svc-hero") && !document.querySelector(".premium-contact-cta")) {
  document.querySelector("main")?.insertAdjacentHTML("beforeend", '<section class="on-dark premium-page-cta premium-contact-cta"><div class="wrap"><p>Pokud máte nějaké dotazy, neváhejte nás kontaktovat.</p><h2>Vše si vysvětlíme a domluvíme se.</h2><a class="btn btn-solid" href="/kontakt">Kontaktovat nás</a></div></section>');
}

// ---------- FAQ: vždy jen jedna otevřená (v rámci skupiny) ----------
document.querySelectorAll("[data-faq-group]").forEach(group => {
  group.querySelectorAll("details").forEach(d => {
    d.addEventListener("toggle", () => {
      if (d.open) group.querySelectorAll("details").forEach(o => { if (o !== d) o.open = false; });
    });
  });
});

// ---------- filtry galerie ----------
const chipRow = document.querySelector("[data-filter-chips]");
if (chipRow) {
  const tiles = document.querySelectorAll("[data-cat]");
  chipRow.addEventListener("click", e => {
    const chip = e.target.closest(".chip"); if (!chip) return;
    chipRow.querySelectorAll(".chip").forEach(c => c.classList.remove("on"));
    chip.classList.add("on");
    const cat = chip.dataset.filter;
    tiles.forEach(t => {
      t.style.display = (cat === "vse" || t.dataset.cat === cat) ? "" : "none";
    });
  });
}

// ---------- cookie lišta ----------
const bar = document.querySelector(".cookiebar");
if (bar) {
  try {
    if (sessionStorage.getItem("rb-cookies")) bar.classList.add("hidden");
  } catch (e) { /* prototyp */ }
  bar.querySelectorAll("[data-cookie]").forEach(btn =>
    btn.addEventListener("click", () => {
      try { sessionStorage.setItem("rb-cookies", btn.dataset.cookie); } catch (e) {}
      bar.classList.add("hidden");
    })
  );
}

// ---------- demo formulář ----------
const form = document.querySelector("[data-demo-form]");
if (form) {
  form.addEventListener("submit", e => {
    e.preventDefault();
    const ok = form.querySelector(".form-ok");
    if (ok) ok.hidden = false;
    form.querySelectorAll("input,textarea,select,button").forEach(el => el.disabled = true);
  });
}
})();
