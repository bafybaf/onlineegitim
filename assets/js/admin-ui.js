(function () {
  const base = window.OI_BASE || "/online-ilahiyat/";
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

  function postForm(url, data) {
    if (data instanceof FormData) {
      if (csrf && !data.has("_csrf")) data.append("_csrf", csrf);
      return fetch(url, { method: "POST", body: data }).then((r) => r.json());
    }
    const body = new URLSearchParams();
    if (csrf) body.append("_csrf", csrf);
    Object.entries(data).forEach(([k, v]) => {
      if (Array.isArray(v)) v.forEach((x) => body.append(k + "[]", x));
      else body.append(k, v);
    });
    return fetch(url, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body }).then((r) => r.json());
  }

  function wrapFileInputs() {
    document.querySelectorAll('input[type="file"]').forEach((input) => {
      if (input.closest(".dropzone-wrap") || input.closest("[data-media-box]")) return;
      const wrap = document.createElement("label");
      wrap.className = "dropzone-wrap";
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
      const copy = document.createElement("span");
      copy.className = "dropzone-copy";
      copy.innerHTML = input.multiple
        ? "<b>Dosyaları bırakın</b> veya seçin. Birden fazla yükleyebilirsiniz."
        : "<b>Dosyayı bırakın</b> veya seçin.";
      wrap.appendChild(copy);
    });
  }

  function bindDropzones() {
    document.querySelectorAll(".dropzone-wrap").forEach((wrap) => {
      if (wrap.dataset.bound) return;
      wrap.dataset.bound = "1";
      const input = wrap.querySelector('input[type="file"]');
      if (!input) return;
      ["dragenter", "dragover"].forEach((ev) => wrap.addEventListener(ev, (e) => {
        e.preventDefault();
        wrap.classList.add("is-over");
      }));
      ["dragleave", "drop"].forEach((ev) => wrap.addEventListener(ev, (e) => {
        e.preventDefault();
        wrap.classList.remove("is-over");
      }));
      wrap.addEventListener("drop", (e) => {
        const files = e.dataTransfer?.files;
        if (!files || !files.length) return;
        const dt = new DataTransfer();
        const max = input.multiple ? files.length : 1;
        for (let i = 0; i < max; i += 1) dt.items.add(files[i]);
        input.files = dt.files;
        input.dispatchEvent(new Event("change", { bubbles: true }));
      });
    });
  }

  function renderThumbs(box, items) {
    const hold = box.querySelector("[data-media-sort]");
    if (!hold) return;
    hold.innerHTML = items.map((it) => (
      '<div class="media-thumb" draggable="true" data-id="' + it.id + '">'
      + '<img src="' + it.src + '" alt="">'
      + '<button type="button" class="media-del" data-media-del="' + it.id + '" aria-label="Sil">×</button>'
      + "</div>"
    )).join("");
    bindThumbs(box);
  }

  function bindThumbs(box) {
    const hold = box.querySelector("[data-media-sort]");
    if (!hold) return;
    let dragEl = null;
    hold.querySelectorAll(".media-thumb").forEach((el) => {
      el.addEventListener("dragstart", () => { dragEl = el; el.classList.add("is-dragging"); });
      el.addEventListener("dragend", () => { el.classList.remove("is-dragging"); dragEl = null; saveMediaOrder(box); });
      el.addEventListener("dragover", (e) => {
        e.preventDefault();
        if (!dragEl || dragEl === el) return;
        const rect = el.getBoundingClientRect();
        const before = (e.clientX - rect.left) < rect.width / 2;
        hold.insertBefore(dragEl, before ? el : el.nextSibling);
      });
    });
  }

  function saveMediaOrder(box) {
    const id = Number(box.dataset.id || 0);
    if (id < 1) return;
    const ids = [...box.querySelectorAll(".media-thumb[data-id]")].map((el) => el.dataset.id);
    postForm(base + "api/media.php", { action: "reorder", owner_type: box.dataset.owner, owner_id: String(id), ids });
  }

  function bindMediaBoxes() {
    document.querySelectorAll("[data-media-box]").forEach((box) => {
      const input = box.querySelector('input[type="file"]');
      const ownerId = Number(box.dataset.id || 0);
      bindThumbs(box);
      box.addEventListener("click", async (e) => {
        const btn = e.target.closest("[data-media-del]");
        if (!btn) return;
        e.preventDefault();
        if (ownerId < 1) {
          btn.closest(".media-thumb")?.remove();
          return;
        }
        const j = await postForm(base + "api/media.php", {
          action: "delete",
          owner_type: box.dataset.owner,
          owner_id: String(ownerId),
          id: btn.dataset.mediaDel,
        });
        if (j.ok) renderThumbs(box, j.items || []);
      });
      input?.addEventListener("change", async () => {
        if (ownerId < 1 || !input.files?.length) return;
        const fd = new FormData();
        fd.append("action", "upload");
        fd.append("owner_type", box.dataset.owner);
        fd.append("owner_id", String(ownerId));
        if (csrf) fd.append("_csrf", csrf);
        [...input.files].forEach((f) => fd.append("images[]", f));
        const j = await fetch(base + "api/media.php", { method: "POST", body: fd }).then((r) => r.json());
        if (j.ok) {
          renderThumbs(box, j.items || []);
          input.value = "";
        } else {
          alert(j.error || "Yüklenemedi.");
        }
      });
    });
  }

  function bindListSort() {
    document.querySelectorAll("[data-sort-table]").forEach((list) => {
      if (list.dataset.sortBound) return;
      list.dataset.sortBound = "1";
      list.addEventListener("pointerdown", (e) => {
        if (e.button !== 0) return;
        const row = e.target.closest("[data-sort-id]");
        if (!row || !list.contains(row)) return;
        if (e.target.closest("input, textarea, select, button, a, label")) return;
        if (row.querySelector(".sort-handle") && !e.target.closest(".sort-handle")) return;

        e.preventDefault();
        const handle = e.target.closest(".sort-handle") || row;
        try { handle.setPointerCapture(e.pointerId); } catch (_) {}
        row.classList.add("is-dragging");
        document.body.classList.add("is-sorting");
        const prevPe = row.style.pointerEvents;
        row.style.pointerEvents = "none";

        const onMove = (ev) => {
          const under = document.elementFromPoint(ev.clientX, ev.clientY);
          const over = under && under.closest("[data-sort-id]");
          if (!over || over === row || !list.contains(over)) return;
          const items = [...list.querySelectorAll("[data-sort-id]")];
          const dragIdx = items.indexOf(row);
          const overIdx = items.indexOf(over);
          if (overIdx < 0 || dragIdx === overIdx) return;
          if (dragIdx < overIdx) list.insertBefore(row, over.nextSibling);
          else list.insertBefore(row, over);
        };
        const onUp = () => {
          row.classList.remove("is-dragging");
          document.body.classList.remove("is-sorting");
          row.style.pointerEvents = prevPe;
          handle.removeEventListener("pointermove", onMove);
          handle.removeEventListener("pointerup", onUp);
          handle.removeEventListener("pointercancel", onUp);
          const ids = [...list.querySelectorAll("[data-sort-id]")].map((el) => el.dataset.sortId);
          postForm(base + "api/sort.php", { table: list.dataset.sortTable, ids }).catch(() => {});
        };
        handle.addEventListener("pointermove", onMove);
        handle.addEventListener("pointerup", onUp);
        handle.addEventListener("pointercancel", onUp);
      });
    });
  }

  function bindSliders() {
    document.querySelectorAll("[data-oi-slider]").forEach((root) => {
      if (root.dataset.sliderBound) return;
      root.dataset.sliderBound = "1";
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
  }

  wrapFileInputs();
  bindDropzones();
  bindMediaBoxes();
  bindListSort();
  bindSliders();
})();
