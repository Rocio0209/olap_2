<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            @if (env('VEDA_ELECTORAL'))
            @else
                <img class="d-none d-md-block bg-logo" src="{{ env('CDNIMB') }}img/LogoIMSSBBlanco.png" style="width:500px;"/>
                <img class="d-block d-md-none bg-logo" src="{{ env('CDNIMB') }}img/LogoIMSSBBlanco.png" style="width:20%;"/>
            @endif
            <br class="mb-3">
            <h5 class="text-center color-institucional3">DEPARTAMENTO DE TECNOLOGÍAS DE LA INFORMACIÓN</h5>
            <br>
            <h3 class="text-center color-institucional3">{{ config('app.name', 'Plantilla IMSS Bienestar') }}</h4>
            <br>
        </x-slot>

        <x-slot name="titulo">
            <div class="bg-fondo-institucional" style="border-radius: 10px 10px 0 0"><h4 class="text-black my-0 px-3 font-institucional-b mt-2" >Restablecer Contraseña</h4></div>
        </x-slot>

        <div class="mb-4 text-sm text-black">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>

        @if (session('status'))
            <div class="mb-4 font-medium text-md text-green-600">
                {{ session('status') }}
            </div>
        @endif

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="block">
                <x-label-black for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-button>
                    <div class="text-black">{{ __('Email Password Reset Link') }}</div>
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
