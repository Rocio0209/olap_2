<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight m-0">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            @if (auth()->user()->changePass==0)
                <div class="alert alert-danger text-danger">
                    <b>Por seguridad cambie la contraseña temporal en la sección "Actualizar contraseña".</b>
                </div>
            @endif
            
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @can('ver_perfil')
                    <i id="tutorial_password" role="button" class="fa-solid fa-circle-info fa-2x color-institucional4 {{ Auth::user()->changePass == 0 ? 'fa-beat' : '' }}"></i>
                    @livewire('profile.update-profile-information-form')

                    <x-section-border />
                @endcan
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                @can('updt_password')
                    <div class="mt-10 sm:mt-0">
                        @livewire('profile.update-password-form')
                    </div>

                    <x-section-border />
                @endcan
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                @can('2factor_auth')
                    <div class="mt-10 sm:mt-0">
                        @livewire('profile.two-factor-authentication-form')
                    </div>

                    <x-section-border />
                @endcan
            @endif

            @can('cerrar_sesiones')
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.logout-other-browser-sessions-form')
                </div>
            @endcan

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <x-section-border />

                <div class="mt-10 sm:mt-0">
                    @livewire('profile.delete-user-form')
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script type="module" nonce="{{ csp_nonce() }}">
            $(document).ready(function(){
                $('#tutorial_password').on('click', function(e){
                    const tutorialPassword = window.inicializaDriverJS({
                        showProgress: true,
                        steps: [
                            { element: '#email', popover: { title: 'Agrega un correo.', description: 'Captura tú correo electronico para mantener comunicación contigo.', side: 'left', align: 'start' }},
                            { element: '#btnProfile', popover: { title: 'Ventajas del correo.', description: 'Si olvidas tú contraseña puedes recuperarla desde {{ route("password.request") }}.', side: 'top', align: 'start' }},
                            { popover: { title: 'Busca en tú correo.', description: 'Te llegará un correo con un enlace para restablecer tú contraseña. ¡No olvides checar tu bandeja de spam/correo no deseado!' }},
                            { popover: { title: '¡No lo olvides!', description: '¡Captura tú información y no pierdas acceso al sistema!' }},
                        ]
                    });

                    tutorialPassword.drive();
                });

                setTimeout(() => {
                    $('#tutorial_password').removeClass('fa-beat');
                }, '5000');
            });
        </script>
    @endpush
</x-app-layout>
