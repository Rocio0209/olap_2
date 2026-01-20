<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-0">
            Unidades
        </h2>
    </x-slot>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="accordion" id="accordionFiltros">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingFiltros">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFiltros" aria-expanded="true" aria-controls="collapseFiltros">
                                Filtros
                            </button>
                        </h2>
                        <div id="collapseFiltros" class="accordion-collapse collapse" aria-labelledby="headingFiltros" data-bs-parent="#accordionFiltros">
                            <div class="accordion-body">
                                <form action="" method="POST" id="filtrosForm">
                                    <fieldset class="row">
                                        <x-formElement classdivcampo=dtfilter  attrdivcampo='column=6'  contenedor=true classaddcontenedor="col-sm-3" ancholabel=12 anchocampo=12 id="f_idtipo_unidad" label="Tipo Unidad" obligatorio=true tipo="select"  classcampo="select2Filtro"  >
                                            <option value="all">Todos...</option>
                                            @foreach( $tiposunidad as $key => $values )
                                                <option value="{{ $values->tipo_unidad }}" >{{ $values->tipo_unidad }}</option>
                                            @endforeach
                                        </x-formElement>
                                        <x-formElement classdivcampo=dtfilter  attrdivcampo='column=9' contenedor=true classaddcontenedor="col-sm-3" ancholabel=12 anchocampo=12 id="f_idtipo_establecimiento" label="Tipo Establecimiento" obligatorio=true tipo="select"  classcampo="select2Filtro"  >
                                            <option value="all">Todos...</option>
                                            @foreach( $tiposestablecimiento as $key => $values )
                                                <option value="{{ $values->tipo_establecimiento }}" >{{ $values->tipo_establecimiento }}</option>
                                            @endforeach
                                        </x-formElement>
                                        <x-formElement classdivcampo=dtfilter  attrdivcampo='column=10' contenedor=true classaddcontenedor="col-sm-3" ancholabel=12 anchocampo=12 id="f_idtipologia_unidad" label="Tipología Unidad" obligatorio=true tipo="select" classcampo="select2Filtro"  >
                                            <option value="all">Todos...</option>
                                            @foreach( $tipologiasunidad as $key => $values )
                                                <option value="{{ $values->clave_tipologia }}" >{{ $values->clave_tipologia }} - {{ $values->tipologia_unidad }}</option>
                                            @endforeach
                                        </x-formElement>
                                        <x-formElement classdivcampo=dtfilter  attrdivcampo='column=7' contenedor=true classaddcontenedor="col-sm-3" ancholabel=12 anchocampo=12 id="f_idtipo_administracion" label="Tipo Administración" obligatorio=true tipo="select"  classcampo="select2Filtro" >
                                            <option value="all">Todos...</option>
                                            @foreach( $tiposadministracion as $key => $values )
                                                <option value="{{ $values->tipo_administracion }}" >{{ $values->tipo_administracion }}</option>
                                            @endforeach
                                        </x-formElement>
                                        <x-formElement classdivcampo=dtfilter  attrdivcampo='column=8' contenedor=true classaddcontenedor="col-sm-3" ancholabel=12 anchocampo=12 id="f_idnivel_atencion" label="Nivel Atención" obligatorio=true tipo="select" classcampo="select2Filtro"  >
                                            <option value="all">Todos...</option>
                                            @foreach( $nivelesatencion as $key => $values )
                                                <option value="{{ $values->nivel_atencion }}" >{{ $values->nivel_atencion }}</option>
                                            @endforeach
                                        </x-formElement>
                                        <x-formElement classdivcampo=dtfilter  attrdivcampo='column=31' contenedor=true classaddcontenedor="col-sm-3" ancholabel=12 anchocampo=12 id="f_idstatus_unidad" label="Estatus de Operación" obligatorio=true tipo="select" classcampo="select2Filtro"  >
                                            <option value="all">Todos...</option>
                                            @foreach( $statusunidades as $key => $values )
                                                <option value="{{ $values->status_unidad }}" >{{ $values->status_unidad }}</option>
                                            @endforeach
                                        </x-formElement>

                                    </fieldset>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                {{ $dataTable->table() }}
                @can('add_unidades')
                    <button id="agregar" type="button" class="btn btn-success"  data-bs-toggle="modal" data-bs-target="#modalData">Agregar</button>
                @endcan
            </div>
        </div>
        <x-precarga></x-precarga>
    </div>
    @push('styles')
        <style>
            div.collapse{
                visibility: visible!important;
            }

            .accordion-button:not(.collapsed) {
                background: var(--colorInstitucional3)!important;
                color: var(--colorInstitucional2)!important;
            }
        </style>
    @endpush
    @push('scripts')
        <script type="module" nonce="{{ csp_nonce() }}">
            window.fillfiltersdrop=function(api){
                $('div.dtfilter').each(function(){
                    var divfilter=$(this);
                    var idfilter=$(this).attr('id');
                    var columnindex=$(this).attr('column');

                    var selected=$("div.dtfilter[column='"+columnindex+"'] select").val();
                    if( selected === undefined || selected === '' ){
                        var column = api.column(columnindex);
                        $("div.dtfilter[column='"+columnindex+"'] select").off( 'change' ).on( 'change', function () {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            if(val==="all"){
                                val="";
                            }
                            column.search( val ? '^'+val+'$' : '', true, false ).draw();
                            fillfiltersdrop(api);
                        } ).select2({
                            theme: 'bootstrap-5',
                        });
                    }
                });
            }
        </script>
        {{ $dataTable->scripts(attributes: ['type' => 'module', 'nonce' => csp_nonce()]) }}
        <script type="module" nonce="{{ csp_nonce() }}">
            $(document).ready(function(){
                //Variables Globales
                const elemento='Unidad';
                const modalData = new bootstrap.Modal('#modalData', {  keyboard: false});
                const validaModal = new bootstrap.Modal('#validaModal', {  keyboard: false});
                const confirmaModal = new bootstrap.Modal('#confirmaModal', {  keyboard: false});
                const eliminaModal = new bootstrap.Modal('#eliminaModal', {  keyboard: false});

                //Abrir Modal Para Agregar
                $('#agregar').click(function(){
                    window.clear_form('#dataFormmodalData');
                    $('#DataTitlemodalData').text('Agregar '+elemento);
                    $('input.id2up').remove();
                    $('div.tiperror').remove();

                    window.inicializaSelect2({parent:'modalData'});
                });

                //Preparación de la información para su envio en elementos nuevos
                $('#accionBtnmodalData').click(function (e) {
                    let idbtn=$(this).attr('id');
                    let btnText=$(this).html();
                    let idform='dataFormmodalData';
                    window.showcarga();
                    e.preventDefault();
                    //Notificar envío de datos
                    $(this).html('Enviando...');
                    //Validar del lado del cliente la información
                    if(window.valida('#'+idform)){
                        $.ajax({
                            data: $('#'+idform).serialize(),
                            url: "{{ route('unidades-api.store') }}",
                            type: "POST",
                            dataType: 'json',
                            success: function (data) {
                                window.hidecarga();
                                //Reset del Formulario
                                $('#'+idbtn).html(btnText);
                                $('#'+idform).trigger("reset");
                                //Ocultar modal
                                $('#closeDataModalmodalData').click();

                                if(data.success!==undefined){
                                    $('#avisoModalBodyconfirmaModal').html('<h6 style="color:green;">'+data.success+'</h6>');
                                    const modalToggle = document.getElementById('confirmaModal');
                                    confirmaModal.show(modalToggle);

                                    setTimeout(() => {
                                        window.clear_form('#'+idform);
                                        $('#avisoCloseModalconfirmaModal').click();
                                    }, 1500);
                                }

                                //refresh table;
                                window.LaravelDataTables["dataTable-table"].ajax.reload();
                            },
                            error: function (data) {
                                $('#'+idbtn).html(btnText);
                                window.hidecarga();
                                let mensaje = window.mostrar_errores(data);

                                //En caso de errores back mostrar ventana de notificación
                                $('#avisoModalBodyvalidaModal').html('<h6 style="color:red;">Favor de verificar la información.</h6>'+mensaje);
                                const modalToggle = document.getElementById('validaModal');
                                validaModal.show(modalToggle);
                            }
                        });
                    }else{
                        window.hidecarga();
                        //En caso de fallar la validación mostrar modal de advertencia
                        $('#'+idbtn).html(btnText);
                        $('#avisoModalBodyvalidaModal').html('<h6 style="color:red;">Favor de llenar todos los campos obligatorios.</h6>');
                        const modalToggle = document.getElementById('validaModal');
                        validaModal.show(modalToggle);
                    }
                });

                //Acción de eliminación
                $('#dataTable-table').on('click','a.eliminar',function(e){
                    e.preventDefault();

                    window.gestionar_id($(this), 'delete', '#avisoActionBtneliminaModal');

                    //abrir modal de eliminación
                    const modalToggle = document.getElementById('eliminaModal');
                    eliminaModal.show(modalToggle);
                });

                /*Ejecutar Eliminación*/
                $('#avisoActionBtneliminaModal').click(function(){
                    //Notificar envío de datos
                    let idbtn=$(this).attr('id');
                    let btnText=$(this).html();
                    $(this).html('Eliminando...');
                    window.showcarga();
                    var ids = window.gestionar_id($(this));

                    $.ajax({
                        type: "DELETE",
                        url: "{{ route('unidades-api.store') }}"+"/"+ids.id1,
                        headers:{
                            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function (data) {
                            //Reset del Formulario
                            $('#'+idbtn).html(btnText);
                            $('#avisoCloseModaleliminaModal').click();

                            if(data.success!==undefined){
                                $('#avisoModalBodyconfirmaModal').html('<h6 style="color:green;">'+data.success+'</h6>');
                                const modalToggle = document.getElementById('confirmaModal');
                                confirmaModal.show(modalToggle);

                                setTimeout(() => {
                                    $('#avisoCloseModalconfirmaModal').click();
                                }, 1500);
                            }

                            //refresh table;
                            window.LaravelDataTables["dataTable-table"].ajax.reload();
                            window.hidecarga();
                        },
                        error: function (data) {
                            window.hidecarga();
                            //Reset del Formulario
                            $('#'+idbtn).html(btnText);
                            let mensaje = window.mostrar_errores(data);

                            //En caso de errores back mostrar ventana de notificación
                            $('#avisoModalBodyvalidaModal').html('<h6 style="color:red;">Ocurrió un error al procesar la petición.</h6>'+mensaje);
                            const modalToggle = document.getElementById('validaModal');
                            validaModal.show(modalToggle);
                        }
                    });
                });

                /*Modificación de datos*/
                $('#dataTable-table').on('click','a.modificar', function () {
                    window.showcarga();
                    let idform='modalData';
                    window.clear_form('#dataForm'+idform);
                    $('input.id2up').remove();
                    $('div.tiperror').remove();

                    var ids = window.gestionar_id($(this), 'update', '#dataForm'+idform);

                    $.get("{{ route('unidades-api.index') }}" +'/' + ids.id1 +'/edit', function (data) {
                        $('#DataTitle'+idform).text('Modificar '+elemento);
                        const modalToggle = document.getElementById('modalData');
                        modalData.show(modalToggle);

                        
                        $('#idestado').val(data.idestado).change();
                        $('#idregional').val(data.municipio.idregional).change();
                        window.precargar_form('#dataForm'+idform, data, ['idestado', 'idregional']);

                        window.inicializaSelect2({parent:'modalData'});

                        window.hidecarga();
                    }).fail(function(data) {
                        window.hidecarga();
                        let mensaje = window.mostrar_errores(data);

                        //En caso de errores back mostrar ventana de notificación
                        $('#avisoModalBodyvalidaModal').html('<h6 style="color:red;">Ocurrió un error al procesar la petición.</h6>'+mensaje);
                        const modalToggle = document.getElementById('validaModal');
                        validaModal.show(modalToggle);
                    })
                });

                /********************Controlar cambios de change de un select**********************/
                $('body').on('change','select',function(){
                    const csrfToken = document.head.querySelector("[name~=csrf-token][content]").content;

                    var id=$(this).attr('id');
                    var value=$(this).val();

                    switch(id){
                        case 'idestado':
                            if(value!==""){
                                $.ajax({
                                    url: "/regionales-select",
                                    type: "POST",
                                    async: false,
                                    data: JSON.stringify({id : value}),
                                    headers:{
                                        'Content-Type': 'application/json',
                                        "X-CSRF-Token": csrfToken
                                    },
                                    success: function(data) {
                                        var opciones ="<option value=''>Seleccione...</option>";
                                        for (let i in data.lista) {
                                            opciones+= '<option data-estado="' + +data.lista[i].idestado + '" value="'+data.lista[i].idregional+'">('+data.lista[i].idregional+') '+data.lista[i].regional+'</option>';
                                        }
                                        $('#idregional').html(opciones).change();
                                    },error: function (data) {
                                        //En caso de errores back mostrar ventana de notificación
                                        $('#validateModalBody').html('<h6 style="color:red;">Ocurrió un error al procesar la petición.</h6>');
                                        const modalToggle = document.getElementById('validateModal');
                                        validateModal.show(modalToggle);
                                    }
                                });
                            }else{
                                $('#idregional').html("<option value=''>Seleccione...</option>").change();
                            }
                            break;
                        case 'idregional':
                            if(value!==""){
                                let estado = $('#idregional option[value="' + value + '"]').data('estado');
                                $.ajax({
                                    url: "/municipiosRegional-select",
                                    type: "POST",
                                    async: false,
                                    data: JSON.stringify({estado : estado, regional : value}),
                                    headers:{
                                        'Content-Type': 'application/json',
                                        "X-CSRF-Token": csrfToken
                                    },
                                    success: function(data) {
                                        var opciones ="<option value=''>Seleccione...</option>";
                                        for (let i in data.lista) {
                                            opciones+= '<option value="'+data.lista[i].idmunicipio+'">('+data.lista[i].idmunicipio+') '+data.lista[i].municipio+'</option>';
                                        }
                                        $('#idmunicipio').html(opciones).change();
                                    },error: function (data) {
                                        //En caso de errores back mostrar ventana de notificación
                                        $('#validateModalBody').html('<h6 style="color:red;">Ocurrió un error al procesar la petición.</h6>');
                                        const modalToggle = document.getElementById('validateModal');
                                        validateModal.show(modalToggle);
                                    }
                                });
                            }else{
                                $('#idmunicipio').html("<option value=''>Seleccione...</option>").change();
                            }
                            break;
                        case 'idmunicipio':
                            if(value!==""){
                                $.ajax({
                                    url: "/localidades-select",
                                    type: "POST",
                                    async: false,
                                    data: JSON.stringify({id : $('#idestado').val(), id2: value}),
                                    headers:{
                                        'Content-Type': 'application/json',
                                        "X-CSRF-Token": csrfToken
                                    },
                                    success: function(data) {
                                        var opciones ="<option value=''>Seleccione...</option>";
                                        for (let i in data.lista) {
                                            opciones+= '<option value="'+data.lista[i].idlocalidad+'">('+data.lista[i].idlocalidad+') '+data.lista[i].localidad+'</option>';
                                        }
                                        $('#idlocalidad').html(opciones);
                                    },error: function (data) {
                                        //En caso de errores back mostrar ventana de notificación
                                        $('#validateModalBody').html('<h6 style="color:red;">Ocurrió un error al procesar la petición.</h6>');
                                        const modalToggle = document.getElementById('validateModal');
                                        validateModal.show(modalToggle);
                                    }
                                });
                            }else{
                                $('#idlocalidad').html("<option value=''>Seleccione...</option>").change();
                            }
                            break;
                    }
                });

                window.inicializaSelect2({parent:'filtrosForm'},'.select2Filtro');
            });
        </script>
    @endpush

    @push('modals')
        <!-- Modal Form para Agregar y Modificar Información-->
        <x-modal-form>
            @csrf
            <x-formElement id="clues" label="CLUES" obligatorio=true maxlength="13" minlength="13" classcampo="mayusculas"></x-formElement>
            <x-formElement id="nombre" label="Nombre" obligatorio=true classcampo="mayusculas"></x-formElement>
            <x-formElement id="idestado" label="Estado" obligatorio=true tipo="select" classcampo="select2" >
                @foreach( $estados as $key => $values )
                    <option value="{{ $values->idestado }}" >{{ $values->estado }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idregional" label="Regional" obligatorio=true tipo="select" classcampo="select2" ></x-formElement>
            <x-formElement id="idmunicipio" label="Municipio" obligatorio=true tipo="select" classcampo="select2" ></x-formElement>
            <x-formElement id="idlocalidad" label="Localidad" obligatorio=true tipo="select" classcampo="select2" ></x-formElement>
            <x-formElement id="idtipo_unidad" label="Tipo Unidad" obligatorio=true tipo="select"  classcampo="select2"  >
                @foreach( $tiposunidad as $key => $values )
                    <option value="{{ $values->idtipo_unidad }}" >{{ $values->tipo_unidad }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idtipo_establecimiento" label="Tipo Establecimiento" obligatorio=true tipo="select"  classcampo="select2"  >
                @foreach( $tiposestablecimiento as $key => $values )
                    <option value="{{ $values->idtipo_establecimiento }}" >{{ $values->tipo_establecimiento }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idtipologia_unidad" label="Tipología Unidad" obligatorio=true tipo="select" classcampo="select2"  >
                @foreach( $tipologiasunidad as $key => $values )
                    <option value="{{ $values->idtipologia_unidad }}" >{{ $values->clave_tipologia }} - {{ $values->tipologia_unidad }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idsubtipologia" label="Sub-Tipología Unidad" obligatorio=true tipo="select" classcampo="select2"  >
                @foreach( $subtipologiasunidad as $key => $values )
                    <option value="{{ $values->idsubtipologia }}" >{{ $values->subtipologia }} - {{ $values->descripcion_subtipologia }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idestrato" label="Estrato" obligatorio=true tipo="select" classcampo="select2"  >
                @foreach( $estratos as $key => $values )
                    <option value="{{ $values->idestrato }}" >{{ $values->estrato }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idinstitucion" label="Institución" obligatorio=true tipo="select" classcampo="select2"  >
                @foreach( $instituciones as $key => $values )
                    <option value="{{ $values->idinstitucion }}" {{ $values->idinstitucion == 4 ? "selected" : "" }} >{{ $values->institucion }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idtipo_administracion" label="Tipo Administración" obligatorio=true tipo="select"  classcampo="select2" >
                @foreach( $tiposadministracion as $key => $values )
                    <option value="{{ $values->idtipo_administracion }}" >{{ $values->tipo_administracion }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idnivel_atencion" label="Nivel Atención" obligatorio=true tipo="select" classcampo="select2"  >
                @foreach( $nivelesatencion as $key => $values )
                    <option value="{{ $values->idnivel_atencion }}" >{{ $values->nivel_atencion }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idstatus_propiedad" label="Estado de Propiedad" obligatorio=false tipo="select" classcampo="select2"  >
                @foreach( $statuspropiedad as $key => $values )
                    <option value="{{ $values->idstatus_propiedad }}" >{{ $values->status_propiedad }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idtipo_vialidad" label="Tipo Vialidad" obligatorio=true tipo="select" classcampo="select2"  >
                @foreach( $tiposvialidades as $key => $values )
                    <option value="{{ $values->idtipo_vialidad }}" >{{ $values->tipo_vialidad }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="vialidad" label="Vialidad" obligatorio=true ></x-formElement>
            <x-formElement id="idtipo_asentamiento" label="Tipo Asentamiento" obligatorio=true tipo="select" classcampo="select2"  >
                @foreach( $tiposasentamientos as $key => $values )
                    <option value="{{ $values->idtipo_asentamiento }}" >{{ $values->tipo_asentamiento }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="asentamiento" label="Asentamiento" obligatorio=true ></x-formElement>
            <x-formElement id="noexterior" label="No. Exterior" obligatorio=false ></x-formElement>
            <x-formElement id="nointerior" label="No. Interior" obligatorio=false ></x-formElement>
            <x-formElement id="cp" label="C.P." obligatorio=false maxlength="13" tipo="number"></x-formElement>
            <x-formElement id="latitud" label="Latitud" obligatorio=false ></x-formElement>
            <x-formElement id="longitud" label="Longitud" obligatorio=false ></x-formElement>



            <x-formElement id="construccion" label="Fecha Construcción" obligatorio=false tipo="date"></x-formElement>
            <x-formElement id="inicio_operacion" label="Fecha Inicio Operación" obligatorio=false tipo="date"></x-formElement>
            <x-formElement id="horarios" label="Horarios" obligatorio=false tipo="textarea"></x-formElement>
            <x-formElement id="telefono" label="Teléfono de la Unidad" obligatorio=false ></x-formElement>

            <hr>
            <h4>Información del Responsable de la Unidad</h4>
            <x-formElement id="nombre_responsable" label="Nombre del Responsable" obligatorio=false ></x-formElement>
            <x-formElement id="pa_responsable" label="Primer Apellido del Responsable" obligatorio=false ></x-formElement>
            <x-formElement id="sa_responsable" label="Segundo Apellido del Responsable" obligatorio=false ></x-formElement>
            <x-formElement id="idprofesion" label="Profesión del Responsable" obligatorio=false tipo="select" classcampo="select2"  >
                @foreach( $profesiones as $key => $values )
                    <option value="{{ $values->idprofesion }}" >{{ $values->profesion }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="cedula_responsable" label="Cédula del Responsable" obligatorio=false ></x-formElement>
            <x-formElement id="email" label="Email del Responsable" obligatorio=false ></x-formElement>

            <hr>
            <h4>Información Unidad Móvil</h4>
            <x-formElement id="idmarca_um" label="(Unidad Móvil) Marca" obligatorio=false tipo="select" classcampo="select2"  >
                @foreach( $marcasum as $key => $values )
                    <option value="{{ $values->idmarca_um }}" >{{ $values->marca_um }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="marca_esp_um" label="(Unidad Móvil) Marca Específica" obligatorio=false ></x-formElement>
            <x-formElement id="modelo_um" label="(Unidad Móvil) Modelo" obligatorio=false ></x-formElement>
            <x-formElement id="idprograma_um" label="(Unidad Móvil) Programa" obligatorio=false tipo="select" classcampo="select2"  >
                @foreach( $programasum as $key => $values )
                    <option value="{{ $values->idprograma_um }}" >{{ $values->programa_um }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idtipo_um" label="(Unidad Móvil) Tipo" obligatorio=false tipo="select" classcampo="select2"  >
                @foreach( $tiposum as $key => $values )
                    <option value="{{ $values->idtipo_um }}" >{{ $values->tipo_um }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="idtipologia_um" label="(Unidad Móvil) Tipología" obligatorio=false tipo="select" classcampo="select2"  >
                @foreach( $tipologiasum as $key => $values )
                    <option value="{{ $values->idtipologia_um }}" >{{ $values->tipologia_um }}</option>
                @endforeach
            </x-formElement>

            <hr>
            <h4>Información Baja de la Unidad</h4>
            <x-formElement id="idmotivo_baja" label="Motivo de Baja" obligatorio=false tipo="select" classcampo="select2"  >
                @foreach( $motivosbaja as $key => $values )
                    <option value="{{ $values->idmotivo_baja }}" >{{ $values->motivo_baja }}</option>
                @endforeach
            </x-formElement>
            <x-formElement id="fecha_efectiva_baja" label="Fecha Efectiva de Baja" obligatorio=false tipo="date"></x-formElement>
            <hr>
            <x-formElement id="idstatus_unidad" label="Estatus de Operación" obligatorio=true tipo="select" classcampo="select2"  >
                @foreach( $statusunidades as $key => $values )
                    <option value="{{ $values->idstatus_unidad }}" >{{ $values->status_unidad }}</option>
                @endforeach
            </x-formElement>
        </x-modal-form>

        <!-- Modal Para mostrar confirmación de eliminación-->
        <x-modal-aviso id="eliminaModal" accionBtnClass="btn-danger" closeDataModalClass="btn-primary" modaltype="danger" textTitle="¡Atención!" accionBtnTxt="Eliminar" closeDataModalTxt="Cancelar">
            <h6 class="text-danger">¿Estas seguro de eliminar el elemento?</h6>
        </x-modal-aviso>
        <!-- Modal para mostrar errores de validación-->
        <x-modal-aviso id="validaModal" showOk=false closeDataModalClass="btn-danger" modaltype="danger" textTitle="¡Atención!"></x-modal-aviso>
        <!-- Modal para informar confirmación de acciones-->
        <x-modal-aviso id="confirmaModal" showOk=false closeDataModalClass="btn-success" textTitle="Operación realizada correctamente."></x-modal-aviso>
    @endpush
</x-app-layout>
