@props(['id'=> 'modal', 'data_bs_backdrop'=> 'static', 'tabindex'=>-1,
'modaltype'=>'success',
'classheader'=>'', 'classcontent'=>'', 'classmodal'=>'',
'attrform'=>'', 'textTitle'=>'',
'accionBtnTxt'=>'Aceptar', 'accionBtnClass'=>'btn-success',
'closeDataModalTxt'=>'Cerrar', 'closeDataModalClass'=>'btn-secondary',
'showOk'=>true, 'showCerrar'=>true,
])

@php
    $showGuardarBtn=true;
    $showCancelarBtn=true;
    if($showOk!==true){
        $showGuardarBtn=false;
    }
    if($showCerrar!==true){
        $showCancelarBtn=false;
    }
@endphp
<!-- Modal para notificación avisos de Procesos -->
<div class="modal fade" id="{{ $id }}" data-bs-backdrop="{{ $data_bs_backdrop }}" tabindex="{{ $tabindex }}" aria-labelledby="avisoTitle{{ $id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered {{ $classmodal }}">
    <div class="modal-content b2{{ $modaltype }}">
        <div class="modal-header {{ $modaltype }}">
            <h5 class="modal-title" id="avisoTitle{{ $id }}">{{ $textTitle }}</h5>
        </div>
        <div class="modal-body text-justify" id="avisoModalBody{{ $id }}">
            {{ $slot }}
        </div>
        <div class="modal-footer">
            @if($showGuardarBtn) <button type="button" class="btn {{ $accionBtnClass }}" id="avisoActionBtn{{ $id }}">{{ $accionBtnTxt }}</button> @endif
            @if($showCancelarBtn) <button type="button" class="btn {{ $closeDataModalClass }}" data-bs-dismiss="modal" id="avisoCloseModal{{ $id }}">{{ $closeDataModalTxt }}</button> @endif
        </div>
    </div>
    </div>
</div>
