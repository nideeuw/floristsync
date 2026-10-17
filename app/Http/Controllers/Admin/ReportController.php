<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\ReportService;
use App\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $f = $this->filters($request);

        $query = $reports->query($f['tab'], $f);
        $cards = $reports->cards($f['tab'], $f, $query);
        $rows = $query->paginate($this->perPage($request))->withQueryString();

        return view('admin.reports', $f + [
            'tabs' => ReportService::TABS,
            'columns' => ReportService::columns($f['tab']),
            'cards' => $cards,
            'rows' => $rows,
            'categories' => Category::where('is_active', true)->orderBy('category_name')->get(['id', 'category_name']),
            'params' => $this->params($f),
        ]);
    }

    public function exportPdf(Request $request, ReportService $reports, SettingService $settings): Response
    {
        $f = $this->filters($request);
        $data = $this->exportData($f, $reports);

        return Pdf::loadView('admin.reports-pdf', $data + [
            'storeName' => $this->storeName($settings),
        ])->setPaper('a4', count($data['columns']) > 4 ? 'landscape' : 'portrait')
            ->download($this->filename($f, 'pdf'));
    }

    public function exportExcel(Request $request, ReportService $reports, SettingService $settings): Response
    {
        $f = $this->filters($request);
        $data = $this->exportData($f, $reports);

        $export = new ReportExport(
            $this->storeName($settings),
            $data['title'],
            $data['period'],
            $data['notes'],
            $data['columns'],
            $data['rows'],
        );

        return Excel::download($export, $this->filename($f, 'xlsx'));
    }

    private function exportData(array $f, ReportService $reports): array
    {
        $query = $reports->query($f['tab'], $f);

        return [
            'title' => ReportService::TABS[$f['tab']] . ' report',
            'period' => $f['tab'] === 'stock'
                ? 'Current stock as of ' . now()->format('d M Y H:i')
                : $f['from']->format('d M Y') . ' - ' . $f['to']->format('d M Y'),
            'notes' => $this->notes($f),
            'cards' => $reports->cards($f['tab'], $f, $query),
            'columns' => ReportService::columns($f['tab']),
            'rows' => $query->get(),
        ];
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(array_keys(ReportService::TABS))],
            'days' => ['nullable', 'integer', 'in:7,30,90'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in([Transaction::TYPE_POS, Transaction::TYPE_ONLINE])],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        if (empty($data['from']) && empty($data['to'])) {
            $days = (int) ($data['days'] ?? 30);
            $from = now()->subDays($days - 1)->startOfDay();
            $to = now()->endOfDay();
        } else {
            $to = ! empty($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
            $from = ! empty($data['from'])
                ? Carbon::parse($data['from'])->startOfDay()
                : $to->copy()->subDays(29)->startOfDay();
        }

        return [
            'tab' => $data['tab'] ?? 'sales',
            'from' => $from,
            'to' => $to,
            'type' => $data['type'] ?? null,
            'category' => isset($data['category']) ? (int) $data['category'] : null,
            'low' => $request->boolean('low'),
        ];
    }

    /** Query string that is carried to tabs, filters and exports. */
    private function params(array $f): array
    {
        return array_filter([
            'tab' => $f['tab'],
            'from' => $f['from']->format('Y-m-d'),
            'to' => $f['to']->format('Y-m-d'),
            'type' => $f['type'],
            'category' => $f['category'],
            'low' => $f['low'] ? 1 : null,
        ]);
    }

    private function notes(array $f): array
    {
        $notes = [];

        if ($f['type'] && $f['tab'] !== 'stock') {
            $notes[] = 'Type: ' . ($f['type'] === Transaction::TYPE_POS ? 'POS' : 'Online');
        }

        if ($f['category'] && in_array($f['tab'], ['products', 'stock'], true)) {
            $notes[] = 'Category: ' . (Category::find($f['category'])?->category_name ?? '-');
        }

        if ($f['low'] && $f['tab'] === 'stock') {
            $notes[] = 'Low stock only';
        }

        return $notes;
    }

    private function perPage(Request $request): int
    {
        $size = (int) $request->query('per_page', 10);

        return in_array($size, [10, 25, 50, 100], true) ? $size : 10;
    }

    private function storeName(SettingService $settings): string
    {
        return (string) $settings->get('store_name', config('app.name'));
    }

    private function filename(array $f, string $extension): string
    {
        $range = $f['tab'] === 'stock'
            ? now()->format('Ymd')
            : $f['from']->format('Ymd') . '-' . $f['to']->format('Ymd');

        return 'report-' . $f['tab'] . '-' . $range . '.' . $extension;
    }
}
