<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Notifications\RentOverdue;
use Illuminate\Console\Command;

class RemindOverdueReceipts extends Command
{
    protected $signature = 'receipts:remind';

    protected $description = 'Avisa por email a los inquilinos con recibos vencidos sin pagar';

    public function handle(): int
    {
        // Un aviso por contrato con todos sus recibos impagados, solo si alguno de ellos está
        // pendiente de aviso y el inquilino tiene email.
        $leases = Lease::query()
            ->whereHas('receipts', fn ($q) => $q->toRemind())
            ->whereHas('tenant', fn ($q) => $q->whereNotNull('email'))
            ->with(['tenant', 'property', 'receipts' => fn ($q) => $q->overdue()])
            ->get();

        foreach ($leases as $lease) {
            $lease->tenant->notify(new RentOverdue($lease));
            $lease->receipts()->overdue()->update(['reminded_at' => now()]);
        }

        $this->info("Avisos de impago enviados: {$leases->count()}.");

        return self::SUCCESS;
    }
}
