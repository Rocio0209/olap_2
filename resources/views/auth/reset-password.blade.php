<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-slot name="logo">
                @if (env('VEDA_ELECTORAL'))
                    <img src="{{ env('CDNIMB') }}img/LogoIMSSB.svg" style="width:80px;"/>
                @else
                    <img class="d-none d-md-block bg-logo" style="width:500px;"/>
                    <img class="d-block d-md-none bg-logo" style="width:400px;"/>
                @endif
                <br>
                <h4 class="text-center color-institucional3 invisible">_</h4>
                <h5 class="text-center color-institucional3">DEPARTAMENTO DE TECNOLOGÍAS DE LA INFORMACIÓN</h5>
                <br>
                <h3 class="text-center color-institucional3">{{ config('app.name', 'Plantilla IMSS Bienestar') }}</h4>
                <br>
            </x-slot>
        </x-slot>

        <x-slot name="titulo">
            <div class="bg-institucional2 " style="border-radius: 10px 10px 0 0"><h4 class="text-white color-institucional1 my-0 px-3 font-institucional-b mt-2" >Restablecer Contraseña</h4></div>
        </x-slot>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="block">
                <x-label-white for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            </div>

            <div class="mt-4">
                <x-label-white for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-label-white for="password_confirmation" value="{{ __('Confirm Password') }}" />
                <x-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-button>
                    {{ __('Reset Password') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
