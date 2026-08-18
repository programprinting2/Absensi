<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $username = '';

    public string $email = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->username = (string) $user->username;
        $this->email = (string) $user->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:64',
                'alpha_dash',
                Rule::unique(User::class, 'username')->ignore($user->id),
            ],
        ]);

        $user->fill($validated);
        $user->save();

        $this->dispatch('profile-updated', username: $user->username);
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Informasi Profil
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Username dipakai untuk login dan tampilan di layar absensi device.
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div>
            <x-input-label for="username" value="Username" />
            <x-text-input
                wire:model="username"
                id="username"
                name="username"
                type="text"
                class="mt-1 block w-full"
                required
                autocomplete="username"
            />
            <x-input-error class="mt-2" :messages="$errors->get('username')" />
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

        <div class="flex items-center gap-4">
            <x-primary-button type="submit">Simpan</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                Tersimpan.
            </x-action-message>
        </div>
    </form>
</section>
