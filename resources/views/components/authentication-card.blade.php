<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-institucionalescudo">
    <div class="d-flex flex-column align-items-center">
        {{ $logo }}
    </div>

    @isset($titulo)
        <div class="d-flex justify-content-start w-full sm:max-w-xl">
            {{ $titulo }}
        </div>
    @endisset
    <div class="bg-fondo-institucional w-full sm:max-w-xl px-6 py-4 pb-1 shadow-md overflow-hidden {{ isset($titulo) ? 'rounded-end-3 rounded-bottom-3' : '' }}">
        {{ $slot }}
    </div>
</div>
