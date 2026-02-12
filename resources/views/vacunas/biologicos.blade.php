<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-0">
            Biologicos por Clues con Unidad
        </h2>
    </x-slot>

    <div class="container">
        <div class="card">
            <div class="card-body">

                {{-- Inputs mínimos para prueba --}}
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Catálogo</label>
                        <input id="catalogoInput" class="form-control" value="SIS_2024">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Cubo</label>
                        <input id="cuboInput" class="form-control" value="SIS_2024">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">CLUES (separadas por coma)</label>
                        <input id="cluesInput" class="form-control" value="HGIMB000011">
                    </div>
                </div>

                <div class="mt-3">
                    <button id="btnConsultarPreview" class="btn btn-primary">
                        Consultar (Preview)
                    </button>
                    <x-precarga></x-precarga>
                </div>

                <div id="resumenPreview" class="alert alert-info d-none mt-3"></div>

                <div class="table-responsive mt-3">
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

        {{-- <x-precarga></x-precarga> --}}
    </div>
</x-app-layout>
