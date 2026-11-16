@php
    $lease = $receipt->lease;
    $money = fn (int $amount) => number_format($amount, 0, ',', '.').' €';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ $receipt->number() }} · {{ config('crm.agency') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-50 text-slate-800 print:bg-white">
    <main class="mx-auto my-10 max-w-2xl rounded-lg border border-slate-200 bg-white p-10 print:my-0 print:border-0 print:p-0">
        <div class="flex items-start justify-between gap-6">
            <div>
                <p class="font-semibold text-teal-700">{{ config('crm.agency') }}</p>
                <h1 class="mt-4 text-2xl font-semibold">Recibo de alquiler</h1>
                <p class="text-sm text-slate-500">Nº {{ $receipt->number() }}</p>
            </div>
            <button onclick="window.print()" class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50 print:hidden">Imprimir</button>
        </div>

        <dl class="mt-8 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
            <dt class="text-slate-500">Inquilino</dt>
            <dd>{{ $lease->tenant->name }}</dd>
            <dt class="text-slate-500">Inmueble</dt>
            <dd>{{ $lease->property->title }} ({{ $lease->property->reference }}), {{ $lease->property->zone }}</dd>
            <dt class="text-slate-500">Periodo</dt>
            <dd class="first-letter:uppercase">{{ $receipt->periodLabel() }}</dd>
            <dt class="text-slate-500">Cobrado el</dt>
            <dd>{{ $receipt->paid_on->isoFormat('D [de] MMMM [de] YYYY') }}</dd>
        </dl>

        {{-- Cada concepto en su línea y la renta en vigor aparte, como pide la ley. --}}
        <table class="mt-8 w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-slate-500">
                    <th class="py-2 font-medium">Concepto</th>
                    <th class="py-2 text-right font-medium">Importe</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-slate-100">
                    <td class="py-2">Renta de {{ $receipt->periodLabel() }}</td>
                    <td class="py-2 text-right">{{ $money($receipt->amount) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <td class="py-3">Total</td>
                    <td class="py-3 text-right">{{ $money($receipt->amount) }}</td>
                </tr>
            </tfoot>
        </table>

        <p class="mt-6 text-xs text-slate-500">Renta mensual en vigor: {{ $money($lease->monthly_rent) }}.</p>
    </main>
</body>
</html>
