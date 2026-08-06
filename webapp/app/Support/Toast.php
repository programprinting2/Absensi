<?php

namespace App\Support;

class Toast
{
    public static function success(string $message, object|null $livewire = null): void
    {
        self::send('success', $message, $livewire);
    }

    public static function error(string $message, object|null $livewire = null): void
    {
        self::send('error', $message, $livewire);
    }

    private static function send(string $type, string $message, object|null $livewire): void
    {
        session()->flash('toast', [
            'type' => $type,
            'message' => $message,
        ]);

        if ($type === 'success') {
            session()->flash('status', $message);
        } else {
            session()->flash('error', $message);
        }

        if ($livewire && method_exists($livewire, 'dispatch')) {
            $livewire->dispatch('app-toast', type: $type, message: $message);
        }
    }
}
