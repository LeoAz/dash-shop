<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu {{ $receipt->receipt_number }}</title>
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
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .receipt {
            padding: 5px;
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

        @media print {
            body {
                width: 80mm;
                margin: 0;
                padding: 0;
            }

            .receipt {
                padding: 5mm;
            }
        }
    </style>
</head>
<body>
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

    <div class="receipt-total">
        TOTAL : {{ number_format($sale->total_amount, 2) }}
    </div>

    <div class="receipt-barcode">
        {{ $receipt->receipt_number }}
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
</body>
</html>
