<?php

use App\Enums\Operation;
use App\Livewire\Forms\LeaseForm;
use App\Models\Contact;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Receipt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Los contratos de alquiler de la agencia, con el estado del recibo de este mes y lo que
 * debe cada inquilino, y el alta de un contrato nuevo.
 */
new #[Title('Alquileres')] class extends Component
{
    public LeaseForm $form;

    #[Computed]
    public function leases()
    {
        return Lease::with(['property', 'tenant', 'receipts'])->orderBy('ends_on')->get();
    }

    /** Lo emitido, lo cobrado y lo pendiente del mes en curso, y el total de impagados. */
    #[Computed]
    public function summary(): array
    {
        $month = Receipt::whereBetween('period', [today()->startOfMonth(), today()->endOfMonth()])->get();

        return [
            'issued' => $month->sum('amount'),
            'paid' => $month->whereNotNull('paid_on')->sum('amount'),
            'overdue' => Receipt::overdue()->sum('amount'),
            'overdue_count' => Receipt::overdue()->count(),
        ];
    }

    /** Los inmuebles en alquiler que siguen libres. */
    #[Computed]
    public function properties()
    {
        return Property::where('operation', Operation::Rent)->where('status', 'available')->orderBy('reference')->get();
    }

    #[Computed]
    public function contacts()
    {
        return Contact::orderBy('name')->get();
    }

    public function updated(string $name): void
    {
        if ($name === 'form.property_id') {
            $this->form->fillFromProperty();
        }
    }

    public function save(): void
    {
        $lease = $this->form->store();

        $this->closeModal('new-lease');
        $this->notify("Contrato de {$lease->property->reference} con {$lease->tenant->name} guardado.");
    }
};
?>

@php($money = fn (int $amount) => number_format($amount, 0, ',', '.').' €')

<div>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <h1 class="mr-auto text-2xl font-semibold">Alquileres</h1>
        <button type="button" x-on:click="Wiremodal.open('new-lease')" class="rounded bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800">
            Nuevo contrato
        </button>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Recibos de {{ today()->isoFormat('MMMM') }}</p>
            <p class="mt-1 text-2xl font-semibold">{{ $money($this->summary['issued']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Cobrado este mes</p>
            <p class="mt-1 text-2xl font-semibold text-teal-700">{{ $money($this->summary['paid']) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Impagado ({{ $this->summary['overdue_count'] }} {{ $this->summary['overdue_count'] === 1 ? 'recibo' : 'recibos' }})</p>
            <p @class(['mt-1 text-2xl font-semibold', 'text-red-700' => $this->summary['overdue'] > 0])>{{ $money($this->summary['overdue']) }}</p>
        </div>
    </div>

    <x-wiretable>
        <x-slot:head>
            <x-wiretable.th>Inmueble</x-wiretable.th>
            <x-wiretable.th shrink align="right">Renta</x-wiretable.th>
            <x-wiretable.th hide="lg" shrink>Hasta</x-wiretable.th>
            <x-wiretable.th hide="md" shrink>Este mes</x-wiretable.th>
            <x-wiretable.th shrink align="right">Debe</x-wiretable.th>
        </x-slot:head>

        @forelse ($this->leases as $lease)
            @php($current = $lease->receipts->first(fn ($receipt) => $receipt->period->isSameMonth(today())))
            <x-wiretable.row :href="route('lease', $lease)" navigate wire:key="lease-{{ $lease->id }}">
                <x-wiretable.td>
                    <x-wiretable.primary :title="$lease->property->title" :subtitle="$lease->property->reference.' · '.$lease->tenant->name" />
                </x-wiretable.td>
                <x-wiretable.td shrink align="right">{{ $money($lease->monthly_rent) }}</x-wiretable.td>
                <x-wiretable.td hide="lg" shrink>{{ $lease->ends_on->format('d/m/Y') }}</x-wiretable.td>
                <x-wiretable.td hide="md" shrink>
                    @if ($current)
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $current->status()->badge() }}">{{ $current->status()->label() }}</span>
                    @else
                        <span class="text-xs text-slate-400">Sin recibo</span>
                    @endif
                </x-wiretable.td>
                <x-wiretable.td shrink align="right">
                    <span @class(['font-medium text-red-700' => $lease->debt() > 0, 'text-slate-400' => $lease->debt() === 0])>{{ $money($lease->debt()) }}</span>
                </x-wiretable.td>
            </x-wiretable.row>
        @empty
            <x-wiretable.empty colspan="5">Todavía no hay contratos de alquiler.</x-wiretable.empty>
        @endforelse
    </x-wiretable>

    <x-wiremodal name="new-lease" title="Nuevo contrato de alquiler" size="2xl" as="form" wire:submit="save">
        <x-slot:body>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm">Inmueble
                    <select wire:model.live="form.property_id" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                        <option value="">Elige uno</option>
                        @foreach ($this->properties as $property)
                            <option value="{{ $property->id }}">{{ $property->reference }} · {{ $property->title }}</option>
                        @endforeach
                    </select>
                    @error('form.property_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="text-sm">Inquilino
                    <select wire:model="form.contact_id" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                        <option value="">Elige uno</option>
                        @foreach ($this->contacts as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                        @endforeach
                    </select>
                    @error('form.contact_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <div class="text-sm sm:col-span-2">
                    <span>Duración del contrato</span>
                    <x-wirepicker range wire:model="form.period" :presets="['next-month', 'this-year']" trigger-class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
                    @error('form.period.start') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    @error('form.period.end') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <label class="text-sm">Renta mensual (€)
                    <input type="number" wire:model="form.monthly_rent" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                    @error('form.monthly_rent') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="text-sm">Fianza (€)
                    <input type="number" wire:model="form.deposit" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                    @error('form.deposit') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="text-sm">Día de pago
                    <input type="number" min="1" max="28" wire:model="form.payment_day" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                    @error('form.payment_day') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" data-wm-dismiss class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancelar</button>
            <button class="rounded bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Guardar contrato</button>
        </x-slot:footer>
    </x-wiremodal>
</div>
