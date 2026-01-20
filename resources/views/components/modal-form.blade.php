@props(['id'=> 'modalData', 'data_bs_backdrop'=> 'static', 'tabindex'=>-1,
'role'=>'dialog', 'classmodal'=>'modal-normal', 'role2'=>'document',
'modaltype'=>'success',
'classheader'=>'', 'classcontent'=>'', 'classfooter'=>'',
'attrform'=>'', 'textTitle'=>'',
'accionBtnTxt'=>'Guardar', 'accionBtnClass'=>'btn-success',
'closeDataModalTxt'=>'Cancelar', 'closeDataModalClass'=>'btn-secondary',
'showGuardar'=>true, 'showCancelar'=>true,
])

@php
    $showGuardarBtn=true;
    $showCancelarBtn=true;
    if($showGuardar!==true){
        $showGuardarBtn=false;
    }
    if($showCancelar!==true){
        $showCancelarBtn=false;
    }
@endphp

<div class="modal fade" id="{{ $id }}"  data-bs-backdrop="{{ $data_bs_backdrop }}" tabindex="{{ $tabindex }}" role="{{ $role }}" aria-labelledby="DataTitle{{ $id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered {{ $classmodal }}" role="{{ $role2 }}">
        <div class="modal-content b2{{ $modaltype }} {{ $classcontent }}">
            <div class="modal-header {{ $modaltype }} {{ $classheader }}" id="DataTitleDiv{{ $id }}">
                <h5 class="modal-title" id="DataTitle{{ $id }}">{{ $textTitle }}</h5>
            </div>
            <div class="modal-body" id="dataFormDiv{{ $id }}">
                <form action="" method="POST" id="dataForm{{ $id }}" {{ $attrform }}>
                    <fieldset class="row">
                        {{ $slot }}
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer {{ $classfooter }}">
                @if($showGuardarBtn) <button type="button" class="btn {{ $accionBtnClass }}" id="accionBtn{{ $id }}">{{ $accionBtnTxt }}</button> @endif
                @if($showCancelarBtn) <button type="button" class="btn {{ $closeDataModalClass }}" data-bs-dismiss="modal" id="closeDataModal{{ $id }}">{{ $closeDataModalTxt }}</button> @endif
            </div>
        </div>
    </div>
</div>
