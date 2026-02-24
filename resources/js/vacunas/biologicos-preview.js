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

            } catch (e) {
                console.error(e);
                alert("Error inesperado. Revisa consola.");
            } finally {
                btnPreview.disabled = false;
            }
        });
    }


    /*
    |--------------------------------------------------------------------------
    | EXPORTAR CON BARRA TAILWIND
    |--------------------------------------------------------------------------
    */

    const btnExport = document.getElementById("btnExportarExcel");

    btnExport?.addEventListener("click", async () => {

        const catalogo = document.getElementById("catalogoInput")?.value?.trim() ?? "";
        const cubo = document.getElementById("cuboInput")?.value?.trim() ?? "";
        const clues = window.getSelectedClues?.() ?? [];

        if (!clues.length) {
            alert("Selecciona al menos 1 CLUES.");
            return;
        }

        btnExport.disabled = true;

        // Reset barra
        const container = document.getElementById("exportProgressContainer");
        const bar = document.getElementById("exportProgressBar");
        const percent = document.getElementById("exportProgressPercent");
        const statusText = document.getElementById("exportStatusText");

        if (container && bar && percent && statusText) {
            container.classList.remove("d-none");
            bar.style.width = "0%";
            percent.textContent = "0%";
            statusText.textContent = "Iniciando...";
            bar.classList.remove("bg-blue-600", "bg-red-600");
            bar.classList.add("bg-green-500");
        }

        try {

            const res = await fetch("/api/vacunas/exports", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                },
                body: JSON.stringify({ catalogo, cubo, clues }),
            });

            const data = await res.json();

            if (!res.ok || !data.ok) {
                alert("Error creando export");
                btnExport.disabled = false;
                return;
            }

            const exportId = data.export.id;

            startPolling(exportId, btnExport);

        } catch (e) {
            console.error(e);
            alert("Error inesperado.");
            btnExport.disabled = false;
        }
    });

});


/*
|--------------------------------------------------------------------------
| POLLING PROGRESO
|--------------------------------------------------------------------------
*/

function startPolling(exportId, button) {

    const interval = setInterval(async () => {

        const res = await fetch(`/api/vacunas/exports/${exportId}`);
        const data = await res.json();

        if (!res.ok || !data.ok) {
            clearInterval(interval);
            button.disabled = false;
            alert("Error consultando progreso");
            return;
        }

        const exportData = data.export;

        console.log("Progreso:", exportData.progress);

        const container = document.getElementById("exportProgressContainer");
        const bar = document.getElementById("exportProgressBar");
        const percent = document.getElementById("exportProgressPercent");
        const statusText = document.getElementById("exportStatusText");

        if (container && bar && percent && statusText) {

            container.classList.remove("d-none");

            bar.style.width = `${exportData.progress}%`;
            percent.textContent = `${exportData.progress}%`;
            statusText.textContent = `Estado: ${exportData.status}`;

            if (exportData.status === "completed") {
                bar.classList.remove("bg-green-500");
                bar.classList.add("bg-blue-600");
            }

            if (exportData.status === "failed") {
                bar.classList.remove("bg-green-500");
                bar.classList.add("bg-red-600");
            }
        }

        if (exportData.status === "completed") {

    clearInterval(interval);
    button.disabled = false;

    const downloadBtn = document.getElementById("btnDownloadExcel");
    if (downloadBtn) {
        downloadBtn.classList.remove("hidden");

        downloadBtn.onclick = () => {
            window.location.href = `/api/vacunas/exports/${exportId}/download`;
        };
    }
}

if (exportData.status === "failed") {
    clearInterval(interval);
    button.disabled = false;
}

    }, 2000);
}


/*
|--------------------------------------------------------------------------
| FUNCIONES PREVIEW (SIN CAMBIOS)
|--------------------------------------------------------------------------
*/

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
    const fixed = table.fixed_columns ?? [];
    let apartados = table.apartados ?? [];

    return { fixed, apartados };
}

function renderHeadersNested(table, elementosDOM) {
    const { fixed, apartados } = buildNestedHeadersFromResponse(table);

    let htmlTop = "";
    fixed.forEach(col => { htmlTop += `<th rowspan="2">${escapeHtml(col.label)}</th>`; });

    apartados.forEach(ap => {
        htmlTop += `<th colspan="${ap.variables.length}">${escapeHtml(ap.label)}</th>`;
    });

    let htmlVars = "";
    apartados.forEach(ap => {
        ap.variables.forEach(v => { htmlVars += `<th>${escapeHtml(v.label)}</th>`; });
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

function escapeHtml(str) {
    return String(str)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}