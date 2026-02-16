console.log("Biologicos Preview cargado correctamente 🚀");

document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("btnConsultarPreview");
    if (!btn) return;

    btn.addEventListener("click", async () => {
        try {
            btn.disabled = true;

            const catalogo = document.getElementById("catalogoInput")?.value?.trim();
            const cubo = document.getElementById("cuboInput")?.value?.trim();
            const clues = typeof window.getSelectedClues === "function"
        ? window.getSelectedClues()
        : getSelectedCluesFallback();

      if (!clues.length) {
        alert("Selecciona al menos 1 CLUES.");
        return;
      }


            const payload = { catalogo, cubo, clues };

            const res = await fetch("/api/vacunas/biologicos/preview", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();

            if (!res.ok || data.ok === false) {
                console.error("Error preview:", data);
                alert(data?.message ?? "Error al consultar preview");
                return;
            }

            // resumen
            renderResumen(data.summary);

            // tabla
            const table = data.table;
            const headerDef = renderHeadersNested(table, {
                tablaHeader: document.getElementById("tablaHeader"),
                variablesHeader: document.getElementById("variablesHeader"),
            });

            renderRowsNested(table, {
                tablaResultadosBody: document.getElementById("tablaResultadosBody"),
            }, headerDef);

        } catch (e) {
            console.error(e);
            alert("Error inesperado. Revisa consola.");
        } finally {
            btn.disabled = false;
        }
    });
});

function getSelectedCluesFallback() {
  const sel = document.getElementById("cluesSelect");
  if (!sel) return [];
  return Array.from(sel.selectedOptions).map(o => o.value).filter(Boolean);
}

function renderResumen(summary) {
    const el = document.getElementById("resumenPreview");
    if (!el) return;

    el.classList.remove("d-none");
    el.innerHTML = `
    <strong>${escapeHtml(summary?.message ?? "OK")}</strong><br>
    Total CLUES: ${escapeHtml(String(summary?.total_clues ?? 0))} |
    Filas (scope preview): ${escapeHtml(String(summary?.total_rows ?? 0))} |
    Preview rows: ${escapeHtml(String(summary?.preview_rows ?? 0))}
  `;
}

function buildNestedHeadersFromResponse(table) {
    const fixed = table.fixed_columns ?? [
        { key: "clues", label: "CLUES" },
        { key: "unidad_nombre", label: "Unidad" },
        { key: "entidad", label: "Entidad" },
        { key: "jurisdiccion", label: "Jurisdicción" },
        { key: "municipio", label: "Municipio" },
        { key: "institucion", label: "Institución" },
    ];

    let apartados = table.apartados;

    // Si el backend no manda apartados, inferimos desde la primera fila:
    if (!apartados) {
        const first = table.rows?.[0] ?? {};
        apartados = Object.keys(first)
            .filter(k => typeof first[k] === "object" && first[k] !== null && !Array.isArray(first[k]))
            .map(apKey => {
                const varsObj = first[apKey] ?? {};
                return {
                    key: apKey,
                    label: apKey,
                    variables: Object.keys(varsObj).map(vk => ({ key: vk, label: vk }))
                };
            });
    }

    return { fixed, apartados };
}

function renderHeadersNested(table, elementosDOM) {
    const { fixed, apartados } = buildNestedHeadersFromResponse(table);

    // Header fila 1
    let htmlTop = "";
    fixed.forEach(col => {
        htmlTop += `<th rowspan="2">${escapeHtml(col.label)}</th>`;
    });

    apartados.forEach(ap => {
        const colspan = ap.variables.length || 1;
        htmlTop += `<th colspan="${colspan}">${escapeHtml(ap.label)}</th>`;
    });

    // Header fila 2
    let htmlVars = "";
    apartados.forEach(ap => {
        if (!ap.variables.length) {
            htmlVars += `<th>(sin variables)</th>`;
            return;
        }
        ap.variables.forEach(v => {
            htmlVars += `<th>${escapeHtml(v.label)}</th>`;
        });
    });

    elementosDOM.tablaHeader.innerHTML = htmlTop;
    elementosDOM.variablesHeader.innerHTML = htmlVars;

    return { fixed, apartados };
}

function renderRowsNested(table, elementosDOM, headerDef) {
    const { fixed, apartados } = headerDef;
    const rows = table.rows ?? [];

    let html = "";

    rows.forEach(r => {
        let tr = "";

        fixed.forEach(col => {
            tr += `<td>${escapeHtml(String(r[col.key] ?? ""))}</td>`;
        });

        apartados.forEach(ap => {
            const obj = r[ap.key] ?? {};
            ap.variables.forEach(v => {
                const val = obj?.[v.key] ?? 0;
                tr += `<td>${escapeHtml(String(val))}</td>`;
            });
        });

        html += `<tr>${tr}</tr>`;
    });

    elementosDOM.tablaResultadosBody.innerHTML = html;
}

function escapeHtml(str) {
    return String(str)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

let btn = null;

document.addEventListener("DOMContentLoaded", () => {
  btn = document.getElementById("btnConsultarPreview");
  if (!btn) return;

btn.addEventListener("click", async () => {
    try {
        btn.disabled = true;

        const catalogo = document.getElementById("catalogoInput")?.value?.trim();
        const cubo = document.getElementById("cuboInput")?.value?.trim();

        const clues = getSelectedClues();

        if (!clues.length) {
            alert("Selecciona al menos 1 CLUES.");
            return;
        }

        const payload = { catalogo, cubo, clues };

        const res = await fetch("/api/vacunas/biologicos/preview", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (!res.ok || data.ok === false) {
            console.error("Error preview:", data);
            alert(data?.message ?? "Error al consultar preview");
            return;
        }

        renderResumen(data.summary);

        const table = data.table;
        const headerDef = renderHeadersNested(table, {
            tablaHeader: document.getElementById("tablaHeader"),
            variablesHeader: document.getElementById("variablesHeader"),
        });

        renderRowsNested(table, {
            tablaResultadosBody: document.getElementById("tablaResultadosBody"),
        }, headerDef);

    } catch (e) {
        console.error(e);
        alert("Error inesperado. Revisa consola.");
    } finally {
        btn.disabled = false;
    }
});
});


function getSelectedClues() {
    const sel = document.getElementById("cluesSelect");
    if (!sel) return [];
    return Array.from(sel.selectedOptions).map(o => o.value).filter(Boolean);
}

