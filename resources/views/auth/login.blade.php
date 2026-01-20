<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            @if (env('VEDA_ELECTORAL'))
            @else
                <img src="{{ env('CDNIMB') }}img/LogoIMSSBBlanco.png" class="d-none d-md-block bg-logo position-absolute top-0 start-0 mt-6 ml-6" style="width:250px;"/>
                <img src="{{ env('CDNIMB') }}img/Membrete.png" class="d-none d-md-block bg-logo position-absolute bottom-0 end-0 mb-6 mr-6" style="width:150px;"/>
                <div class="d-flex d-md-none justify-content-center align-items-center linea-login position-fixed left-0 right-0 z-1">
                    <img src="{{ env('CDNIMB') }}img/LogoIMSSBBlanco.png" class="bg-logo-movil" style="width:250px;"/>
                </div>
            @endif
            <div class="linea-login-2">
                <h3 class="text-center text-white txt-shadow">DEPARTAMENTO DE TECNOLOGÍAS DE LA INFORMACIÓN</h3>
                <h1 class="text-center text-white txt-shadow">{{ config('app.name', 'Plantilla IMSS Bienestar') }}</h1>
            </div>
            
        </x-slot>

        <x-slot name="titulo">
            <div class="bg-fondo-institucional" style="border-radius: 10px 10px 0 0"><h2 class="text-black my-0 px-3 font-institucional-b mt-2" >Iniciar Sesión</h2></div>
        </x-slot>

        <x-validation-errors class="mb-4" />

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <x-label-black for="username" value="{{ __('Usuario') }}" />
                <div class="col-auto">
                    <div class="input-group">
                        <div class="input-group-text"><i class="fa-solid fa-user"></i></div>
                        <input type="text" class="form-control bg-institucional4-500" id="username" name="username" :value="old('username')"  required autofocus autocomplete="username" placeholder="">
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <x-label-black for="password" value="{{ __('Password') }}" />
                <div class="col-auto">
                    <div class="input-group">
                        <div class="input-group-text"><i class="fa-solid fa-key"></i></div>
                        <input type="password" class="form-control bg-institucional4-500" id="password" name="password" required autocomplete="current-password" placeholder="">
                    </div>
                </div>
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ml-2 text-sm text-black">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-between mt-4">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-black hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                        <b>{{ __('Forgot your password?') }}</b>
                    </a>
                @endif

                <x-button class="ml-4" >

                    <div class="text-black">Entrar</div> <i class="fa-solid fa-right-to-bracket text-dark fa-2x "></i>
                </x-button>
            </div>
            <div class="mt-4">
                <p class="text-center font-institucional-re text-black" style="font-size:10px;">Sus datos personales (nombre y CURP) serán tratados de manera confidencial y utilizados únicamente para validar su identidad como parte del personal de la institución, conforme a la Ley General de Protección de Datos Personales en Posesión de Sujetos Obligados. Consulte el <span class="font-institucional-b">Aviso de Privacidad Integral.</span></p>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
