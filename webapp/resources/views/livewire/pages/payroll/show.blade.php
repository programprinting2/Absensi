<?php

use App\Models\PayrollPeriod;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public PayrollPeriod $period;

    public function mount(PayrollPeriod $period): void
    {
        $this->period = $period;
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('payroll.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $period->label }}</h2>
                <p class="text-sm text-gray-500">{{ $period->period_start->format('d/m/Y') }} — {{ $period->period_end->format('d/m/Y') }}</p>
            </div>
            <div>
                @if ($period->isDraft())
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-700">Draft</span>
                @elseif ($period->isReview())
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-700">Review</span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">Final</span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 min-h-0 overflow-auto px-4 sm:px-6 lg:px-8 py-4">
            <livewire:payroll.period-entries :period="$period" :key="'period-entries-'.$period->id" />
        </div>
    </div>

    @include('payroll.partials.print-slip-dialog')
</div>
