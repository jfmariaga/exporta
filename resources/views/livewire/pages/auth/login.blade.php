<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="text-xl font-semibold text-ink-900 dark:text-gray-100">Iniciar sesión</h1>
    <p class="mt-1 text-sm text-ink-secondary dark:text-gray-400">Ingresa con tu correo y contraseña.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form wire:submit="login" class="mt-6">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-password-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me + Forgot password -->
        <div class="mt-4 flex items-center justify-between">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-line dark:border-white/10 text-brand-600 shadow-sm focus:ring-brand-500" name="remember">
                <span class="ms-2 text-sm text-ink-900 dark:text-gray-300">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline dark:text-brand-300 dark:hover:text-brand-200 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:focus:ring-offset-surface-dark" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="mt-4 w-full justify-center">
            {{ __('Log in') }}
        </x-primary-button>
    </form>
</div>
