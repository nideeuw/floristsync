<?php

namespace Tests\Feature;

use App\Exports\ReportExport;
use App\Services\ReportService;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The owner opens the Excel export, so user text must never run as a formula.
 */
class ReportExportTextSafetyTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function formulaStarters(): array
    {
        return [
            'equals (hyperlink)' => ['=HYPERLINK("http://x","y")'],
            'plus' => ['+1+1'],
            'minus' => ['-2+3'],
            'at sign' => ['@SUM(1+1)'],
        ];
    }

    #[DataProvider('formulaStarters')]
    public function test_product_name_starting_with_a_formula_character_exports_as_text(string $name): void
    {
        $sheet = $this->exportProducts($name);
        $row = $this->findRow($sheet, $name);

        $this->assertNotNull($row, 'The product row was not found in the export.');

        $cell = $sheet->getCell('A'.$row);

        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        $this->assertSame($name, $cell->getValue());
    }

    public function test_filter_notes_starting_with_a_formula_character_export_as_text(): void
    {
        $note = '=1+1';
        $sheet = $this->exportProducts('Pink Peony', [$note]);

        $found = false;

        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            $cell = $sheet->getCell('B'.$row);

            if ($cell->getValue() === $note) {
                $found = true;
                $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            }
        }

        $this->assertTrue($found, 'The filter note was not found in the export.');
    }

    public function test_numbers_and_rupiah_format_and_column_order_are_unchanged(): void
    {
        $sheet = $this->exportProducts('Pink Peony');
        $row = $this->findRow($sheet, 'Pink Peony');

        $this->assertNotNull($row);

        // Column order guard: heading row sits right above the first data row.
        $this->assertSame(
            ['Product', 'Category', 'Sold', 'Revenue'],
            [
                $sheet->getCell('A'.($row - 1))->getValue(),
                $sheet->getCell('B'.($row - 1))->getValue(),
                $sheet->getCell('C'.($row - 1))->getValue(),
                $sheet->getCell('D'.($row - 1))->getValue(),
            ],
        );

        $qty = $sheet->getCell('C'.$row);
        $revenue = $sheet->getCell('D'.$row);

        $this->assertSame(DataType::TYPE_NUMERIC, $qty->getDataType());
        $this->assertEquals(3, $qty->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $revenue->getDataType());
        $this->assertEquals(150000, $revenue->getValue());

        $format = $sheet->getStyle('D'.$row)->getNumberFormat()->getFormatCode();
        $this->assertStringContainsString('Rp', $format);
        $this->assertStringContainsString('#,##0', $format);
    }

    /**
     * @param  array<int, string>  $notes
     */
    private function exportProducts(string $productName, array $notes = []): Worksheet
    {
        $rows = [(object) [
            'product_name' => $productName,
            'category_name' => 'Roses',
            'total_qty' => 3,
            'total_revenue' => 150000,
        ]];

        $export = new ReportExport(
            'FloristSync',
            'Products report',
            '01 Nov 2026 - 20 Nov 2026',
            $notes,
            ReportService::columns('products'),
            $rows,
        );

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('export_', true).'.xlsx';
        file_put_contents($path, Excel::raw($export, ExcelWriter::XLSX));

        try {
            return IOFactory::load($path)->getActiveSheet();
        } finally {
            @unlink($path);
        }
    }

    private function findRow(Worksheet $sheet, string $text): ?int
    {
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            if ($sheet->getCell('A'.$row)->getValue() === $text) {
                return $row;
            }
        }

        return null;
    }
}
