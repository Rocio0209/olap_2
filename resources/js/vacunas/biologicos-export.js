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

function resetProgressBar() {

    const container = document.getElementById("exportProgressContainer");
    const bar = document.getElementById("exportProgressBar");
    const percent = document.getElementById("exportProgressPercent");
    const downloadBtn = document.getElementById("btnDownloadExcel");

    if (!container || !bar || !percent) return;

    container.classList.remove("hidden");

    bar.style.width = "0%";
    percent.textContent = "0%";

    // 🔥 Sobrescribe todas las clases
    bar.className = "h-6 transition-all duration-700 ease-out bg-rose-800";

    if (downloadBtn) {
        downloadBtn.classList.add("hidden");
    }
}

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

        updateProgressBar(exportData.progress);

        if (exportData.status === "completed") {
            clearInterval(currentInterval);
            currentInterval = null;
            button.disabled = false;
            showDownloadButton(exportId);
        }

        if (exportData.status === "failed") {
            clearInterval(currentInterval);
            currentInterval = null;
            button.disabled = false;
        }

    }, 2000);
}

function updateProgressBar(progress) {

    const bar = document.getElementById("exportProgressBar");
    const percent = document.getElementById("exportProgressPercent");

    if (!bar || !percent) return;

    bar.style.width = `${progress}%`;
    percent.textContent = `${progress}%`;

    let color = "var(--colorInstitucional7)"; // 🔴 rojo institucional

    if (progress <= 33) {
        color = "var(--colorInstitucional7)";
    }
    else if (progress < 100) {
        color = "var(--colorInstitucional4)"; // 🟡 dorado institucional
    }
    else {
        color = "var(--colorInstitucional11)"; // 🟢 verde institucional
    }

    bar.style.backgroundColor = color;
}
function showDownloadButton(exportId) {

    const downloadBtn = document.getElementById("btnDownloadExcel");
    if (!downloadBtn) return;

    downloadBtn.classList.remove("hidden");

    downloadBtn.onclick = () => {
        window.location.href = `/api/vacunas/exports/${exportId}/download`;
    };
}