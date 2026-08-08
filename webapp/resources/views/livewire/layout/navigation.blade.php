<?php

use App\Livewire\Actions\Logout;
use App\Support\MenuRegistry;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/');
    }
}; ?>

@php
    $homeRoute = auth()->user()?->homeRouteName() ?? 'login';
    $sidebarMenus = MenuRegistry::sidebarForUser(auth()->user());
@endphp

{{-- Full page links (tanpa wire:navigate): hindari race SPA + wire:poll / halaman Blade biasa --}}
<div x-data="{ open: false }" x-on:toggle-sidebar.window="open = !open">
    <!-- Overlay -->
    <div x-show="open" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="open = false" class="fixed inset-0 z-40 bg-gray-600/50 lg:hidden" x-cloak></div>

    <!-- Sidebar -->
    <aside :class="open ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transform transition-transform duration-300 ease-in-out lg:translate-x-0 flex flex-col">
        <!-- Logo -->
        <div class="flex items-center h-16 px-6 border-b border-gray-200 shrink-0">
            <a href="{{ route($homeRoute) }}" class="flex items-center" @click="open = false">
                <x-application-logo class="h-9 w-auto" />
            </a>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            @foreach ($sidebarMenus as $menu)
                @php
                    $patterns = $menu['patterns'] ?? [$menu['route']];
                    $isActive = collect($patterns)->contains(fn ($pattern) => request()->routeIs($pattern));
                    $iconPath = MenuRegistry::iconPath($menu['icon'] ?? 'home');
                @endphp
                <x-sidebar-link :href="route($menu['route'])" :active="$isActive" @click="open = false">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}" />
                    </svg>
                    {{ __($menu['label']) }}
                </x-sidebar-link>
            @endforeach
        </nav>

        <!-- User Section -->
        <div class="border-t border-gray-200 p-3">
            <x-dropdown align="bottom-up" width="48">
                <x-slot name="trigger">
                    <button class="flex items-center w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-600 text-sm font-semibold shrink-0">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <div class="ml-3 text-left min-w-0">
                            <div class="truncate font-medium" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                            <div class="truncate text-xs text-gray-500">{{ auth()->user()->email }}</div>
                        </div>
                        <svg class="ml-auto h-4 w-4 text-gray-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile')">
                        {{ __('Profil') }}
                    </x-dropdown-link>

                    <button wire:click="logout" class="w-full text-start">
                        <x-dropdown-link>
                            {{ __('Log Out') }}
                        </x-dropdown-link>
                    </button>
                </x-slot>
            </x-dropdown>
        </div>
    </aside>
</div>
