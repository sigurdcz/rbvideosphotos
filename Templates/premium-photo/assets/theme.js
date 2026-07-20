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
  const items = [...tl.querySelectorAll(".tl-item")];
  const update = () => {
    const rect = tl.getBoundingClientRect();
    const mid = window.innerHeight * 0.45;
    const progress = Math.min(1, Math.max(0, (mid - rect.top) / rect.height));
    if (playhead) playhead.style.top = (progress * 100) + "%";
    items.forEach(it => {
      const r = it.getBoundingClientRect();
      it.classList.toggle("on", r.top < mid + 40);
    });
  };
  update();
  window.addEventListener("scroll", update, { passive: true });
  window.addEventListener("resize", update);
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
