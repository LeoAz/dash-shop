<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimer le reçu {{ $receipt->receipt_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.2;
            width: 80mm;
            margin: 0 auto;
            background: white;
            color: black;
            padding: 10px;
        }

        .receipt {
            border: 1px dashed #ccc;
            padding: 10px;
            margin: 10px 0;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }

        .receipt-header h3 {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }

        .receipt-logo {
            display: block;
            margin: 0 auto 6px auto;
            max-height: 40px;
            width: auto;
        }

        .receipt-info p {
            margin: 2px 0;
            font-size: 11px;
        }

        .receipt-items {
            margin: 10px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 10px 0;
        }

        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .receipt-item-details {
            flex: 1;
            margin-right: 10px;
        }

        .receipt-item-name {
            font-weight: bold;
            font-size: 11px;
        }

        .receipt-item-quantity {
            font-size: 10px;
            color: #666;
        }

        .receipt-item-price {
            text-align: right;
            white-space: nowrap;
        }

        .receipt-total {
            text-align: right;
            font-weight: bold;
            font-size: 14px;
            margin: 10px 0;
        }

        .receipt-footer {
            text-align: center;
            border-top: 1px dashed #000;
            padding-top: 10px;
            margin-top: 10px;
        }

        .receipt-footer p {
            margin: 2px 0;
            font-size: 10px;
        }

        .receipt-barcode {
            text-align: center;
            font-family: monospace;
            font-size: 14px;
            margin: 10px 0;
            letter-spacing: 2px;
        }

        .receipt-thank-you {
            text-align: center;
            font-weight: bold;
            margin-top: 10px;
        }

        /* Signatures */
        .signature-section {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 12px;
            margin-bottom: 8px;
        }
        .signature-box {
            flex: 1;
            border-top: 1px dashed #000;
            padding-top: 8px;
            text-align: center;
        }
        .signature-label {
            font-size: 10px;
            color: #333;
        }
        .signature-name {
            font-size: 11px;
            font-weight: bold;
            margin-top: 4px;
        }

        .print-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            white-space: nowrap;
        }

        .print-button {
            display: inline-flex;
            align-items: center;
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .print-button:hover {
            background: #0056b3;
        }

        @media print {
            body {
                width: 80mm;
                margin: 0;
                padding: 0;
            }

            .receipt {
                border: none;
                margin: 0;
                padding: 5mm;
            }

            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="print-actions">
    <button class="print-button" onclick="window.print()">
        🖨️ Imprimer le reçu
    </button>
    <button class="print-button" onclick="returnToPrevious()">
        ❌ Fermer
    </button>
</div>

<div class="receipt">
    <div class="receipt-header">
        <img src="{{ asset('img/logo 1.png') }}" alt="{{ config('app.name', 'Application') }} Logo" class="receipt-logo">
        <h3>{{ $shop->name }}</h3>
        <p>{{ $shop->address }}</p>
        @if($shop->phone)
            <p>Tél: {{ $shop->phone }}</p>
        @endif
        @if($shop->email)
            <p>Email: {{ $shop->email }}</p>
        @endif
    </div>

    <div class="receipt-info">
        <p><strong>N° de reçu :</strong> {{ $receipt->receipt_number }}</p>
        <p><strong>Date :</strong> {{ $date }}</p>
        <p><strong>Caissier :</strong> {{ $cashier }}</p>
        @if($sale->customer_name)
            <p><strong>Client :</strong> {{ $sale->customer_name }}</p>
        @endif
        @if(!empty($sale->customer_phone))
            <p><strong>Téléphone :</strong> {{ $sale->customer_phone }}</p>
        @endif
        @if($sale->hairdresser)
            <p><strong>Coiffeur :</strong> {{ $sale->hairdresser->name }}</p>
        @endif
    </div>

    <div class="receipt-items">
        @foreach($items as $item)
            <div class="receipt-item">
                <div class="receipt-item-details">
                    <div class="receipt-item-name">{{ $item['name'] }}</div>
                    <div class="receipt-item-quantity">{{ $item['quantity'] }} x {{ number_format($item['unit_price'], 2) }}</div>
                </div>
                <div class="receipt-item-price">{{ number_format($item['subtotal'], 2) }}</div>
            </div>
        @endforeach
    </div>

    @if(!empty($sale->discount_amount))
    <div class="receipt-total" style="text-align:right;font-weight:normal;font-size:12px;">
        Sous-total : {{ number_format($sale->total_amount, 2) }}
    </div>
    <div class="receipt-total" style="text-align:right;font-weight:normal;font-size:12px;">
        @php $pName = optional($sale->promotion)->name; $pPct = (float) (optional($sale->promotion)->percentage ?? 0); @endphp
        Remise{{ $pName ? ' ('.$pName.($pPct > 0 ? ' '.number_format($pPct, 0).'%' : '').')' : '' }} : -{{ number_format($sale->discount_amount, 2) }}
    </div>
    <div class="receipt-total">
        TOTAL À PAYER : {{ number_format($sale->total_amount - $sale->discount_amount, 2) }}
    </div>
    @else
    <div class="receipt-total">
        TOTAL : {{ number_format($sale->total_amount, 2) }}
    </div>
    @endif

    <div class="receipt-barcode">
        {{ $receipt->receipt_number }}
    </div>

    <!-- Signatures -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-label">Signature Coiffeur</div>
            @if($sale->hairdresser)
                <div class="signature-name">{{ $sale->hairdresser->name }}</div>
            @else
                <div class="signature-name">&nbsp;</div>
            @endif
        </div>
        <div class="signature-box">
            <div class="signature-label">Signature Caissier</div>
            <div class="signature-name">{{ $cashier }}</div>
        </div>
    </div>

    <div class="receipt-footer">
        <p>Merci pour votre achat !</p>
        <p>Veuillez conserver ce reçu pour vos réclamations.</p>
        <p>Retours acceptés dans les 30 jours avec reçu.</p>
    </div>

    <div class="receipt-thank-you">
        MERCI!
    </div>
</div>

<script>
    function returnToPrevious() {
        // Try to return to the previous page (shop details on sales section)
        if (window.history.length > 1) {
            window.history.back();
            return;
        }
        if (document.referrer) {
            window.location.href = document.referrer;
            return;
        }
        @if(isset($shop) && isset($shop->id))
            window.location.href = "{{ url('/shops/'.$shop->id) }}";
        @else
            // Fallback: if nothing else works, try to close the window
            window.close();
        @endif
    }

    // Auto-print after 1 second
    setTimeout(function() {
        window.print();
    }, 1000);

    // Focus on print button for keyboard users
    document.querySelector('.print-button').focus();
</script>
</body>
</html>
