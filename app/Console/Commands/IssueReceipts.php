<?php

namespace App\Console\Commands;

use App\Models\Lease;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class IssueReceipts extends Command
{
    protected $signature = 'receipts:issue {month? : El mes, en formato AAAA-MM. Si no se indica, el actual}';

    protected $description = 'Emite el recibo del mes de cada contrato de alquiler en vigor';

    public function handle(): int
    {
        $month = $this->argument('month')
            ? Carbon::createFromFormat('!Y-m', $this->argument('month'))
            : today()->startOfMonth();

        $issued = 0;

        foreach (Lease::activeIn($month)->get() as $lease) {
            if ($lease->issueReceipt($month)->wasRecentlyCreated) {
                $issued++;
            }
        }

        $this->info("Recibos de {$month->isoFormat('MMMM [de] YYYY')}: {$issued} emitidos.");

        return self::SUCCESS;
    }
}
