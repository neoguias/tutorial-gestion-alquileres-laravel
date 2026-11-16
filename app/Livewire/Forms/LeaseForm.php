<?php

namespace App\Livewire\Forms;

use App\Enums\Operation;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Form;

class LeaseForm extends Form
{
    public ?int $property_id = null;

    public ?int $contact_id = null;

    /** El inicio y el final del contrato, tal como los devuelve el calendario de rango. */
    public array $period = ['start' => '', 'end' => ''];

    public ?int $monthly_rent = null;

    public ?int $deposit = null;

    // Si no se pacta otra cosa, la renta se paga en los siete primeros días de cada mes.
    public int $payment_day = 7;

    /** Al elegir el inmueble propone su renta y la fianza que marca la ley. */
    public function fillFromProperty(): void
    {
        $property = Property::find($this->property_id);

        if ($property) {
            $this->monthly_rent = $property->price;
            $this->deposit = Lease::legalDeposit($property, $property->price);
        }
    }

    protected function rules(): array
    {
        return [
            'property_id' => ['required', Rule::exists('properties', 'id')->where('operation', Operation::Rent->value)],
            'contact_id' => ['required', Rule::exists('contacts', 'id')],
            'period.start' => ['required', 'date'],
            'period.end' => ['required', 'date', 'after:period.start'],
            'monthly_rent' => ['required', 'integer', 'min:1'],
            'deposit' => ['required', 'integer', 'min:0'],
            'payment_day' => ['required', 'integer', 'between:1,28'],
        ];
    }

    protected function messages(): array
    {
        return [
            'period.start.required' => 'Elige las fechas del contrato.',
            'period.end.required' => 'Elige también el día en que termina.',
        ];
    }

    /** Guarda el contrato, emite el recibo del mes en curso y saca el inmueble de la cartera. */
    public function store(): Lease
    {
        $data = $this->validate();

        $lease = DB::transaction(function () use ($data) {
            $lease = Lease::create([
                'property_id' => $data['property_id'],
                'contact_id' => $data['contact_id'],
                'starts_on' => $data['period']['start'],
                'ends_on' => $data['period']['end'],
                'monthly_rent' => $data['monthly_rent'],
                'deposit' => $data['deposit'],
                'payment_day' => $data['payment_day'],
            ]);

            $lease->property->update(['status' => PropertyStatus::Closed]);

            // El recibo de este mes no espera al día 1 del que viene.
            if ($lease->isActiveIn(today())) {
                $lease->issueReceipt(today());
            }

            return $lease;
        });

        $this->reset();

        return $lease;
    }
}
