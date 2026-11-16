<?php

namespace Database\Seeders;

use App\Enums\PropertyStatus;
use App\Models\Contact;
use App\Models\Lease;
use App\Models\Property;
use Illuminate\Database\Seeder;

/**
 * Tres contratos de alquiler firmados hace unos meses, con sus recibos. Todos están al día
 * menos Jorge, que no ha pagado los dos últimos.
 */
class RentalSeeder extends Seeder
{
    public function run(): void
    {
        Contact::create(['name' => 'Clara Vidal', 'email' => 'clara@example.test', 'notes' => 'Arquitecta. Alquila la oficina para su estudio.']);

        $leases = [
            // Inmueble, inquilino, hace cuántos meses se firmó y para cuántos años.
            ['AT-105', 'Ana Ruiz', 8, 1],
            ['AT-107', 'Jorge Martí', 14, 5],
            ['AT-109', 'Clara Vidal', 3, 3],
        ];

        foreach ($leases as [$reference, $tenant, $monthsAgo, $years]) {
            $property = Property::where('reference', $reference)->first();
            $starts = today()->startOfMonth()->subMonths($monthsAgo);

            $lease = Lease::create([
                'property_id' => $property->id,
                'contact_id' => Contact::where('name', $tenant)->value('id'),
                'starts_on' => $starts,
                'ends_on' => $starts->copy()->addYears($years)->subDay(),
                'monthly_rent' => $property->price,
                'deposit' => Lease::legalDeposit($property, $property->price),
            ]);
            $property->update(['status' => PropertyStatus::Closed]);

            for ($month = $starts->copy(); $month->lte(today()); $month->addMonth()) {
                $receipt = $lease->issueReceipt($month);

                $unpaid = $reference === 'AT-107' && $month->gte(today()->startOfMonth()->subMonth());

                // Los demás se cobran unos días antes de vencer, si ya ha llegado ese día.
                if (! $unpaid && $receipt->due_on->lte(today())) {
                    $receipt->update(['paid_on' => $receipt->due_on->copy()->subDays(($month->month + $lease->id) % 4)]);
                }
            }
        }
    }
}
