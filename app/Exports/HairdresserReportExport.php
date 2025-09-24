<?php

namespace App\Exports;

use App\Models\Hairdresser;
use App\Models\Sale;
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

class HairdresserReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting, ShouldAutoSize, WithEvents
{
    use Exportable;

    public function __construct(
        protected Shop $shop,
        protected ?string $from = null,
        protected ?string $to = null,
    ) {}

    public function collection(): Collection
    {
        $rows = Sale::selectRaw('hairdresser_id, COUNT(*) as sales_count, SUM(total_amount) as total_amount')
            ->where('shop_id', $this->shop->id)
            ->when($this->from, fn ($q) => $q->whereDate('sale_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('sale_date', '<=', $this->to))
            ->groupBy('hairdresser_id')
            ->orderByRaw('SUM(total_amount) DESC NULLS LAST')
            ->get()
            ->map(function ($row) {
                $row->hairdresser_name = optional(Hairdresser::find($row->hairdresser_id))->name;
                return $row;
            });

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Hairdresser',
            'Sales Count',
            'Total Amount',
        ];
    }

    public function map($row): array
    {
        return [
            $row->hairdresser_name ?? '—',
            (int) $row->sales_count,
            (float) $row->total_amount,
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
            'C' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
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
