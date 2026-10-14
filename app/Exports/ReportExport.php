<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private const MONEY_FORMAT = '"Rp" #,##0';

    private array $rows = [];
    private array $titleRows = [];
    private array $headingRows = [];
    private array $moneyCells = [];

    public function __construct(
        private readonly string $storeName,
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly array $summary,
        private readonly iterable $topProducts,
        private readonly iterable $lowStock,
        private readonly int $lowStockThreshold,
    ) {
        $this->build();
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Report';
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ($this->titleRows as $row) {
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
        }

        foreach ($this->headingRows as $row) {
            $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
        }

        foreach ($this->moneyCells as $cell) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
        }

        return [];
    }

    private function build(): void
    {
        $this->addTitle($this->storeName);
        $this->addTitle('Sales report');
        $this->add(['Period', $this->from->format('d M Y') . ' - ' . $this->to->format('d M Y')]);
        $this->add(['Generated', now()->format('d M Y H:i')]);
        $this->blank();

        $this->addTitle('Revenue summary');
        $this->addHeading(['Metric', 'Value']);
        $this->addMoney(['Revenue', (int) $this->summary['revenue']], 'B');
        $this->add(['Orders', (int) $this->summary['orders']]);
        $this->addMoney(['Average order value', (int) $this->summary['average']], 'B');
        $this->addMoney(['POS revenue', (int) $this->summary['pos_revenue']], 'B');
        $this->addMoney(['Online revenue', (int) $this->summary['online_revenue']], 'B');
        $this->blank();

        $this->addTitle('Best selling products');
        $this->addHeading(['Product', 'Sold', 'Revenue']);

        $top = collect($this->topProducts);

        if ($top->isEmpty()) {
            $this->add(['No sales in this period.']);
        }

        foreach ($top as $row) {
            $this->addMoney([$row->product_name, (int) $row->total_qty, (int) $row->total_revenue], 'C');
        }

        $this->blank();

        $this->addTitle('Low stock (at or below ' . $this->lowStockThreshold . ')');
        $this->addHeading(['Product', 'Stock']);

        $low = collect($this->lowStock);

        if ($low->isEmpty()) {
            $this->add(['All products have enough stock.']);
        }

        foreach ($low as $product) {
            $this->add([$product->product_name, (int) $product->stock]);
        }
    }

    private function add(array $row): int
    {
        $this->rows[] = $row;

        return count($this->rows);
    }

    private function blank(): void
    {
        $this->add(['']);
    }

    private function addTitle(string $text): void
    {
        $this->titleRows[] = $this->add([$text]);
    }

    private function addHeading(array $row): void
    {
        $this->headingRows[] = $this->add($row);
    }

    private function addMoney(array $row, string $column): void
    {
        $this->moneyCells[] = $column . $this->add($row);
    }
}
