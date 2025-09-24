<?php

namespace App\Http\Controllers;

use App\Exports\HairdresserReportExport;
use App\Exports\ProductReportExport;
use App\Exports\SalesReportExport;
use App\Models\Shop;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function sales(Shop $shop, Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $export = new SalesReportExport($shop, $from, $to);
        $filename = 'sales-report-shop-' . $shop->id . '-' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $filename);
    }

    public function hairdressers(Shop $shop, Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $export = new HairdresserReportExport($shop, $from, $to);
        $filename = 'hairdresser-report-shop-' . $shop->id . '-' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $filename);
    }

    public function products(Shop $shop, Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $export = new ProductReportExport($shop, $from, $to);
        $filename = 'product-report-shop-' . $shop->id . '-' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $filename);
    }
}
