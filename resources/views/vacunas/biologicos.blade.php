<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-0">
            Biologicos por Clues con Unidad
        </h2>
    </x-slot>

    <div class="container">
        <div class="card">
            <div class="card-body">

                {{-- Inputs minimos para prueba --}}
                <div class="row g-2">

                    <div class="col-md-6">
                        <label class="form-label">SIS</label>
                        <select id="sisSelect" class="form-select">
                            <option value="">Cargando...</option>
                        </select>
                        <small class="text-muted">Selecciona el SIS (ej. SIS 2024).</small>
                    </div>

                    <div class="col-md-6" id="cuboWrap" style="display:none;">
                        <label class="form-label">Cubo</label>
                        <select id="cuboSelect" class="form-select"></select>
                    </div>

                    <input type="hidden" id="catalogoInput">
                    <input type="hidden" id="cuboInput">

                </div>


                <div class="col-md-11">
                    <label class="form-label">CLUES</label>
                    <div class="position-relative">
                        <input id="cluesInput" class="form-control" placeholder="Escribe CLUES o nombre de unidad...">

                        <div id="cluesResults" class="list-group position-absolute w-100 d-none"
                            style="top:100%; left:0; z-index:1050; max-height:220px; overflow:auto;">
                        </div>
                    </div>

                    <div id="cluesChips" class="d-flex flex-wrap gap-2 mt-2"
                        style="max-height: 200px; overflow-y: auto;"></div>


                    <div class="mt-2 d-flex gap-2 flex-wrap">
                        <button id="btnPrefixHG" type="button" class="btn btn-outline-secondary btn-sm">Prefijo
                            HG</button>
                        <button id="btnPrefixHGIMB" type="button" class="btn btn-outline-secondary btn-sm">Prefijo
                            HGIMB</button>
                        <button id="btnPrefixHGSSA" type="button" class="btn btn-outline-secondary btn-sm">Prefijo
                            HGSSA</button>
                        <button id="btnClearClues" type="button" class="btn btn-outline-danger btn-sm">Limpiar</button>
                    </div>

                </div>


            </div>

            <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
                <button id="btnConsultarPreview" class="btn btn-primary">
                    Consultar (Preview)
                </button>
                <button id="btnExportarExcel" class="btn btn-success ms-2 d-none">
                    Exportar a Excel
                </button>
                <button id="btnDownloadExcel" class="btn btn-danger ms-2 d-none">
                    Descargar Excel
                </button>
                <div id="previewMetaInfo"
                    class="d-none alert alert-info mb-0 ms-2 py-1 px-2 small"></div>

            </div>

            <div id="previewContainer" class="table-responsive mt-3">
                <table class="table table-bordered table-striped" id="tablaResultados">
                    <thead>
                        <tr id="tablaHeader"></tr>
                        <tr id="variablesHeader"></tr>
                    </thead>
                    <tbody id="tablaResultadosBody"></tbody>
                </table>
            </div>

        </div>
    </div>

    <x-modal-aviso id="confirmPrefijoModal" modaltype="warning" textTitle="Confirmar seleccion masiva"
        accionBtnTxt="Si, agregar" accionBtnClass="btn-primary" closeDataModalTxt="Cancelar"
        closeDataModalClass="btn-secondary" :showOk="true" :showCerrar="true">
        <div id="confirmPrefijoBody">
            <!-- aqui inyectamos el mensaje desde JS -->
        </div>
    </x-modal-aviso>

    @push('modals')
        <div class="modal fade" id="exportProgressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="exportProgressModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exportProgressModalLabel">Generando Excel</h5>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Tu archivo se esta preparando. Espera un momento...</p>
                        <x-progress-bar />
                    </div>
                </div>
            </div>
        </div>
    @endpush
</x-app-layout>
