<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-0">
            Usuarios
        </h2>
    </x-slot>

    <div class="container">
        <div class="card">
            <div class="card-body">
                {{ $dataTable->table() }}
                @can('add_usuarios')
                    <button id="agregar" type="button" class="btn btn-success"  data-bs-toggle="modal" data-bs-target="#modalData">Agregar</button>
                @endcan
            </div>
        </div>
        <x-precarga></x-precarga>
    </div>
    @push('scripts')
        {{ $dataTable->scripts(attributes: ['type' => 'module', 'nonce' => csp_nonce()]) }}
        <script type="module" nonce="{{ csp_nonce() }}">
            $(document).ready(function(){
                //Variables Globales
                const elemento='Usuario';
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
                            url: "{{ route('usuarios-api.store') }}",
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
                        url: "{{ route('usuarios-api.store') }}"+"/"+ids.id1,
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

                    $.get("{{ route('usuarios-api.index') }}" +'/' + ids.id1 +'/edit', function (data) {
                        $('#DataTitle'+idform).text('Modificar '+elemento);
                        const modalToggle = document.getElementById('modalData');
                        modalData.show(modalToggle);

                        $('#name').val(data.name);
                        $('#email').val(data.email);
                        $('#username').val(data.username);
                        $('#idstatus_user').val(data.idstatus_user);
                        $('#password').removeClass('required').attr('placeholder','Sin modificaciones');

                        if(data.roles[0]!==undefined){
                            $('#idrole').val(data.roles[0].id);
                        }

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
                // $('body').on('change','select',function(){
                //     var id=$(this).attr('id');
                //     var value=$(this).val();

                //     switch(id){
                //         case '':
                //             break;
                //     }
                // });

                window.inicializaSelect2({parent:'modalData'});

            });

        </script>
    @endpush

    @push('modals')
        <!-- Modal Form para Agregar y Modificar Información-->
        <x-modal-form >
            @csrf
            <x-formElement ancholabel="4" anchocampo="8" id="name" label="Nombre" required=true ></x-formElement>
            <x-formElement ancholabel="4" anchocampo="8" id="email" label="Correo Electrónico" required=true ></x-formElement>
            <x-formElement ancholabel="4" anchocampo="8" id="username" label="Usuario" required=true ></x-formElement>
            <x-formElement ancholabel="4" anchocampo="8" id="password" label="Password" required=true tipo="password"></x-formElement>
            <x-formElement ancholabel="4" anchocampo="8" id="idrole" label="Rol" required=true tipo="select" classcampo="select2">
                @foreach( $roles as $key => $values )
                    <option value="{{ $values->id }}" >{{ $values->name }}</option>
                @endforeach
            </x-formElement>
            <x-formElement ancholabel="4" anchocampo="8" id="idstatus_user" label="Estatus del Usuario" required=true tipo="select" classcampo="select2">
                <option value="1" >Activo</option>
                <option value="0" >Inactivo</option>
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
