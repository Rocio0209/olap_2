console.log("clues-bootstrap.js ✅");

document.addEventListener("DOMContentLoaded", () => {
  const input = document.getElementById("cluesInput");
  const results = document.getElementById("cluesResults");
  const chips = document.getElementById("cluesChips");

  const btnHG = document.getElementById("btnPrefixHG");
  const btnHGIMB = document.getElementById("btnPrefixHGIMB");
  const btnHGSSA = document.getElementById("btnPrefixHGSSA");
  const btnClear = document.getElementById("btnClearClues");

  if (!input || !results || !chips) return;

  const getCatalogo = () => document.getElementById("catalogoInput")?.value?.trim() ?? "";
  const getCubo = () => document.getElementById("cuboInput")?.value?.trim() ?? "";
  const getEstado = () => "HIDALGO";

  let debounceT = null;
  let currentPrefix = "";
  const selected = new Map();

  function escapeHtml(str) {
    return String(str)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function hideResults() {
    results.classList.add("d-none");
    results.innerHTML = "";
  }

  function showResults(items) {
    const filtered = (items ?? []).filter(it => !selected.has(it.value));

    if (!filtered.length) return hideResults();

    results.classList.remove("d-none");
    results.innerHTML = filtered.map(it => `
      <button type="button"
        class="list-group-item list-group-item-action"
        data-value="${escapeHtml(it.value)}"
        data-label="${escapeHtml(it.label ?? it.value)}">
        ${escapeHtml(it.label ?? it.value)}
      </button>
    `).join("");
  }

  function renderChips() {
    chips.innerHTML = Array.from(selected.entries()).map(([value, label]) => `
      <span class="badge text-bg-secondary d-inline-flex align-items-center gap-2 py-2 px-2" data-value="${escapeHtml(value)}">
        ${escapeHtml(label)}
        <button type="button" class="btn-close btn-close-white btn-sm" aria-label="Remove" data-remove="${escapeHtml(value)}"></button>
      </span>
    `).join("");
  }

  function addSelected(value, label) {
    if (!value || selected.has(value)) return;

    selected.set(value, label ?? value);
    renderChips();

    input.value = "";
    input.focus();
    hideResults();
  }

  async function search(q) {
    const catalogo = getCatalogo();
    const cubo = getCubo();

    // ✅ si aún no eligió SIS, no buscamos
    if (!catalogo || !cubo) return [];

    const qs = new URLSearchParams({
      catalogo,
      cubo,
      estado: getEstado(),
      limit: "10", // traemos 600 resultados para que el usuario vea más opciones, pero el front solo muestra los 10 primeros (o lo que quieras)
      prefix: currentPrefix,
      q,
    });

    const res = await fetch(`/api/vacunas/clues/search?${qs.toString()}`, {
      headers: { Accept: "application/json" },
    });

    const data = await res.json();
    if (!res.ok || data.ok === false) return [];
    return data.items ?? [];
  }

  // typing
  input.addEventListener("input", () => {
    clearTimeout(debounceT);
    const q = input.value.trim();

    if (q.length < 1) return hideResults();

    debounceT = setTimeout(async () => {
      const items = await search(q);
      showResults(items);
    }, 250);
  });

  // click resultado
  results.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-value]");
    if (!btn) return;
    addSelected(btn.dataset.value, btn.dataset.label);
  });

  // quitar chip
  chips.addEventListener("click", (e) => {
    const rm = e.target.closest("[data-remove]");
    if (!rm) return;
    selected.delete(rm.dataset.remove);
    renderChips();
  });

  // prefijos
  const setPrefix = (p) => {
    currentPrefix = p;
    input.focus();
    if (input.value.trim().length >= 1) input.dispatchEvent(new Event("input"));
  };

