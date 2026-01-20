@props(['id'=> 'modalDataTable', 'idTitle'=> 'DataTableTitle',  'data_bs_backdrop'=> 'static', 'tabindex'=>-1,
'role'=>'dialog', 'classmodal'=>'modal-normal', 'role2'=>'document',
'classheader'=>'success', 'classcontent'=>'b2success',
])
<div class="modal fade" id="{{ $id }}" data-bs-backdrop="{{ $data_bs_backdrop }}" tabindex="{{ $tabindex }}" role="{{ $role }}" aria-labelledby="DataTableTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered {{ $classmodal }}" role="{{ $role2 }}">
        <div class="modal-content {{ $classcontent }}">
            <div class="modal-header {{ $classheader }}">
                <h5 class="modal-title" id="{{ $idTitle }}"></h5>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="close{{ $id }}">Cerrar</button>
            </div>
        </div>
    </div>
</div>
