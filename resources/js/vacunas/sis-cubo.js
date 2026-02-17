console.log("sis-cubos.js ✅");

document.addEventListener("DOMContentLoaded", () => {
    const sisSelect = document.getElementById("sisSelect");
    const cuboWrap = document.getElementById("cuboWrap");
    const cuboSelect = document.getElementById("cuboSelect");

    const catalogoInput = document.getElementById("catalogoInput");
    const cuboInput = document.getElementById("cuboInput");
    const cluesInput = document.getElementById("cluesInput");

    if (!sisSelect || !catalogoInput || !cuboInput) return;

    // Bloquea CLUES hasta que haya SIS
    if (cluesInput) {
        cluesInput.disabled = true;
        cluesInput.placeholder = "Primero selecciona SIS...";
    }

    // Guardamos mapping catálogo->cubos
    let mapping = {}; // { "Cubo solo sinba 2020": ["SIS_SINBA_2020"], ... }

    function yearFromCatalogName(cat) {
        const m = String(cat).match(/(\d{4})/);
        return m ? m[1] : "";
    }

    function makeLabel(cat) {
        // Muestra "SIS 2020" aunque el catálogo sea "Cubo solo sinba 2020" o "SIS_2019_2"
        const y = yearFromCatalogName(cat);
        if (!y) return cat;
        return `SIS ${y}`;
    }

    function setChosen(cat, cube) {
        catalogoInput.value = cat || "";
        cuboInput.value = cube || "";

        const ok = !!(catalogoInput.value && cuboInput.value);

        if (cluesInput) {
            cluesInput.disabled = !ok;
            cluesInput.placeholder = ok ? "Escribe CLUES o nombre de unidad..." : "Primero selecciona SIS...";
            if (ok) cluesInput.focus();
        }

        // limpia chips al cambiar
        window.clearClues?.();
    }

    function renderCuboSelect(cat) {
        const cubos = mapping[cat] || [];

        if (!cuboSelect || !cuboWrap) {
            // si no tienes el select de cubo en el blade, toma el primero
            setChosen(cat, cubos[0] || "");
            return;
        }

        if (cubos.length <= 1) {
            cuboWrap.style.display = "none";
            cuboSelect.innerHTML = "";
            setChosen(cat, cubos[0] || "");
            return;
        }

        cuboWrap.style.display = "";
        cuboSelect.innerHTML = cubos.map(c => `<option value="${c}">${c}</option>`).join("");

        // por defecto el primero
        setChosen(cat, cuboSelect.value);
    }

    async function loadSis() {
        try {
            sisSelect.innerHTML = `<option value="">Cargando...</option>`;

            const res = await fetch("/api/vacunas/catalogos_y_cubos_sis", {
                headers: { Accept: "application/json" },
            });

            const data = await res.json();

            if (!res.ok || !data?.catalogo) {
                console.error("Error cargando SIS:", data);
                sisSelect.innerHTML = `<option value="">Error al cargar</option>`;
                return;
            }

            mapping = data.catalogo;

            const cats = Object.keys(mapping);

            // Orden por año desc (2026, 2025, ...)
            cats.sort((a, b) => (yearFromCatalogName(b)).localeCompare(yearFromCatalogName(a)));

            sisSelect.innerHTML = `
        <option value="">Selecciona...</option>
        ${cats.map(cat => `<option value="${cat}">${makeLabel(cat)} — ${cat}</option>`).join("")}`;

        } catch (e) {
            console.error(e);
            sisSelect.innerHTML = `<option value="">Error al cargar</option>`;
        }
    }

    sisSelect.addEventListener("change", () => {
        const cat = sisSelect.value;
        if (!cat) {
            setChosen("", "");
            if (cuboWrap) cuboWrap.style.display = "none";
            return;
        }
        renderCuboSelect(cat);
    });

    cuboSelect?.addEventListener("change", () => {
        const cat = sisSelect.value;
        if (!cat) return;
        setChosen(cat, cuboSelect.value);
    });

    loadSis();
});
