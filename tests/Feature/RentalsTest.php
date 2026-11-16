<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Models\Contact;
use App\Models\Lease;
use App\Models\Property;
use App\Notifications\RentOverdue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Los contratos, los recibos de cada mes y los avisos de impago, en un 16 de noviembre fijo. */
class RentalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-16 10:00'));
    }

    private function property(array $overrides = []): Property
    {
        return Property::create($overrides + [
            'reference' => 'AT-1', 'title' => 'Estudio de prueba', 'operation' => 'rent', 'type' => 'flat',
            'zone' => 'Ruzafa', 'price' => 900, 'bedrooms' => 1, 'area' => 45,
        ]);
    }

    private function lease(array $overrides = []): Lease
    {
        $tenant = Contact::create(['name' => 'Ana Ruiz', 'email' => 'ana@example.test']);

        return Lease::create($overrides + [
            'property_id' => $this->property()->id, 'contact_id' => $tenant->id,
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'monthly_rent' => 900, 'deposit' => 900,
        ]);
    }

    public function test_the_monthly_receipts_are_issued_once(): void
    {
        $lease = $this->lease();

        $this->artisan('receipts:issue')->expectsOutputToContain('1 emitidos')->assertSuccessful();
        $this->artisan('receipts:issue')->expectsOutputToContain('0 emitidos')->assertSuccessful();

        $receipt = $lease->receipts()->sole();
        $this->assertSame(900, $receipt->amount);
        $this->assertSame('2026-11-07', $receipt->due_on->toDateString());
    }

    public function test_leases_that_have_ended_get_no_receipt(): void
    {
        $this->lease(['starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);

        $this->artisan('receipts:issue')->expectsOutputToContain('0 emitidos');
    }

    public function test_the_first_receipt_is_due_on_the_day_the_lease_starts(): void
    {
        $lease = $this->lease(['starts_on' => '2026-11-20', 'ends_on' => '2027-11-19']);

        $this->assertSame('2026-11-20', $lease->issueReceipt(today())->due_on->toDateString());
    }

    public function test_the_deposit_is_one_month_for_housing_and_two_for_other_uses(): void
    {
        $this->assertSame(900, Lease::legalDeposit($this->property(), 900));
        $this->assertSame(2400, Lease::legalDeposit($this->property(['reference' => 'AT-2', 'type' => 'commercial']), 1200));
    }

    public function test_tenants_with_overdue_receipts_are_reminded_once_a_week(): void
    {
        Notification::fake();
        $lease = $this->lease();
        $lease->issueReceipt(Carbon::parse('2026-10-01'));
        $lease->issueReceipt(today())->update(['paid_on' => today()]);

        $this->artisan('receipts:remind')->expectsOutputToContain('enviados: 1');
        Notification::assertSentTo($lease->tenant, RentOverdue::class, fn ($notification) => $notification->lease->receipts->count() === 1);

        $this->travel(3)->days();
        $this->artisan('receipts:remind')->expectsOutputToContain('enviados: 0');

        $this->travel(4)->days();
        $this->artisan('receipts:remind')->expectsOutputToContain('enviados: 1');
    }

    public function test_a_receipt_can_be_marked_as_paid(): void
    {
        $lease = $this->lease();
        $receipt = $lease->issueReceipt(Carbon::parse('2026-10-01'));
        $this->assertSame(900, $lease->debt());

        Livewire::test('pages::lease', ['lease' => $lease])
            ->call('markPaid', $receipt->id)
            ->assertSee('Cobrado');

        $this->assertSame('2026-11-16', $receipt->fresh()->paid_on->toDateString());
        $this->assertSame(0, $lease->fresh()->debt());
        $this->get(route('receipt', $receipt))->assertOk()->assertSee('Renta de octubre de 2026');
    }

    public function test_a_new_lease_proposes_the_rent_and_deposit_and_issues_this_month(): void
    {
        $property = $this->property(['type' => 'commercial', 'price' => 1100]);
        $tenant = Contact::create(['name' => 'Jorge Martí']);

        Livewire::test('pages::leases')
            ->set('form.property_id', $property->id)
            ->assertSet('form.monthly_rent', 1100)
            ->assertSet('form.deposit', 2200)
            ->set('form.contact_id', $tenant->id)
            ->set('form.period', ['start' => '2026-11-01', 'end' => '2031-10-31'])
            ->call('save')
            ->assertHasNoErrors();

        $lease = Lease::sole();
        $this->assertCount(1, $lease->receipts);
        $this->assertSame(PropertyStatus::Closed, $property->fresh()->status);
    }
}