async function addAllByPrefix(prefix) {
  const catalogo = getCatalogo();
  const cubo = getCubo();

  if (!catalogo || !cubo) {
    alert("Primero selecciona SIS.");
    return;
  }

  const qs = new URLSearchParams({
    catalogo,
    cubo,
    estado: getEstado(),
  });

  const res = await fetch(`/api/vacunas/clues_y_nombre_unidad_por_estado?${qs.toString()}`, {
    headers: { Accept: "application/json" },
  });

  const data = await res.json();

  if (!res.ok) {
    console.error("Error masivo:", data);
    alert(data?.error ?? data?.message ?? "Error al traer CLUES por estado");
    return;
  }

  const rows = data.data ?? [];

  const normalized = rows
    .filter(r => (r.clues ?? "").toUpperCase().startsWith(prefix.toUpperCase()))
    .map(r => ({
      value: r.clues,
      label: `${r.clues} - ${r.nombre_unidad ?? ""}`.trim(),
    }));

  if (normalized.length > 300) {
  const ok = await confirmWithModal({
    title: "Confirmar selección masiva",
    html: `
      <p class="mb-2">Vas a agregar <b>${normalized.length}</b> CLUES con prefijo <b>${prefix}</b>.</p>
      <p class="mb-0 text-muted">Esto puede tardar un poco y agregará chips en la lista.</p>
    `,
    okText: "Sí, agregar",
  });

  if (!ok) return;
}

window.addManyClues?.(normalized);


  window.addManyClues?.(normalized);
}


  btnHG?.addEventListener("click", async () => {
    currentPrefix = "HG";      // opcional: que el buscador quede en ese prefijo
    await addAllByPrefix("HG");
  });

  btnHGIMB?.addEventListener("click", async () => {
    currentPrefix = "HGIMB";
    await addAllByPrefix("HGIMB");
  });

  btnHGSSA?.addEventListener("click", async () => {
    currentPrefix = "HGSSA";
    await addAllByPrefix("HGSSA");
  });

  // limpiar
  btnClear?.addEventListener("click", () => {
    currentPrefix = "";
    selected.clear();
    renderChips();
    input.value = "";
    hideResults();
    input.focus();
  });

  // click afuera cierra
  document.addEventListener("click", (e) => {
    if (e.target === input || results.contains(e.target)) return;
    hideResults();
  });

  // helpers globales
  window.getSelectedClues = () => Array.from(selected.keys());
  window.clearClues = () => {
    selected.clear();
    renderChips();
    input.value = "";
    hideResults();
  };
  window.addManyClues = (items) => {
    // items: [{value, label}] o [{clues, unidad}] según tu endpoint
    if (!Array.isArray(items)) return;

    for (const it of items) {
      const value = it.value ?? it.clues ?? it.CLUES ?? it.clue;
      const label = it.label ?? it.unidad ?? it.unidad_nombre ?? value;

      if (!value) continue;
      if (selected.has(value)) continue;

      selected.set(value, label);
    }

    renderChips();
  };

});
function confirmWithModal({ title, html, okText } = {}) {
  return new Promise((resolve) => {
    const modalEl = document.getElementById("confirmPrefijoModal");
    const bodyEl = document.getElementById("confirmPrefijoBody");
    const titleEl = document.getElementById("avisoTitleconfirmPrefijoModal");
    const okBtn = document.getElementById("avisoActionBtnconfirmPrefijoModal");
    const closeBtn = document.getElementById("avisoCloseModalconfirmPrefijoModal");

    if (!modalEl || !bodyEl || !titleEl || !okBtn) {
      // fallback por si algo falta
      resolve(window.confirm("¿Continuar?"));
      return;
    }

    if (title) titleEl.textContent = title;
    if (html) bodyEl.innerHTML = html;
    if (okText) okBtn.textContent = okText;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    let decided = false;

    okBtn.onclick = () => {
      decided = true;
      modal.hide();
      resolve(true);
    };

    // si cierra por X, backdrop, ESC o Cancelar
    const onHidden = () => {
      modalEl.removeEventListener("hidden.bs.modal", onHidden);
      if (!decided) resolve(false);
    };

    modalEl.addEventListener("hidden.bs.modal", onHidden);

    // si tu botón cancelar tiene data-bs-dismiss ya cierra solo
    closeBtn?.addEventListener("click", () => {
      // no hacemos resolve aquí; lo hará hidden.bs.modal
    }, { once: true });

    modal.show();
  });
}

