<?php

namespace App\Exports;

use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReportExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnFormatting, ShouldAutoSize, WithEvents
{
    use Exportable;

    public string $fileName;

    public function __construct(
        protected Shop $shop,
        protected ?string $from = null,
        protected ?string $to = null,
    ) {
        $this->fileName = 'sales-report-shop-' . $shop->id . '-' . now()->format('Ymd_His') . '.xlsx';
    }

    public function query(): Builder
    {
        return Sale::query()
            ->with('hairdresser')
            ->where('shop_id', $this->shop->id)
            ->when($this->from, fn($q) => $q->whereDate('sale_date', '>=', $this->from))
            ->when($this->to, fn($q) => $q->whereDate('sale_date', '<=', $this->to))
            ->orderByDesc('sale_date');
    }

    public function headings(): array
    {
        return [
            'Sale ID',
            'Date',
            'Customer',
            'Hairdresser',
            'Status',
            'Total Amount',
        ];
    }

    public function map($sale): array
    {
        return [
            $sale->id,
            optional($sale->sale_date)->format('Y-m-d H:i'),
            $sale->customer_name,
            optional($sale->hairdresser)->name,
            $sale->status,
            (float) $sale->total_amount,
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
            'B' => NumberFormat::FORMAT_DATE_YYYYMMDD2 . ' HH:MM',
            'F' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Freeze header row
                $event->sheet->getDelegate()->freezePane('A2');
                // Autofilter
                $highestColumn = $event->sheet->getDelegate()->getHighestColumn();
                $highestRow = $event->sheet->getDelegate()->getHighestRow();
                $event->sheet->getDelegate()->setAutoFilter("A1:{$highestColumn}{$highestRow}");
            },
        ];
    }
}
