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
      limit: "10",
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

  btnHG?.addEventListener("click", () => setPrefix("HG"));
  btnHGIMB?.addEventListener("click", () => setPrefix("HGIMB"));
  btnHGSSA?.addEventListener("click", () => setPrefix("HGSSA"));

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
});
