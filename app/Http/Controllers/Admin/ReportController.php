<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports, InventoryService $inventory): View
    {
        return view('admin.reports', $this->data($request, $reports, $inventory));
    }

    public function exportPdf(
        Request $request,
        ReportService $reports,
        InventoryService $inventory,
        SettingService $settings
    ): Response {
        $data = $this->data($request, $reports, $inventory);

        return Pdf::loadView('admin.reports-pdf', $data + [
            'storeName' => $this->storeName($settings),
        ])->setPaper('a4')->download($this->filename($data, 'pdf'));
    }

    public function exportExcel(
        Request $request,
        ReportService $reports,
        InventoryService $inventory,
        SettingService $settings
    ): Response {
        $data = $this->data($request, $reports, $inventory);

        $export = new ReportExport(
            $this->storeName($settings),
            $data['from'],
            $data['to'],
            $data['summary'],
            $data['topProducts'],
            $data['lowStock'],
            (int) config('floristsync.low_stock_threshold'),
        );

        return Excel::download($export, $this->filename($data, 'xlsx'));
    }

    private function data(Request $request, ReportService $reports, InventoryService $inventory): array
    {
        $days = (int) $request->query('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();

        return [
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'summary' => $reports->summary($from, $to),
            'topProducts' => $reports->topProducts($from, $to, 10),
            'lowStock' => $inventory->lowStock(),
        ];
    }

    private function storeName(SettingService $settings): string
    {
        return (string) $settings->get('store_name', config('app.name'));
    }

    private function filename(array $data, string $extension): string
    {
        return 'report-' . $data['from']->format('Ymd') . '-' . $data['to']->format('Ymd') . '.' . $extension;
    }
}
