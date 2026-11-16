<?php

use App\Models\Lease;
use App\Models\Receipt;
use Livewire\Component;

/**
 * Un contrato de alquiler con todos sus recibos, para anotar los cobros y abrir cada recibo
 * para imprimirlo o mandarlo.
 */
new class extends Component
{
    public Lease $lease;

    public function render()
    {
        return $this->view()->title($this->lease->property->title);
    }

    public function markPaid(int $receiptId): void
    {
        $receipt = $this->lease->receipts()->findOrFail($receiptId);
        $receipt->update(['paid_on' => today()]);

        $this->notify('Recibo de '.$receipt->periodLabel().' cobrado.');
    }

    public function undoPayment(int $receiptId): void
    {
        $receipt = $this->lease->receipts()->findOrFail($receiptId);
        $receipt->update(['paid_on' => null]);

        $this->notify('Cobro de '.$receipt->periodLabel().' deshecho.', 'info');
    }
};
?>

@php($money = fn (int $amount) => number_format($amount, 0, ',', '.').' €')

<div>
    <a href="{{ route('leases') }}" wire:navigate class="text-sm text-slate-500 hover:text-teal-700">← Alquileres</a>

    <div class="mt-2 mb-6">
        <h1 class="text-2xl font-semibold">{{ $lease->property->title }}</h1>
        <p class="mt-1 text-slate-600">
            <span class="font-mono text-xs">{{ $lease->property->reference }}</span> ·
            {{ $lease->tenant->name }} ·
            del {{ $lease->starts_on->isoFormat('D [de] MMMM [de] YYYY') }} al {{ $lease->ends_on->isoFormat('D [de] MMMM [de] YYYY') }}
        </p>
        <p class="mt-1 text-sm text-slate-500">
            {{ $money($lease->monthly_rent) }} al mes, que se pagan antes del día {{ $lease->payment_day }} · Fianza de {{ $money($lease->deposit) }}
            @if ($lease->debt() > 0)
                · <span class="font-medium text-red-700">Debe {{ $money($lease->debt()) }}</span>
            @endif
        </p>
    </div>

    <x-wiretable>
        <x-slot:head>
            <x-wiretable.th>Mes</x-wiretable.th>
            <x-wiretable.th shrink align="right">Importe</x-wiretable.th>
            <x-wiretable.th hide="md" shrink>Vence</x-wiretable.th>
            <x-wiretable.th shrink>Estado</x-wiretable.th>
            <x-wiretable.th hide="md" shrink>Cobrado el</x-wiretable.th>
            <x-wiretable.th actions />
        </x-slot:head>

        @forelse ($lease->receipts as $receipt)
            <x-wiretable.row wire:key="receipt-{{ $receipt->id }}">
                <x-wiretable.td>{{ Str::ucfirst($receipt->periodLabel()) }}</x-wiretable.td>
                <x-wiretable.td shrink align="right">{{ $money($receipt->amount) }}</x-wiretable.td>
                <x-wiretable.td hide="md" shrink>{{ $receipt->due_on->format('d/m/Y') }}</x-wiretable.td>
                <x-wiretable.td shrink>
                    <span class="rounded-full px-2 py-0.5 text-xs {{ $receipt->status()->badge() }}">{{ $receipt->status()->label() }}</span>
                </x-wiretable.td>
                <x-wiretable.td hide="md" shrink>{{ $receipt->paid_on?->format('d/m/Y') }}</x-wiretable.td>
                <x-wiretable.td actions>
                    <x-wiretable.menu :label="'Acciones del recibo de '.$receipt->periodLabel()">
                        @if ($receipt->paid_on)
                            <x-wiretable.menu-item :href="route('receipt', $receipt)" target="_blank">Ver recibo</x-wiretable.menu-item>
                            <x-wiretable.menu-separator />
                            <x-wiretable.menu-item wire:click="undoPayment({{ $receipt->id }})">Deshacer el cobro</x-wiretable.menu-item>
                        @else
                            <x-wiretable.menu-item wire:click="markPaid({{ $receipt->id }})">Marcar como cobrado</x-wiretable.menu-item>
                        @endif
                    </x-wiretable.menu>
                </x-wiretable.td>
            </x-wiretable.row>
        @empty
            <x-wiretable.empty colspan="6">Este contrato todavía no tiene recibos.</x-wiretable.empty>
        @endforelse
    </x-wiretable>
</div>
