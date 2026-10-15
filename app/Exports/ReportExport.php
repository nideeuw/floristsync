<?php

namespace App\Exports;

use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private const MONEY_FORMAT = '"Rp" #,##0';

    private array $rows = [];
    private array $titleRows = [];
    private int $headingRow = 0;
    private int $firstData = 0;
    private int $lastData = 0;

    public function __construct(
        private readonly string $storeName,
        private readonly string $title,
        private readonly string $period,
        private readonly array $notes,
        private readonly array $columns,
        private readonly iterable $data,
    ) {
        $this->build();
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ($this->titleRows as $row) {
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($this->columns)));
        $sheet->getStyle("A{$this->headingRow}:{$lastColumn}{$this->headingRow}")->getFont()->setBold(true);

        if ($this->lastData >= $this->firstData && $this->firstData > 0) {
            foreach ($this->columns as $index => $column) {
                if ($column['type'] !== 'money') {
                    continue;
                }

                $letter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->getStyle("{$letter}{$this->firstData}:{$letter}{$this->lastData}")
                    ->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            }
        }

        return [];
    }

    private function build(): void
    {
        $this->titleRows[] = $this->add([$this->storeName]);
        $this->titleRows[] = $this->add([$this->title]);
        $this->add(['Period', $this->period]);

        if ($this->notes !== []) {
            $this->add(['Filters', implode(', ', $this->notes)]);
        }

        $this->add(['Generated', now()->format('d M Y H:i')]);
        $this->add(['']);

        $this->headingRow = $this->add(array_column($this->columns, 'label'));

        $count = 0;

        foreach ($this->data as $row) {
            $line = [];

            foreach ($this->columns as $column) {
                $value = $row->{$column['key']} ?? null;

                $line[] = in_array($column['type'], ['money', 'int'], true)
                    ? (int) $value
                    : ReportService::format($column['type'], $value);
            }

            $index = $this->add($line);
            $this->firstData = $this->firstData ?: $index;
            $this->lastData = $index;
            $count++;
        }

        if ($count === 0) {
            $this->add(['No data for these filters.']);
        }
    }

    private function add(array $row): int
    {
        $this->rows[] = $row;

        return count($this->rows);
    }
}
