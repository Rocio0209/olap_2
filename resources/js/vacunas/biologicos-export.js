let currentInterval = null;

console.log("Biologicos Export cargado correctamente 🚀");

document.addEventListener("DOMContentLoaded", () => {

    const btnExport = document.getElementById("btnExportarExcel");
    if (!btnExport) return;

    btnExport.addEventListener("click", async () => {

        const catalogo = document.getElementById("catalogoInput")?.value?.trim() ?? "";
        const cubo = document.getElementById("cuboInput")?.value?.trim() ?? "";
        const clues = window.getSelectedClues?.() ?? [];

        if (!clues.length) {
            alert("Selecciona al menos 1 CLUES.");
            return;
        }

        btnExport.disabled = true;

        resetProgressBar();

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

            startPolling(data.export.id, btnExport);

        } catch (e) {
            console.error(e);
            alert("Error inesperado.");
            btnExport.disabled = false;
        }
    });

});



/*
|--------------------------------------------------------------------------
| Reset Progress Bar
|--------------------------------------------------------------------------
*/

function resetProgressBar() {

    const container = document.getElementById("exportProgressContainer");
    const bar = document.getElementById("exportProgressBar");
    const percent = document.getElementById("exportProgressPercent");
    const downloadBtn = document.getElementById("btnDownloadExcel");

    if (!container || !bar || !percent) return;

    container.classList.remove("d-none");

    bar.style.width = "0%";
    percent.textContent = "0%";

    // color inicial rojo institucional
    bar.style.backgroundColor = "#A02142";

    // ocultar botón descarga
    downloadBtn?.classList.add("d-none");
}



/*
|--------------------------------------------------------------------------
| Polling
|--------------------------------------------------------------------------
*/

function startPolling(exportId, button) {

    if (currentInterval) {
        clearInterval(currentInterval);
    }

    currentInterval = setInterval(async () => {

        const res = await fetch(`/api/vacunas/exports/${exportId}`);
        const data = await res.json();

        if (!res.ok || !data.ok) {
            clearInterval(currentInterval);
            currentInterval = null;
            button.disabled = false;
            alert("Error consultando progreso");
            return;
        }

        const exportData = data.export;
        console.log("Progreso recibido:", exportData.progress);

        updateProgressBar(parseInt(exportData.progress));

        if (exportData.status === "completed") {
            document.getElementById("btnDownloadExcel")?.classList.add("d-none");
            clearInterval(currentInterval);
            currentInterval = null;
            button.disabled = false;
            showDownloadButton(exportId);
        }

        if (exportData.status === "failed") {
            clearInterval(currentInterval);
            currentInterval = null;
            button.disabled = false;
            alert("La exportación falló.");
        }

    }, 2000);
}



/*
|--------------------------------------------------------------------------
| Update Progress Bar
|--------------------------------------------------------------------------
*/
function updateProgressBar(progress) {

    const bar = document.getElementById("exportProgressBar");
    const percent = document.getElementById("exportProgressPercent");

    if (!bar || !percent) return;

    progress = Math.max(0, Math.min(100, progress));

    bar.style.width = `${progress}%`;
    percent.textContent = `${progress}%`;

    if (progress <= 33) {
        bar.style.backgroundColor = "#A02142"; // rojo
    }
    else if (progress < 100) {
        bar.style.backgroundColor = "#BC955B"; // dorado
    }
    else {
        bar.style.backgroundColor = "#235C4F"; // verde
    }
}



/*
|--------------------------------------------------------------------------
| Show Download Button
|--------------------------------------------------------------------------
*/

function showDownloadButton(exportId) {

    const downloadBtn = document.getElementById("btnDownloadExcel");
    if (!downloadBtn) return;

    downloadBtn.classList.remove("d-none");

    downloadBtn.onclick = () => {
        window.location.href = `/api/vacunas/exports/${exportId}/download`;
    };
}
