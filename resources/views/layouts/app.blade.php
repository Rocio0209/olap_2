<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex nofollow">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.scss', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles

        @stack('styles')
    </head>
    <body class="font-sans antialiased {{ env('VEDA_ELECTORAL') ? 'veda-electoral' : ''}}">
        <x-banner />

        <div class="min-h-screen bg-fondo-usuario">
            <header class="text-white pt-3 pb-1 institucional bg-institucionalbarra borde-inferior-institucional4">
                <div class="container d-flex flex-row align-items-center">
                    <div class="bg-institucional4 mr-2 mb-2 rounded-3"><i class="fa-solid fa-book m-2"></i></div>
                    <div class="col-6 col-lg-auto me-lg-auto justify-content-center mb-md-0 d-none d-sm-none d-md-block py-1">
                        <h2 style="font-size: 18px; " class="mb-0 text-white">{{ config('app.name') }} </h2>
                    </div>
                    <div class="col-10 col-lg-auto me-lg-auto justify-content-center mb-md-0 d-block d-sm-block d-md-none py-1">
                        <h2 style="font-size: 16px; " class="mb-0 text-white">{{ config('app.name') }} </h2>
                    </div>
                </div>
            </header>

            @livewire('navigation-menu')

            <!-- Page Heading -->
            @if (isset($header))
                <header class="margen-navbar-lateral">
                    <div class="container headerpagina">
                        <div class="py-2 d-flex flex-row align-items-center">
                            <span class="d-inline rounded-2 bg-institucional4 pt-2 pb-3 px-1 mr-1"></span>
                            {{ $header }}
                        </div>
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="z-0 margen-navbar-lateral">
                {{ $slot }}
            </main>
        </div>

        @stack('modals')

        @livewireScripts(['nonce' => csp_nonce()])

        <script type="module" nonce="{{ csp_nonce() }}">
            $(document).ready(function(){
                //En caso de que la página tenga un scroll en Y entonces el Sidebar se tiene posicionar de modo
                //que no quede un espacio vacio en el header
                window.onscroll = function() {
                    if ($(document).width() > 992) { //El posicionamiento del sidebar depende del ancho de la pantalla
                        let altura = $(document).scrollTop();
                        let alturaBanner = parseInt($('header.institucional').outerHeight());
                        altura = altura > alturaBanner ? 0 : alturaBanner - altura;
                        $('#navbar-lateral').addClass('sin-transicion').css('top', altura);
                        setTimeout(function(){ //Quitando la clase de transicion despues de 200 ms para evitar que se ejecute 
                            $('#navbar-lateral').removeClass('sin-transicion');
                        }, 200);
                        $('.dropdown-menu.show').css('top', altura);
                    } else {
                        $('#navbar-lateral').css('top', '');
                        $('.dropdown-menu.show').css('top', '');
                    }
                }
                $('#navbar-lateral').on('shown.bs.dropdown', function(){ //Cuando se muestre el menu lateral desplegable se recalcula la posición 
                    if ($(document).width() > 992) { //El posicionamiento del sidebar depende del ancho de la pantalla
                        let altura = $(document).scrollTop();
                        let alturaBanner = parseInt($('header.institucional').outerHeight());
                        altura = altura > alturaBanner ? 0 : alturaBanner - altura;
                        $('.dropdown-menu.show').css('top', altura);
                    } else {
                        $('.dropdown-menu.show').css('top', '');   
                    }
                    //$(this).find('.dropdown-menu').first().stop(true, true).slideDown();
                });
                $('#navbar-lateral').on('mouseleave', function(){ //Cerrando el menu desplegable en caso
                    if ($(document).width() > 992) { //El posicionamiento del sidebar depende del ancho de la pantalla
                        const elementos = document.querySelectorAll('.nav-link.dropdown-toggle.show');
                        if (elementos.length > 0) {
                            $('.nav-link.dropdown-toggle.show').blur(); //Se deja de posicionar el boton del dropdown
                            const toggle = new bootstrap.Dropdown(elementos[0]); //Se obtiene elemento deldropdown y se cierra
                            toggle.toggle();
                        } 
                    }
                });
            });
        </script>
        @stack('scripts')
    </body>
</html>
