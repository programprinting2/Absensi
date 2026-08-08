<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Informasi Profil
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Informasi akun Anda. Nama dan email tidak dapat diubah di sini.
        </p>
    </header>

    <div class="mt-6 space-y-6">
        <div>
            <x-input-label for="name" value="Nama" />
            <x-text-input
                id="name"
                name="name"
                type="text"
                class="mt-1 block w-full bg-gray-50 text-gray-600"
                value="{{ $name }}"
                disabled
            />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input
                id="email"
                name="email"
                type="email"
                class="mt-1 block w-full bg-gray-50 text-gray-600"
                value="{{ $email }}"
                disabled
            />
        </div>
    </div>
</section>
