<div
    x-data="dialogHub()"
    x-cloak
    class="relative z-[210]"
    aria-live="assertive"
>
    <div
        x-show="open"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-900/50"
        @click="cancel()"
    ></div>

    <div
        x-show="open"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
        class="fixed inset-0 z-[211] flex items-end sm:items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="if (open) cancel()"
    >
        <div
            class="w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl border border-gray-100"
            @click.stop
        >
            <div class="px-5 pt-5 pb-3">
                <div class="flex items-start gap-3">
                    <div
                        class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                        :class="danger ? 'bg-red-50 text-red-600' : (mode === 'alert' ? 'bg-sky-50 text-sky-600' : 'bg-amber-50 text-amber-700')"
                    >
                        <template x-if="danger">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </template>
                        <template x-if="!danger && mode === 'alert'">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                            </svg>
                        </template>
                        <template x-if="!danger && mode !== 'alert'">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-gray-900" x-text="title"></h3>
                        <p class="mt-1 text-sm text-gray-600 whitespace-pre-line" x-text="message"></p>
                        <template x-if="mode === 'prompt'">
                            <input
                                x-ref="promptInput"
                                type="text"
                                x-model="promptValue"
                                @keydown.enter.prevent="accept()"
                                class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 bg-gray-50 px-5 py-3 border-t border-gray-100">
                <template x-if="mode !== 'alert'">
                    <button
                        type="button"
                        class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50"
                        @click="cancel()"
                        x-text="cancelLabel"
                    ></button>
                </template>
                <button
                    type="button"
                    x-ref="confirmBtn"
                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold text-white border border-transparent"
                    :class="danger ? 'bg-red-600 hover:bg-red-500' : 'bg-gray-800 hover:bg-gray-700'"
                    @click="accept()"
                    x-text="confirmLabel"
                ></button>
            </div>
        </div>
    </div>
</div>
