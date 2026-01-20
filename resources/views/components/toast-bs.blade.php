@props(['id'=> 'toastData', 'title'=>'¡Atención!',])
{{-- Toast para notificar guardados --}}
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="{{ $id }}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header text-success" >
            @if (isset($icono))
                {{ $icono }}
            @endif
            <strong class="me-auto" style="font-size:18px; " id="titleToast{{ $id }}"> {{ $title }} </strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            {{ $slot }}
        </div>
    </div>
</div>
