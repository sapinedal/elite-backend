<?php

namespace App\Http\Modules\Pagos\Service;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Cuadro de pagos en Excel con el formato del archivo semanal "Cuentas por pagar":
 * título "PAGOS AUTORIZADOS VIERNES ...", una sección por frente (INDIRECTOS, TORRE 1, TORRE 2, URBANISMO...)
 * con las columnas ID | NOMBRE | NOTAS | SALDO | ABONO | VALOR A PAGAR | # FACTURA | AUTORIZACIÓN,
 * subtotal por frente y total general.
 */
class PagoExportService
{
    protected const HEADERS = ['ID', 'NOMBRE', 'NOTAS', 'SALDO', 'ABONO', 'VALOR A PAGAR', '# FACTURA', 'AUTORIZACIÓN', 'PRIORIDAD', 'ORIGEN'];
    protected const WIDTHS = [14, 38, 46, 16, 16, 18, 18, 30, 11, 16];
    protected const MONEY = '"$"#,##0;[Red]-"$"#,##0';
    protected const NAVY = '004C6C';
    protected const ORANGE = 'EE9D4C';
    protected const LAST_COL = 'J';

    /**
     * @param array<string, list<array>> $groups Cuentas presentadas (PagoService::present) agrupadas por frente
     */
    public function build(array $groups, ?string $paymentDate): Spreadsheet
    {
        $date = $paymentDate ? Carbon::parse($paymentDate) : now();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator('Elite · Inverconstrucción')->setTitle('Cuentas por pagar');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($date->format('d,m,y')); // Mismo nombre de hoja que el archivo semanal: 23,10,26

        foreach (self::WIDTHS as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }

        $title = $paymentDate
            ? 'PAGOS AUTORIZADOS ' . mb_strtoupper($date->locale('es')->isoFormat('dddd D [DE] MMMM YYYY'))
            : 'CUENTAS POR PAGAR AL ' . mb_strtoupper($date->locale('es')->isoFormat('D [DE] MMMM YYYY'));
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:' . self::LAST_COL . '1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $row = 3;
        $subtotalCells = [];

        foreach ($groups as $frente => $cuentas) {
            $row = $this->section($sheet, $row, (string) $frente, $cuentas, $subtotalCells);
        }

        // Total general: suma de los subtotales de cada frente
        if ($subtotalCells) {
            $sheet->setCellValue("C{$row}", 'TOTAL GENERAL');
            foreach (['D', 'E', 'F'] as $col) {
                $sheet->setCellValue("{$col}{$row}", '=' . implode('+', array_map(fn ($r) => "{$col}{$r}", $subtotalCells)));
            }
            $sheet->getStyle("A{$row}:" . self::LAST_COL . $row)->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            ]);
            $sheet->getStyle("D{$row}:F{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        } else {
            $sheet->setCellValue('A3', 'No hay cuentas con los filtros seleccionados.');
        }

        $sheet->freezePane('A2');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        return $spreadsheet;
    }

    /**
     * Escribe una sección (frente) y devuelve la siguiente fila libre.
     */
    protected function section(Worksheet $sheet, int $row, string $frente, array $cuentas, array &$subtotalCells): int
    {
        $last = self::LAST_COL;

        // Título del frente
        $sheet->setCellValue("A{$row}", $frente);
        $sheet->mergeCells("A{$row}:{$last}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ORANGE]],
        ]);
        $row++;

        // Encabezados
        $sheet->fromArray(self::HEADERS, null, "A{$row}");
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::NAVY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F0F5']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::NAVY]]],
        ]);
        $row++;

        $first = $row;
        foreach ($cuentas as $c) {
            $sheet->setCellValueExplicit("A{$row}", (string) ($c['supplier_nit'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $c['supplier_name']);
            $sheet->setCellValue("C{$row}", $c['concept']);
            $sheet->setCellValue("D{$row}", $c['amount'] - $c['adjustments']); // Saldo del documento (neto de notas crédito)
            $sheet->setCellValue("E{$row}", $c['paid_amount'] ?: null);         // Abonos
            $sheet->setCellValue("F{$row}", $c['balance']);                      // Valor a pagar
            $sheet->setCellValue("G{$row}", $c['document_number']);
            $sheet->setCellValue("H{$row}", $this->authorization($c));
            $sheet->setCellValue("I{$row}", $c['priority_label']);
            $sheet->setCellValue("J{$row}", trim($c['source_label'] . ' ' . ($c['source_code'] ?? '')));
            $row++;
        }
        $lastRow = $row - 1;

        if ($cuentas) {
            $sheet->getStyle("D{$first}:F{$lastRow}")->getNumberFormat()->setFormatCode(self::MONEY);
            $sheet->getStyle("C{$first}:C{$lastRow}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("A{$first}:{$last}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CBD5E1']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ]);
        }

        // Subtotal del frente
        $sheet->setCellValue("C{$row}", "TOTAL {$frente}");
        foreach (['D', 'E', 'F'] as $col) {
            $sheet->setCellValue("{$col}{$row}", $cuentas ? "=SUM({$col}{$first}:{$col}{$lastRow})" : 0);
        }
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]],
        ]);
        $sheet->getStyle("D{$row}:F{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $subtotalCells[] = $row;

        return $row + 2;
    }

    /**
     * Columna AUTORIZACIÓN, como en el archivo: "AUTORIZADAS PAGO 23/10/2026", "PAGADA 23/10/2026"...
     */
    protected function authorization(array $c): string
    {
        $date = fn (?string $value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('d/m/Y') : '';

        return match ($c['status']) {
            PagoService::STATUS_PRIORIZADA => 'AUTORIZADAS PAGO ' . $date($c['scheduled_for']),
            PagoService::STATUS_PAGADA => 'PAGADA ' . $date($c['paid_at']),
            default => mb_strtoupper($c['status_label']),
        };
    }
}
