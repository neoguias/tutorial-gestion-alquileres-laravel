<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lease extends Model
{
    protected $fillable = ['property_id', 'contact_id', 'starts_on', 'ends_on', 'monthly_rent', 'deposit', 'payment_day'];

    // El mismo valor por defecto que la tabla, para que un contrato recién creado lo tenga
    // ya en memoria.
    protected $attributes = [
        'payment_day' => 7,
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'monthly_rent' => 'integer',
            'deposit' => 'integer',
            'payment_day' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** El inquilino es uno de los clientes del CRM. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class)->orderByDesc('period');
    }

    /** Los contratos que están en vigor algún día de ese mes. */
    #[Scope]
    protected function activeIn(Builder $query, CarbonInterface $month): void
    {
        $query->where('starts_on', '<=', $month->copy()->endOfMonth())
            ->where('ends_on', '>=', $month->copy()->startOfMonth());
    }

    public function isActiveIn(CarbonInterface $month): bool
    {
        return $this->starts_on->lte($month->copy()->endOfMonth())
            && $this->ends_on->gte($month->copy()->startOfMonth());
    }

    /**
     * La fianza que exige la Ley de Arrendamientos Urbanos: una mensualidad si se alquila
     * como vivienda y dos para cualquier otro uso.
     */
    public static function legalDeposit(Property $property, int $rent): int
    {
        return $rent * ($property->type->isHousing() ? 1 : 2);
    }

    /**
     * El recibo de un mes. Si ya estaba emitido devuelve el que hay, así que emitir dos veces
     * el mismo mes no duplica nada.
     */
    public function issueReceipt(CarbonInterface $month): Receipt
    {
        $period = $month->copy()->startOfMonth();

        // Vence el día de pago pactado. El primer mes, si el contrato empieza más tarde, vence
        // el día de la firma.
        $due = $period->copy()->day($this->payment_day)->max($this->starts_on);

        return $this->receipts()->firstOrCreate(
            ['period' => $period],
            ['amount' => $this->monthly_rent, 'due_on' => $due],
        );
    }

    /** Lo que debe el inquilino: la suma de los recibos que han vencido sin cobrarse. */
    public function debt(): int
    {
        return $this->receipts->filter->isOverdue()->sum('amount');
    }
}
