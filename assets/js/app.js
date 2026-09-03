(function () {
  const base = window.OI_BASE || "/online-ilahiyat/";
  function paint(n) {
    document.querySelectorAll("#cart-count").forEach((el) => { el.textContent = String(n); });
  }
  paint(window.OI_CART || 0);

  window.OICart = {
    async add(id) {
      const r = await fetch(base + "api/cart.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "action=add&book_id=" + id });
      const j = await r.json();
      paint(j.count);
      return j;
    },
    async addProgram(id) {
      const r = await fetch(base + "api/cart.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "action=add_program&program_id=" + id });
      const j = await r.json();
      paint(j.count);
      return j;
    },
    async set(id, qty) {
      const r = await fetch(base + "api/cart.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "action=set&book_id=" + id + "&qty=" + qty });
      const j = await r.json();
      paint(j.count);
      return j;
    },
    async setProgram(id, qty) {
      const r = await fetch(base + "api/cart.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "action=set_program&program_id=" + id + "&qty=" + qty });
      const j = await r.json();
      paint(j.count);
      return j;
    },
    async remove(id) {
      return this.set(id, 0);
    },
    async clear() {
      const r = await fetch(base + "api/cart.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "action=clear" });
      const j = await r.json();
      paint(j.count);
      return j;
    }
  };

    document.querySelectorAll("[data-add-book]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      await OICart.add(btn.dataset.addBook);
      btn.textContent = "Sepete eklendi";
    });
  });
  document.querySelectorAll("[data-add-program]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      await OICart.addProgram(btn.dataset.addProgram);
      btn.textContent = "Sepete eklendi";
    });
  });

  document.querySelectorAll("[data-oi-slider]").forEach((root) => {
    const slides = [...root.querySelectorAll(".oi-slide")];
    const dots = [...root.querySelectorAll("[data-oi-dot]")];
    if (slides.length < 2) return;
    let i = 0;
    let timer;
    const show = (n) => {
      i = (n + slides.length) % slides.length;
      slides.forEach((s, idx) => s.classList.toggle("is-on", idx === i));
      dots.forEach((d, idx) => d.classList.toggle("is-on", idx === i));
    };
    const play = () => {
      clearInterval(timer);
      timer = setInterval(() => show(i + 1), 5000);
    };
    root.querySelector("[data-oi-prev]")?.addEventListener("click", (e) => { e.preventDefault(); e.stopPropagation(); show(i - 1); play(); });
    root.querySelector("[data-oi-next]")?.addEventListener("click", (e) => { e.preventDefault(); e.stopPropagation(); show(i + 1); play(); });
    dots.forEach((d) => d.addEventListener("click", (e) => { e.preventDefault(); e.stopPropagation(); show(Number(d.dataset.oiDot)); play(); }));
    root.addEventListener("mouseenter", () => clearInterval(timer));
    root.addEventListener("mouseleave", play);
    play();
  });

  const slides = document.querySelectorAll(".hero-slide");
  if (slides.length) {
    let i = 0;
    const show = (n) => {
      slides.forEach((s, idx) => s.classList.toggle("is-active", idx === n));
      document.querySelectorAll(".dots button").forEach((d, idx) => d.classList.toggle("is-on", idx === n));
    };
    show(0);
    document.getElementById("hero-prev")?.addEventListener("click", () => { i = (i + slides.length - 1) % slides.length; show(i); });
    document.getElementById("hero-next")?.addEventListener("click", () => { i = (i + 1) % slides.length; show(i); });
    document.querySelectorAll(".dots button").forEach((d, idx) => d.addEventListener("click", () => { i = idx; show(i); }));
    setInterval(() => { i = (i + 1) % slides.length; show(i); }, 7000);
  }

  const drawer = document.getElementById("drawer");
  document.getElementById("menu-btn")?.addEventListener("click", () => drawer?.classList.add("is-open"));
  document.getElementById("drawer-close")?.addEventListener("click", () => drawer?.classList.remove("is-open"));
  drawer?.addEventListener("click", (e) => { if (e.target === drawer) drawer.classList.remove("is-open"); });
  document.querySelectorAll("[data-open-call]").forEach((b) => b.addEventListener("click", () => document.getElementById("call-modal")?.classList.add("is-open")));
  document.querySelectorAll("[data-close-call]").forEach((b) => b.addEventListener("click", () => document.getElementById("call-modal")?.classList.remove("is-open")));

  const askModal = document.getElementById("ask-modal");
  const openAsk = () => askModal?.classList.add("is-open");
  const closeAsk = () => askModal?.classList.remove("is-open");
  document.querySelectorAll("[data-open-ask]").forEach((b) => b.addEventListener("click", openAsk));
  document.querySelectorAll("[data-close-ask]").forEach((b) => b.addEventListener("click", closeAsk));
  askModal?.addEventListener("click", (e) => { if (e.target === askModal) closeAsk(); });
  document.querySelectorAll("[data-ask-form]").forEach((form) => {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const ok = form.querySelector("[data-ask-ok]");
      const err = form.querySelector("[data-ask-err]");
      if (ok) ok.classList.add("hidden");
      if (err) { err.classList.add("hidden"); err.textContent = ""; }
      const btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      try {
        const r = await fetch(form.action, { method: "POST", body: new FormData(form) });
        const j = await r.json();
        if (j.ok) {
          if (ok) ok.classList.remove("hidden");
          const ta = form.querySelector("textarea[name=body]");
          if (ta) ta.value = "";
        } else if (err) {
          err.textContent = j.error || "Gönderilemedi.";
          err.classList.remove("hidden");
        }
      } catch (_) {
        if (err) {
          err.textContent = "Gönderilemedi.";
          err.classList.remove("hidden");
        }
      }
      if (btn) btn.disabled = false;
    });
  });
})();
