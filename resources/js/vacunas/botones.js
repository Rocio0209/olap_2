console.log("botones.js cargado ✅");

document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("cluesSearchInput");
  const select = document.getElementById("cluesSelect");

  const btnAllHG = document.getElementById("btnAddAllHG");
  const btnAllHGIMB = document.getElementById("btnAddAllHGIMB");
  const btnAllHGSSA = document.getElementById("btnAddAllHGSSA");
  const btnClear = document.getElementById("btnClearClues");

  if (!searchInput || !select) return;

  let t = null;
  let currentPrefix = ""; // lo cambian los botones

  // ---- helpers ----
  const getCatalogo = () => document.getElementById("catalogoInput")?.value?.trim() ?? "";
  const getCubo = () => document.getElementById("cuboInput")?.value?.trim() ?? "";
  const getEstado = () => "HIDALGO"; // luego lo haces dinámico si quieres

  function addOptions(items) {
    items.forEach(it => {
      const value = it.value;
      const label = it.label ?? it.value;

      // evitar duplicados
      if ([...select.options].some(o => o.value === value)) return;

      const opt = document.createElement("option");
      opt.value = value;
      opt.textContent = label;
      opt.selected = true; // 👈 importante: se agrega y queda seleccionada
      select.appendChild(opt);
    });
  }

  async function searchClues(q) {
    const params = new URLSearchParams({
      catalogo: getCatalogo(),
      cubo: getCubo(),
      estado: getEstado(),
      limit: "5",
      prefix: currentPrefix,
      q: q ?? "",
    });

    const res = await fetch(`/api/vacunas/clues/search?${params.toString()}`, {
      headers: { "Accept": "application/json" }
    });

    const data = await res.json();
    if (!res.ok || data.ok === false) {
      console.error("Error clues search:", data);
      return [];
    }
    return data.items ?? [];
  }

  // ---- input typing (debounce) ----
  searchInput.addEventListener("input", () => {
    clearTimeout(t);
    t = setTimeout(async () => {
      const q = searchInput.value.trim();
      if (q.length < 2) return;

      const items = await searchClues(q);
      addOptions(items);
    }, 300);
  });

  // ---- botones de prefijo ----
  btnAllHG?.addEventListener("click", async () => {
    currentPrefix = "HG";
    const items = await searchClues(searchInput.value.trim() || "HG");
    addOptions(items);
  });

  btnAllHGIMB?.addEventListener("click", async () => {
    currentPrefix = "HGIMB";
    const items = await searchClues(searchInput.value.trim() || "HGIMB");
    addOptions(items);
  });

  btnAllHGSSA?.addEventListener("click", async () => {
    currentPrefix = "HGSSA";
    const items = await searchClues(searchInput.value.trim() || "HGSSA");
    addOptions(items);
  });

  // ---- limpiar ----
  btnClear?.addEventListener("click", () => {
    select.innerHTML = "";
    searchInput.value = "";
    currentPrefix = "";
  });
});
