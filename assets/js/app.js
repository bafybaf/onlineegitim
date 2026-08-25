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
    async set(id, qty) {
      const r = await fetch(base + "api/cart.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "action=set&book_id=" + id + "&qty=" + qty });
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
})();
