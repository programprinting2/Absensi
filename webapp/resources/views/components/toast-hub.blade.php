@php
    $initialToasts = [];
    if (session()->has('status')) {
        $initialToasts[] = ['type' => 'success', 'message' => session('status')];
    }
    if (session()->has('error')) {
        $initialToasts[] = ['type' => 'error', 'message' => session('error')];
    }
    if (session()->has('toast') && is_array(session('toast'))) {
        $initialToasts[] = session('toast');
    }
@endphp

<div
    x-data="toastHub(@js($initialToasts))"
    @app-toast.window="push($event.detail)"
    class="pointer-events-none fixed top-4 right-4 z-[200] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2"
    aria-live="polite"
    aria-relevant="additions"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-x-4"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-4"
            class="pointer-events-auto flex items-start gap-3 rounded-lg border px-4 py-3 shadow-lg bg-white"
            :class="toast.type === 'error'
                ? 'border-red-200 text-red-800'
                : 'border-green-200 text-green-800'"
            role="status"
        >
            <div class="mt-0.5 shrink-0" :class="toast.type === 'error' ? 'text-red-500' : 'text-green-600'">
                <template x-if="toast.type === 'error'">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </template>
                <template x-if="toast.type !== 'error'">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </template>
            </div>
            <p class="flex-1 text-sm font-medium leading-snug" x-text="toast.message"></p>
            <button
                type="button"
                class="shrink-0 rounded p-0.5 text-current/60 hover:bg-black/5 hover:text-current"
                @click="dismiss(toast.id)"
                aria-label="Tutup"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>
        </div>
    </template>
</div>
