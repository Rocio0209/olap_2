console.log("clues-select2.js ✅");

document.addEventListener("DOMContentLoaded", () => {
  const selectEl = document.getElementById("cluesSelect");
  if (!selectEl) return;

  if (!window.$ || !$.fn.select2) {
    console.error("Falta cargar jQuery/Select2.");
    return;
  }

  const btnHG = document.getElementById("btnAllHG");
  const btnHGIMB = document.getElementById("btnAllHGIMB");
  const btnHGSSA = document.getElementById("btnAllHGSSA");
  const btnClear = document.getElementById("btnClearClues");

  const getCatalogo = () => document.getElementById("catalogoInput")?.value?.trim() ?? "";
  const getCubo = () => document.getElementById("cuboInput")?.value?.trim() ?? "";
  const getEstado = () => "HIDALGO";

  let currentPrefix = "";

  // 👇 IMPORTANTÍSIMO: asegúrate que NO esté disabled
  selectEl.disabled = false;

  const $select = $(selectEl).select2({
    width: "100%",
    placeholder: "Escribe para buscar CLUES…",
    closeOnSelect: true,
    minimumInputLength: 2,
    ajax: {
      delay: 250,
      transport: async (params, success, failure) => {
        try {
          const q = params.data.term ?? "";

          const qs = new URLSearchParams({
            catalogo: getCatalogo(),
            cubo: getCubo(),
            estado: getEstado(),
            limit: "10",
            prefix: currentPrefix,
            q,
          });

          const res = await fetch(`/api/vacunas/clues/search?${qs.toString()}`, {
            headers: { Accept: "application/json" },
          });

          const data = await res.json();
          if (!res.ok || data.ok === false) return success({ results: [] });

          const selected = $select.val() ?? [];

          const results = (data.items ?? [])
            .filter(it => !selected.includes(it.value))
            .map(it => ({ id: it.value, text: it.label ?? it.value }));

          success({ results });
        } catch (e) {
          console.error(e);
          failure(e);
        }
      },
      processResults: d => d,
    },
  });

  // ✅ Limpia el texto al seleccionar
  $select.on("select2:select", () => {
    // abre y limpia para que sigas escribiendo sin basura
    setTimeout(() => {
      $select.select2("open");
      const input = document.querySelector(".select2-container--open .select2-search__field");
      if (input) input.value = "";
    }, 0);
  });

  // Botones solo cambian prefijo y abren selector (para escribir de inmediato)
  const setPrefix = (p) => {
    currentPrefix = p;
    $select.select2("open");
    setTimeout(() => {
      const input = document.querySelector(".select2-container--open .select2-search__field");
      if (input) input.focus();
    }, 0);
  };

  btnHG?.addEventListener("click", () => setPrefix("HG"));
  btnHGIMB?.addEventListener("click", () => setPrefix("HGIMB"));
  btnHGSSA?.addEventListener("click", () => setPrefix("HGSSA"));

  btnClear?.addEventListener("click", () => {
    currentPrefix = "";
    $select.val(null).trigger("change");
  });

  window.getSelectedClues = () => ($select.val() ?? []).filter(Boolean);
});
