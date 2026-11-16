<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Pending = 'pending';
    case Overdue = 'overdue';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Overdue => 'Impagado',
            self::Paid => 'Cobrado',
        };
    }

    /** Las clases de la etiqueta de color que lo acompaña en las tablas. */
    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-800',
            self::Overdue => 'bg-red-50 text-red-700',
            self::Paid => 'bg-teal-50 text-teal-800',
        };
    }
}
