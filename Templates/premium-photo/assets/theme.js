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

const premiumHome = document.querySelector(".premium-photo-home")
  || (document.body.dataset.cmsPageKey === "homepage" ? document.querySelector("main") : null);
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
  pricing?.querySelector(".container")?.classList.add("wrap");
  pricing?.classList.add("premium-pricing-section");
  faq?.querySelector(".container")?.classList.add("wrap");
  faq?.classList.add("premium-faq-section");
  reviews?.querySelector(".container")?.classList.add("wrap");
  contact?.classList.add("on-dark", "premium-contact-section");
  contact?.querySelector(".container")?.classList.add("wrap");
  process?.querySelector(".container")?.classList.add("wrap");

  // HomepageSectionRenderer resolves this URL from the CMS block
  // jb-about-visual__media. The point list reuses the existing DB-backed
  // service cards (title + description) rather than inventing new copy.
  if (aboutVisualSource && services && !premiumHome.querySelector(".premium-about-split")) {
    const src = aboutVisualSource.dataset.src || "";
    const serviceCards = [...services.querySelectorAll(".premium-service-card, .hp-svc-card")].slice(0, 4);
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
        const num = card.querySelector(".t-num, .hp-svc-card__num")?.textContent || "";
        const title = card.querySelector("h3, .hp-svc-card__title")?.textContent || "";
        const desc = card.querySelector("p, .hp-svc-card__desc")?.textContent || "";
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

  const reviewGrid = reviews?.querySelector(".premium-review-grid");
  const reviewCards = reviewGrid ? [...reviewGrid.querySelectorAll(":scope > .review")] : [];
  if (reviewGrid && reviewCards.length > 3 && !reviews.querySelector(".premium-review-controls")) {
    reviews.classList.add("premium-review-carousel");
    reviewGrid.classList.add("premium-review-track");
    const viewport = document.createElement("div");
    viewport.className = "premium-review-viewport";
    reviewGrid.before(viewport);
    viewport.appendChild(reviewGrid);

    const controls = document.createElement("div");
    controls.className = "premium-review-controls";
    const previous = document.createElement("button");
    previous.type = "button";
    previous.className = "premium-review-arrow premium-review-arrow--previous";
    previous.setAttribute("aria-label", "Předchozí recenze");
    previous.textContent = "←";
    const next = document.createElement("button");
    next.type = "button";
    next.className = "premium-review-arrow premium-review-arrow--next";
    next.setAttribute("aria-label", "Další recenze");
    next.textContent = "→";
    controls.append(previous, next);
    viewport.after(controls);

    let reviewPage = 0;
    const pageCount = Math.ceil(reviewCards.length / 3);
    const updateReviews = () => {
      if (!window.matchMedia("(min-width: 960px)").matches) {
        reviewGrid.style.transform = "";
        return;
      }
      const gap = parseFloat(getComputedStyle(reviewGrid).gap) || 0;
      const cardWidth = reviewCards[0].getBoundingClientRect().width;
      reviewGrid.style.transform = `translate3d(${-reviewPage * (cardWidth + gap) * 3}px,0,0)`;
      previous.disabled = reviewPage === 0;
      next.disabled = reviewPage === pageCount - 1;
    };
    previous.addEventListener("click", () => { reviewPage = Math.max(0, reviewPage - 1); updateReviews(); });
    next.addEventListener("click", () => { reviewPage = Math.min(pageCount - 1, reviewPage + 1); updateReviews(); });
    window.addEventListener("resize", updateReviews);
    updateReviews();
  }

  // The DB template owns sections; Premium Photo owns their visual order.
  if (portfolio && process) portfolio.after(process);
  if (process && blog) process.after(blog);
  if (blog && pricing) blog.after(pricing);
  if (pricing && faq) pricing.after(faq);
  if (faq && reviews) faq.after(reviews);
  if (reviews && contact) reviews.after(contact);

  // Reuse the DB-rendered FAQ preview next to the contact form. Moving the
  // existing node preserves inline editing and avoids duplicate content.
  const contactGrid = contact?.querySelector(".premium-contact-grid");
  const faqContent = faq?.querySelector(":scope > .container");
  if (contactGrid && faq && faqContent && !contactGrid.querySelector(":scope > .premium-contact-faq")) {
    faq.removeAttribute("id");
    faqContent.id = "faq";
    faqContent.classList.add("premium-contact-faq");
    contactGrid.appendChild(faqContent);
    faq.remove();
  }
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

// All internal routes share one hero geometry. Generic CMS page heroes do not
// carry an eyebrow in their stored HTML, so derive it from the visible title.
const innerPageKey = document.body.dataset.cmsPageKey || "";
if (innerPageKey !== "homepage") {
  const innerHero = document.querySelector("main > .video-band:has(.page-head--hero), main > .svc-hero, main > .page-hero");
  innerHero?.classList.add("premium-inner-hero");
  if (innerHero?.classList.contains("page-hero")) {
    const innerHeroCopy = innerHero.querySelector(":scope > .container");
    const innerHeroTitle = innerHeroCopy?.querySelector(":scope > h1");
    if (innerHeroCopy && innerHeroTitle && !innerHeroCopy.querySelector(":scope > .eyebrow")) {
      const eyebrow = document.createElement("span");
      eyebrow.className = "eyebrow";
      eyebrow.textContent = innerHeroTitle.textContent.trim();
      innerHeroCopy.prepend(eyebrow);
    }
    if (innerPageKey === "cenik" && !innerHero.querySelector(":scope > .band-cap")) {
      const bandCap = document.createElement("div");
      bandCap.className = "band-cap inner-hero-band-cap";
      bandCap.innerHTML = '<span class="rec">rec</span><span>ceník · balíčky na míru</span><span><b>RB</b> pricing</span>';
      innerHero.insertBefore(bandCap, innerHeroCopy);
    }
  }
}

// ---------- mobilní menu ----------
// Blog, portfolio and service details share one media viewer. The component
// only consumes media that already exists in the rendered CMS DOM; it never
// invents placeholder slides when the primary data path is empty.
const detailHero = document.querySelector(".bp-hero, .pf-detail-hero, .svc-hero");
if (detailHero && !document.querySelector(".detail-media-gallery")) {
  const media = [];
  const seen = new Set();
  const absoluteUrl = value => {
    try { return new URL(value, window.location.href).href; } catch (error) { return ""; }
  };
  const addMedia = item => {
    const src = absoluteUrl(item.src || "");
    if (!src || seen.has(src)) return;
    seen.add(src);
    media.push({ ...item, src });
  };
  const addNode = node => {
    if (!node) return;
    if (node.matches(".theme-hero-video")) {
      addNode(node.querySelector("iframe, video, img"));
    } else if (node.matches("img")) {
      addMedia({ type: "image", src: node.currentSrc || node.src, alt: node.alt || "" });
    } else if (node.matches("video")) {
      addMedia({
        type: "video",
        src: node.currentSrc || node.src || node.querySelector("source")?.src,
        poster: node.poster || "",
        alt: node.getAttribute("aria-label") || node.getAttribute("title") || "Video",
      });
    } else if (node.matches("iframe")) {
      const youtubeEmbed = node.src.match(/youtube\.com\/embed\/([A-Za-z0-9_-]{6,})/);
      const vimeoEmbed = node.src.match(/player\.vimeo\.com\/video\/(\d+)/);
      const src = youtubeEmbed
        ? `https://www.youtube.com/embed/${youtubeEmbed[1]}`
        : (vimeoEmbed ? `https://player.vimeo.com/video/${vimeoEmbed[1]}?dnt=1` : node.src);
      addMedia({ type: "embed", src, alt: node.title || "Video" });
    } else if (node.matches("a[href]")) {
      const href = node.href;
      const youtube = href.match(/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([A-Za-z0-9_-]{11})/);
      const vimeo = href.match(/vimeo\.com\/(?:video\/)?(\d+)/);
      if (youtube) addMedia({ type: "embed", src: `https://www.youtube.com/embed/${youtube[1]}`, alt: node.textContent.trim() || "Video" });
      else if (vimeo) addMedia({ type: "embed", src: `https://player.vimeo.com/video/${vimeo[1]}?dnt=1`, alt: node.textContent.trim() || "Video" });
      else if (/\.(?:avif|gif|jpe?g|png|webp)(?:[?#].*)?$/i.test(href)) addMedia({ type: "image", src: href, alt: node.querySelector("img")?.alt || "" });
    }
  };

  addNode(detailHero.querySelector(":scope > .hero-bg, :scope > .theme-hero-video"));
  document.querySelectorAll([
    ".premium-video iframe", ".premium-video video",
    ".premium-article-gallery a[href]",
    ".premium-article-body img", ".premium-article-body video", ".premium-article-body iframe",
    ".premium-article-body a[href*='youtube.com']", ".premium-article-body a[href*='youtu.be']", ".premium-article-body a[href*='vimeo.com']",
    "main .svc-detail__img", "main .svc-hero ~ video", "main .svc-hero ~ iframe",
    "main .svc-hero ~ a[href*='youtube.com']", "main .svc-hero ~ a[href*='youtu.be']", "main .svc-hero ~ a[href*='vimeo.com']",
  ].join(",")).forEach(addNode);

  if (media.length) {
    document.querySelectorAll(".premium-video, .premium-article-gallery").forEach(node => node.remove());
    document.querySelectorAll("main .svc-detail__img").forEach(node => node.remove());

    const gallery = document.createElement("section");
    gallery.className = "detail-media-gallery wrap";
    gallery.setAttribute("aria-label", "Galerie médií");
    gallery.dataset.galleryCount = String(media.length);
    const viewport = document.createElement("div");
    viewport.className = "detail-media-gallery__viewport";
    viewport.tabIndex = 0;
    const track = document.createElement("div");
    track.className = "detail-media-gallery__track";

    const slides = media.map((item, index) => {
      const slide = document.createElement("figure");
      slide.className = "detail-media-gallery__slide";
      slide.setAttribute("aria-label", `${index + 1} z ${media.length}`);
      if (item.type === "embed") {
        const frame = document.createElement("iframe");
        frame.src = item.src;
        frame.title = item.alt || `Video ${index + 1}`;
        frame.loading = "lazy";
        frame.allow = "autoplay; fullscreen; picture-in-picture";
        frame.allowFullscreen = true;
        slide.appendChild(frame);
      } else if (item.type === "video") {
        const video = document.createElement("video");
        video.src = item.src;
        video.controls = true;
        video.preload = "metadata";
        if (item.poster) video.poster = item.poster;
        video.setAttribute("aria-label", item.alt || `Video ${index + 1}`);
        slide.appendChild(video);
      } else {
        const image = document.createElement("img");
        image.src = item.src;
        image.alt = item.alt || "";
        image.loading = index === 0 ? "eager" : "lazy";
        image.decoding = "async";
        slide.appendChild(image);
      }
      track.appendChild(slide);
      return slide;
    });
    viewport.appendChild(track);

    const previous = document.createElement("button");
    previous.type = "button";
    previous.className = "detail-media-gallery__arrow detail-media-gallery__arrow--previous";
    previous.setAttribute("aria-label", "Předchozí médium");
    previous.innerHTML = "&#8592;";
    const next = document.createElement("button");
    next.type = "button";
    next.className = "detail-media-gallery__arrow detail-media-gallery__arrow--next";
    next.setAttribute("aria-label", "Další médium");
    next.innerHTML = "&#8594;";
    viewport.append(previous, next);

    const dots = document.createElement("div");
    dots.className = "detail-media-gallery__dots";
    dots.setAttribute("aria-label", "Výběr média");
    const status = document.createElement("p");
    status.className = "detail-media-gallery__status sr-only";
    status.setAttribute("aria-live", "polite");
    gallery.append(viewport, dots, status);
    detailHero.after(gallery);

    let activeIndex = 0;
    let dotButtons = [];
    const show = index => {
      activeIndex = (index + media.length) % media.length;
      track.style.transform = `translate3d(${-activeIndex * 100}%,0,0)`;
      slides.forEach((slide, slideIndex) => slide.setAttribute("aria-hidden", slideIndex === activeIndex ? "false" : "true"));
      dotButtons.forEach((dot, dotIndex) => {
        dot.classList.toggle("is-active", dotIndex === activeIndex);
        dot.setAttribute("aria-current", dotIndex === activeIndex ? "true" : "false");
      });
      status.textContent = `Médium ${activeIndex + 1} z ${media.length}`;
      slides.forEach((slide, slideIndex) => {
        if (slideIndex !== activeIndex) slide.querySelectorAll("video").forEach(video => video.pause());
      });
    };
    dotButtons = media.map((item, index) => {
      const dot = document.createElement("button");
      dot.type = "button";
      dot.className = "detail-media-gallery__dot";
      dot.setAttribute("aria-label", `Zobrazit médium ${index + 1} z ${media.length}`);
      dot.addEventListener("click", () => show(index));
      dots.appendChild(dot);
      return dot;
    });
    previous.addEventListener("click", () => show(activeIndex - 1));
    next.addEventListener("click", () => show(activeIndex + 1));
    viewport.addEventListener("keydown", event => {
      if (event.key === "ArrowLeft") { event.preventDefault(); show(activeIndex - 1); }
      if (event.key === "ArrowRight") { event.preventDefault(); show(activeIndex + 1); }
      if (event.key === "Home") { event.preventDefault(); show(0); }
      if (event.key === "End") { event.preventDefault(); show(media.length - 1); }
    });
    let pointerStart = null;
    viewport.addEventListener("pointerdown", event => { pointerStart = event.clientX; });
    viewport.addEventListener("pointerup", event => {
      if (pointerStart === null) return;
      const distance = event.clientX - pointerStart;
      pointerStart = null;
      if (Math.abs(distance) >= 42) show(activeIndex + (distance < 0 ? 1 : -1));
    });
    viewport.addEventListener("pointercancel", () => { pointerStart = null; });
    if (media.length === 1) {
      previous.hidden = true;
      next.hidden = true;
    }
    show(0);
  }
}

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

// Na homepage navigace smeruje do sekci, proto aktivni stav sleduje polohu
// viewportu. Na ostatnich strankach se stav odvozuje z routy a detailni routy
// se mapuji na svou rodicovskou sekci.
const path = window.location.pathname.replace(/\/+$/, "") || "/";
const pageKey = document.body.dataset.cmsPageKey || "";
const navLinks = [...document.querySelectorAll(".site-nav .nav-links a")];
if (pageKey === "homepage" && path === "/") {
  const sectionLinks = navLinks.map(link => {
    const url = new URL(link.href, window.location.href);
    return url.hash ? { link, section: document.querySelector(url.hash) } : null;
  }).filter(item => item?.section);
  let navTicking = false;
  const updateHomepageNavigation = () => {
    const marker = window.innerHeight * 0.35;
    const active = sectionLinks.find(item => {
      const bounds = item.section.getBoundingClientRect();
      return bounds.top <= marker && bounds.bottom > marker;
    }) || null;
    sectionLinks.forEach(item => {
      if (item === active) item.link.setAttribute("aria-current", "location");
      else item.link.removeAttribute("aria-current");
    });
    navTicking = false;
  };
  const requestHomepageNavigationUpdate = () => {
    if (!navTicking) {
      navTicking = true;
      requestAnimationFrame(updateHomepageNavigation);
    }
  };
  updateHomepageNavigation();
  window.addEventListener("scroll", requestHomepageNavigationUpdate, { passive: true });
  window.addEventListener("resize", requestHomepageNavigationUpdate);
} else {
  let activeRoot = path;
  if (pageKey === "blog-post" || document.querySelector(".bp-hero")) activeRoot = "/blog";
  if (pageKey === "portfolio-detail" || document.querySelector(".pf-detail-hero")) activeRoot = "/nase-prace";
  if (/^(svatebni|rodinne|reklamni|rande)/.test(pageKey) || document.querySelector(".svc-hero")) activeRoot = "/sluzby";
  navLinks.forEach(link => {
    const href = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, "") || "/";
    if (href === activeRoot) link.setAttribute("aria-current", "page");
    else link.removeAttribute("aria-current");
  });
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
  document.querySelector("main")?.insertAdjacentHTML("beforeend", '<section class="on-dark premium-page-cta premium-contact-cta"><div class="wrap"><p>Pokud máte nějaké dotazy, neváhejte nás kontaktovat.</p><a class="btn btn-solid" href="/kontakt">Kontaktovat nás</a></div></section>');
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
