console.log("Biologicos Preview cargado correctamente 🚀");

document.addEventListener("DOMContentLoaded", () => {

    /*
    |--------------------------------------------------------------------------
    | PREVIEW
    |--------------------------------------------------------------------------
    */

    const btnPreview = document.getElementById("btnConsultarPreview");
    if (btnPreview && btnPreview.dataset.bound !== "1") {

        btnPreview.dataset.bound = "1";

        btnPreview.addEventListener("click", async () => {
            console.log("CLICK PREVIEW - handler único ✅");

            try {
                btnPreview.disabled = true;

                const catalogo = document.getElementById("catalogoInput")?.value?.trim() ?? "";
                const cubo = document.getElementById("cuboInput")?.value?.trim() ?? "";
                const clues = window.getSelectedClues?.() ?? [];

                if (!clues.length) {
                    alert("Selecciona al menos 1 CLUES.");
                    return;
                }

                const res = await fetch("/api/vacunas/biologicos/preview", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    body: JSON.stringify({ catalogo, cubo, clues }),
                });

                const data = await res.json();

                if (!res.ok || data.ok === false) {
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
                document.getElementById("previewContainer")?.classList.remove("d-none");

            } catch (e) {
                console.error(e);
                alert("Error inesperado. Revisa consola.");
            } finally {
                btnPreview.disabled = false;
            }
        });
    }
});
function renderHeadersNested(table, elementosDOM) {

    const { fixed, apartados } = buildNestedHeadersFromResponse(table);

    let htmlTop = "";
    fixed.forEach(col => {
        htmlTop += `<th rowspan="2">${escapeHtml(col.label)}</th>`;
    });

    apartados.forEach(ap => {
        htmlTop += `<th colspan="${ap.variables.length}">
                        ${escapeHtml(ap.label)}
                    </th>`;
    });

    let htmlVars = "";
    apartados.forEach(ap => {
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
                tr += `<td>${escapeHtml(String(obj[v.key] ?? 0))}</td>`;
            });
        });

        html += `<tr>${tr}</tr>`;
    });

    elementosDOM.tablaResultadosBody.innerHTML = html;
}

function renderResumen(summary) {
    const el = document.getElementById("resumenPreview");
    if (!el) return;

    el.classList.remove("hidden");

    el.innerHTML = `
        <strong>${escapeHtml(summary?.message ?? "OK")}</strong><br>
        Total CLUES: ${escapeHtml(String(summary?.total_clues ?? 0))} |
        Filas (scope preview): ${escapeHtml(String(summary?.total_rows ?? 0))} |
        Preview rows: ${escapeHtml(String(summary?.preview_rows ?? 0))}
    `;
}
function escapeHtml(str) {
    return String(str)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}
function buildNestedHeadersFromResponse(table) {
    const fixed = table.fixed_columns ?? [];
    let apartados = table.apartados ?? [];
    return { fixed, apartados };
}
// 👇 AGREGA ESTO AL FINAL
window.clearPreview = function () {

    const container = document.getElementById("previewContainer");
    const resumen = document.getElementById("resumenPreview");

    if (container) {
        container.classList.add("d-none");
    }

    if (resumen) {
        resumen.classList.add("d-none");
        resumen.innerHTML = "";
    }

    const header1 = document.getElementById("tablaHeader");
    if (header1) header1.innerHTML = "";

    const header2 = document.getElementById("variablesHeader");
    if (header2) header2.innerHTML = "";

    const body = document.getElementById("tablaResultadosBody");
    if (body) body.innerHTML = "";
};