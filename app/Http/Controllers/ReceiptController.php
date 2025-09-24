<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Receipt;
use App\Models\Shop;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class ReceiptController extends Controller
{
    /**
     * Generate and display a receipt for a sale
     */
    public function generate(Sale $sale)
    {
        // Ensure the sale belongs to the current user's shop
        if (auth()->user()->hairdresser && auth()->user()->hairdresser->shop_id != $sale->shop_id) {
            abort(403, 'Unauthorized access to this receipt');
        }

        // Create receipt record if it doesn't exist
        $receipt = Receipt::firstOrCreate(
            ['sale_id' => $sale->id],
            [
                'shop_id' => $sale->shop_id,
                'receipt_number' => 'REC-' . strtoupper(uniqid()),
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]
        );

        // Load sale with products and relationships
        $sale->load(['products', 'shop', 'user']);

        // Prepare receipt data
        $receiptData = [
            'receipt' => $receipt,
            'sale' => $sale,
            'shop' => $sale->shop,
            'items' => $sale->products->map(function ($product) {
                return [
                    'name' => $product->name,
                    'quantity' => $product->pivot->quantity,
                    'unit_price' => $product->pivot->unit_price,
                    'subtotal' => $product->pivot->subtotal,
                ];
            })->toArray(),
            'date' => now()->format('d/m/Y H:i'),
            'cashier' => $sale->user->name,
        ];

        // Generate PDF
        $pdf = PDF::loadView('receipts.template', $receiptData)
            ->setPaper([0, 0, 226.77, 850], 'portrait') // 80mm width thermal paper
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isPhpEnabled', true);

        return $pdf->stream('receipt-' . $receipt->receipt_number . '.pdf');
    }

    /**
     * Generate receipt and return print view
     */
    public function print(Sale $sale)
    {
        // Ensure the sale belongs to the current user's shop
        if (auth()->user()->hairdresser && auth()->user()->hairdresser->shop_id != $sale->shop_id) {
            abort(403, 'Unauthorized access to this receipt');
        }

        // Create receipt record if it doesn't exist
        $receipt = Receipt::firstOrCreate(
            ['sale_id' => $sale->id],
            [
                'shop_id' => $sale->shop_id,
                'receipt_number' => 'REC-' . strtoupper(uniqid()),
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]
        );

        // Load sale with products and relationships
        $sale->load(['products', 'shop', 'user']);

        // Prepare receipt data
        $receiptData = [
            'receipt' => $receipt,
            'sale' => $sale,
            'shop' => $sale->shop,
            'items' => $sale->products->map(function ($product) {
                return [
                    'name' => $product->name,
                    'quantity' => $product->pivot->quantity,
                    'unit_price' => $product->pivot->unit_price,
                    'subtotal' => $product->pivot->subtotal,
                ];
            })->toArray(),
            'date' => now()->format('d/m/Y H:i'),
            'cashier' => $sale->user->name,
        ];

        return view('receipts.print', $receiptData);
    }

    /**
     * Download receipt as PDF
     */
    public function download(Sale $sale)
    {
        // Ensure the sale belongs to the current user's shop
        if (auth()->user()->hairdresser && auth()->user()->hairdresser->shop_id != $sale->shop_id) {
            abort(403, 'Unauthorized access to this receipt');
        }

        // Create receipt record if it doesn't exist
        $receipt = Receipt::firstOrCreate(
            ['sale_id' => $sale->id],
            [
                'shop_id' => $sale->shop_id,
                'receipt_number' => 'REC-' . strtoupper(uniqid()),
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]
        );

        // Load sale with products and relationships
        $sale->load(['products', 'shop', 'user']);

        // Prepare receipt data
        $receiptData = [
            'receipt' => $receipt,
            'sale' => $sale,
            'shop' => $sale->shop,
            'items' => $sale->products->map(function ($product) {
                return [
                    'name' => $product->name,
                    'quantity' => $product->pivot->quantity,
                    'unit_price' => $product->pivot->unit_price,
                    'subtotal' => $product->pivot->subtotal,
                ];
            })->toArray(),
            'date' => now()->format('d/m/Y H:i'),
            'cashier' => $sale->user->name,
        ];

        // Generate PDF
        $pdf = PDF::loadView('receipts.template', $receiptData)
            ->setPaper([0, 0, 226.77, 850], 'portrait') // 80mm width thermal paper
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isPhpEnabled', true);

        return $pdf->download('receipt-' . $receipt->receipt_number . '.pdf');
    }

    /**
     * Generate receipt for auto-printing after sale creation
     */
    public function autoPrint(Sale $sale)
    {
        // Create receipt record
        $receipt = Receipt::create([
            'sale_id' => $sale->id,
            'shop_id' => $sale->shop_id,
            'receipt_number' => 'REC-' . strtoupper(uniqid()),
            'generated_at' => now(),
            'generated_by' => auth()->id(),
        ]);

        // Load sale with products and relationships
        $sale->load(['products', 'shop', 'user']);

        // Prepare receipt data
        $receiptData = [
            'receipt' => $receipt,
            'sale' => $sale,
            'shop' => $sale->shop,
            'items' => $sale->products->map(function ($product) {
                return [
                    'name' => $product->name,
                    'quantity' => $product->pivot->quantity,
                    'unit_price' => $product->pivot->unit_price,
                    'subtotal' => $product->pivot->subtotal,
                ];
            })->toArray(),
            'date' => now()->format('d/m/Y H:i'),
            'cashier' => $sale->user->name,
        ];

        // Return print view that will auto-trigger printing
        return view('receipts.auto-print', $receiptData);
    }
}
