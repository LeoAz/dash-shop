<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting, ShouldAutoSize, WithEvents
{
    use Exportable;

    public function __construct(
        protected Shop $shop,
        protected ?string $from = null,
        protected ?string $to = null,
    ) {}

    public function collection(): Collection
    {
        $rows = ProductSale::selectRaw('product_id, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
            ->whereHas('sale', function ($q) {
                $q->where('shop_id', $this->shop->id)
                  ->when($this->from, fn ($qq) => $qq->whereDate('sale_date', '>=', $this->from))
                  ->when($this->to, fn ($qq) => $qq->whereDate('sale_date', '<=', $this->to));
            })
            ->groupBy('product_id')
            ->orderByRaw('SUM(subtotal) DESC')
            ->get()
            ->map(function ($row) {
                $product = Product::find($row->product_id);
                $row->product_name = optional($product)->name;
                $row->product_sku = optional($product)->sku;
                return $row;
            });

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Product',
            'SKU',
            'Total Quantity',
            'Total Revenue',
        ];
    }

    public function map($row): array
    {
        return [
            $row->product_name ?? '—',
            $row->product_sku ?? '',
            (int) $row->total_qty,
            (float) $row->total_revenue,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
                $highestColumn = $event->sheet->getDelegate()->getHighestColumn();
                $highestRow = $event->sheet->getDelegate()->getHighestRow();
                $event->sheet->getDelegate()->setAutoFilter("A1:{$highestColumn}{$highestRow}");
            },
        ];
    }
}
