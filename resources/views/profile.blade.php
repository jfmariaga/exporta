<x-app-layout>
    <x-ui.page title="Perfil">
        <div class="max-w-xl space-y-5">
            <div class="card card-pad">
                <livewire:profile.update-profile-information-form />
            </div>

            <div class="card card-pad">
                <livewire:profile.update-password-form />
            </div>

            <div class="card card-pad">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </x-ui.page>
</x-app-layout>
