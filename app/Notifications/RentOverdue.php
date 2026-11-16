<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El aviso que recibe un inquilino cuando tiene recibos vencidos sin pagar, con cada uno de
 * ellos y el total.
 */
class RentOverdue extends Notification
{
    public function __construct(public Lease $lease) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $receipts = $this->lease->receipts;
        $total = number_format($receipts->sum('amount'), 0, ',', '.');

        $message = (new MailMessage)
            ->subject($receipts->count() > 1 ? 'Tienes recibos de alquiler pendientes' : 'Tienes un recibo de alquiler pendiente')
            ->greeting("Hola, {$notifiable->name}:")
            ->line("No nos consta el pago de estos recibos de {$this->lease->property->title}:");

        foreach ($receipts as $receipt) {
            $message->line(ucfirst($receipt->periodLabel()).': '.number_format($receipt->amount, 0, ',', '.').' €, que vencía el '.$receipt->due_on->format('d/m/Y').'.');
        }

        return $message
            ->line("En total, {$total} €. Si ya lo has pagado, no hagas caso de este mensaje y disculpa la molestia.")
            ->salutation("Un saludo,\n\n".config('crm.agency'));
    }
}
