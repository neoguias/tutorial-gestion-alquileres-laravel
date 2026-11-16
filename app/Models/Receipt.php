<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = ['period', 'amount', 'due_on', 'paid_on', 'reminded_at'];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'amount' => 'integer',
            'due_on' => 'date',
            'paid_on' => 'date',
            'reminded_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** Los recibos sin cobrar cuyo día de pago ya ha pasado. */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereNull('paid_on')->where('due_on', '<', today());
    }

    /** Los impagados a los que toca avisar: nunca se ha avisado, o hace una semana o más. */
    #[Scope]
    protected function toRemind(Builder $query): void
    {
        $query->overdue()->where(fn (Builder $q) => $q
            ->whereNull('reminded_at')
            ->orWhere('reminded_at', '<=', now()->subWeek()));
    }

    public function isOverdue(): bool
    {
        return $this->paid_on === null && $this->due_on->lt(today());
    }

    public function status(): ReceiptStatus
    {
        return match (true) {
            $this->paid_on !== null => ReceiptStatus::Paid,
            $this->isOverdue() => ReceiptStatus::Overdue,
            default => ReceiptStatus::Pending,
        };
    }

    /** El número que se imprime en el recibo: el año y mes, y el identificador. */
    public function number(): string
    {
        return $this->period->format('Y-m').'-'.str_pad($this->id, 5, '0', STR_PAD_LEFT);
    }

    /** «noviembre de 2026». */
    public function periodLabel(): string
    {
        return $this->period->isoFormat('MMMM [de] YYYY');
    }
}
