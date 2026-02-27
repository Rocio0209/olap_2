import * as Bootstrap from "bootstrap";

let currentInterval = null;
let currentDisplayedProgress = 0;
let progressAnimationFrame = null;
let exportProgressModalInstance = null;
let currentExportId = null;

console.log("Biologicos Export cargado correctamente");

document.addEventListener("DOMContentLoaded", () => {
    const btnExport = document.getElementById("btnExportarExcel");
    const btnCancelExport = document.getElementById("btnCancelExport");
    if (!btnExport) return;

    if (btnCancelExport && btnCancelExport.dataset.bound !== "1") {
        btnCancelExport.dataset.bound = "1";
        btnCancelExport.addEventListener("click", () => cancelCurrentExport(btnExport, btnCancelExport));
    }

    btnExport.addEventListener("click", async () => {
        const catalogo = document.getElementById("catalogoInput")?.value?.trim() ?? "";
        const cubo = document.getElementById("cuboInput")?.value?.trim() ?? "";
        const clues = window.getSelectedClues?.() ?? [];

        if (!clues.length) {
            alert("Selecciona al menos 1 CLUES.");
            return;
        }

        btnExport.disabled = true;
        showExportProgressModal();
        resetProgressBar();

        try {
            const res = await fetch("/api/vacunas/exports", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify({ catalogo, cubo, clues }),
            });

            const data = await res.json();

            if (!res.ok || !data.ok) {
                hideExportProgressModal();
                alert("Error creando export");
                btnExport.disabled = false;
                return;
            }

            currentExportId = data.export.id;
            startPolling(data.export.id, btnExport);
        } catch (e) {
            console.error(e);
            hideExportProgressModal();
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

    container.classList.remove("d-none");

    if (progressAnimationFrame) {
        cancelAnimationFrame(progressAnimationFrame);
        progressAnimationFrame = null;
    }

    currentDisplayedProgress = 0;
    bar.style.width = "0%";
    percent.textContent = "0%";
    bar.setAttribute("aria-valuenow", "0");
    bar.style.backgroundColor = "#A02142";
    bar.classList.add("progress-bar-striped", "progress-bar-animated");
    downloadBtn?.classList.add("d-none");
}

function startPolling(exportId, button) {
    if (currentInterval) {
        clearInterval(currentInterval);
    }

    currentInterval = setInterval(async () => {
        try {
            const res = await fetch(`/api/vacunas/exports/${exportId}`);
            const data = await res.json();

            if (!res.ok || !data.ok) {
                clearInterval(currentInterval);
                currentInterval = null;
                hideExportProgressModal();
                button.disabled = false;
                alert("Error consultando progreso");
                return;
            }

            const exportData = data.export;
            updateProgressBar(Number.parseInt(exportData.progress, 10));

            if (exportData.status === "completed") {
                clearInterval(currentInterval);
                currentInterval = null;
                updateProgressBar(100);

                setTimeout(() => {
                    hideExportProgressModal();
                    button.disabled = false;
                    showDownloadButton(exportId);
                    currentExportId = null;
                }, 450);
            }

            if (exportData.status === "failed") {
                clearInterval(currentInterval);
                currentInterval = null;
                hideExportProgressModal();
                button.disabled = false;
                alert("La exportacion fallo.");
                currentExportId = null;
            }

            if (exportData.status === "cancelled") {
                clearInterval(currentInterval);
                currentInterval = null;
                hideExportProgressModal();
                button.disabled = false;
                alert("La exportacion fue cancelada.");
                currentExportId = null;
            }
        } catch (e) {
            console.error(e);
            clearInterval(currentInterval);
            currentInterval = null;
            hideExportProgressModal();
            button.disabled = false;
            alert("Error de red consultando progreso.");
            currentExportId = null;
        }
    }, 2000);
}

function updateProgressBar(progress) {
    const bar = document.getElementById("exportProgressBar");
    const percent = document.getElementById("exportProgressPercent");

    if (!bar || !percent) return;
    if (!Number.isFinite(progress)) return;

    const targetProgress = Math.max(0, Math.min(100, progress));
    const startProgress = currentDisplayedProgress;
    const delta = targetProgress - startProgress;

    if (Math.abs(delta) < 0.1) {
        renderProgressState(targetProgress, bar, percent);
        currentDisplayedProgress = targetProgress;
        return;
    }

    if (progressAnimationFrame) {
        cancelAnimationFrame(progressAnimationFrame);
    }

    const duration = Math.min(900, Math.max(300, Math.abs(delta) * 25));
    const startedAt = performance.now();

    const step = (now) => {
        const elapsed = now - startedAt;
        const t = Math.min(1, elapsed / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        const interpolated = startProgress + delta * eased;

        renderProgressState(interpolated, bar, percent);

        if (t < 1) {
            progressAnimationFrame = requestAnimationFrame(step);
            return;
        }

        currentDisplayedProgress = targetProgress;
        progressAnimationFrame = null;
    };

    progressAnimationFrame = requestAnimationFrame(step);
}

function renderProgressState(progress, bar, percent) {
    const rounded = Math.round(progress);

    bar.style.width = `${progress.toFixed(1)}%`;
    percent.textContent = `${rounded}%`;
    bar.setAttribute("aria-valuenow", String(rounded));

    if (progress <= 33) {
        bar.style.backgroundColor = "#A02142";
    } else if (progress < 100) {
        bar.style.backgroundColor = "#BC955B";
    } else {
        bar.style.backgroundColor = "#235C4F";
        bar.classList.remove("progress-bar-animated");
    }
}

function showDownloadButton(exportId) {
    const downloadBtn = document.getElementById("btnDownloadExcel");
    if (!downloadBtn) return;

    downloadBtn.classList.remove("d-none");
    downloadBtn.onclick = () => {
        window.location.href = `/api/vacunas/exports/${exportId}/download`;
    };
}

function showExportProgressModal() {
    const modalEl = document.getElementById("exportProgressModal");
    if (!modalEl) return;

    exportProgressModalInstance = Bootstrap.Modal.getOrCreateInstance(modalEl, {
        backdrop: "static",
        keyboard: false,
    });
    exportProgressModalInstance.show();
}

function hideExportProgressModal() {
    const modalEl = document.getElementById("exportProgressModal");
    if (!modalEl) return;

    const modal = exportProgressModalInstance ?? Bootstrap.Modal.getInstance(modalEl);
    modal?.hide();
}

async function cancelCurrentExport(exportButton, cancelButton) {
    if (!currentExportId) {
        hideExportProgressModal();
        exportButton.disabled = false;
        return;
    }

    const confirmed = await confirmCancelExportWithModal();
    if (!confirmed) {
        return;
    }

    cancelButton.disabled = true;

    try {
        const res = await fetch(`/api/vacunas/exports/${currentExportId}/cancel`, {
            method: "POST",
            headers: {
                Accept: "application/json",
            },
        });

        const data = await res.json();
        if (!res.ok || !data.ok) {
            alert(data?.message ?? "No se pudo cancelar la exportacion.");
            return;
        }

        if (currentInterval) {
            clearInterval(currentInterval);
            currentInterval = null;
        }

        hideExportProgressModal();
        exportButton.disabled = false;
        currentExportId = null;
    } catch (e) {
        console.error(e);
        alert("Error cancelando exportacion.");
    } finally {
        cancelButton.disabled = false;
    }
}

function confirmCancelExportWithModal() {
    return new Promise((resolve) => {
        const modalEl = document.getElementById("cancelExportModal");
        const bodyEl = document.getElementById("cancelExportBody");
        const titleEl = document.getElementById("avisoTitlecancelExportModal");
        const okBtn = document.getElementById("avisoActionBtncancelExportModal");

        if (!modalEl || !okBtn) {
            resolve(window.confirm("¿Deseas cancelar la exportacion actual?"));
            return;
        }

        if (bodyEl) {
            bodyEl.textContent = "¿Deseas cancelar la exportacion actual?";
        }
        if (titleEl) {
            titleEl.textContent = "Cancelar exportacion";
        }

        const modal = Bootstrap.Modal.getOrCreateInstance(modalEl);
        let decided = false;

        // Forzar apilamiento por encima del modal de progreso.
        modalEl.style.zIndex = "2000";
        modalEl.addEventListener("shown.bs.modal", () => {
            const backdrops = document.querySelectorAll(".modal-backdrop");
            const lastBackdrop = backdrops[backdrops.length - 1];
            if (lastBackdrop) {
                lastBackdrop.style.zIndex = "1995";
            }
        }, { once: true });

        okBtn.onclick = () => {
            decided = true;
            modal.hide();
            resolve(true);
        };

        const onHidden = () => {
            modalEl.removeEventListener("hidden.bs.modal", onHidden);
            if (!decided) {
                resolve(false);
            }
        };

        modalEl.addEventListener("hidden.bs.modal", onHidden);
        modal.show();
    });
}
