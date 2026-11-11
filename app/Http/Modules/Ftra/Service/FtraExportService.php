<?php

namespace App\Http\Modules\Ftra\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Ftra\Models\FtraFormat;
use App\Http\Modules\Ftra\Models\FtraRecord;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Informe de Seguimiento FTRA en Excel (mismo estilo que el cuadro de Cuentas por pagar):
 *  - Hoja "Registros": los FTRA con los filtros de la pantalla, con las fechas y responsables de cada revisión.
 *  - Hoja "Matriz": por torre, cada apartamento contra cada tipo de FTRA (formato), para ver qué le falta.
 *    Verde = aprobado por Supervisor y Director · Gris = registrado / en revisión · Rojo = devuelto · Vacío = falta.
 */
class FtraExportService
{
    protected const NAVY = '004C6C';
    protected const ORANGE = 'EE9D4C';
    protected const GREEN = 'C6EFCE';
    protected const GREEN_TEXT = '166534';
    protected const GRAY = 'E2E8F0';
    protected const RED = 'FDE2E2';

    protected const HEADERS = [
        'ID', 'TORRE', 'PISO', 'APTO', 'FORMATO', 'PROVEEDOR', 'NIT', 'INSPECCIÓN RESIDENTE', 'ORDEN Y ASEO',
        'ESTADO', 'REGISTRADO POR', 'FECHA REGISTRO', 'SUPERVISOR', 'FECHA SUPERVISOR', 'DIRECTOR DE OBRA',
        'FECHA DIRECTOR', 'OBSERVACIONES', 'ÚLTIMA DEVOLUCIÓN',
    ];
    protected const WIDTHS = [8, 14, 7, 8, 34, 32, 16, 24, 14, 26, 26, 17, 26, 17, 26, 17, 46, 46];

    /**
     * @param Collection<int, FtraRecord> $records Registros filtrados (para la hoja Registros)
     * @param Collection<int, FtraRecord> $projectRecords Todos los registros del proyecto (para la matriz)
     */
    public function build(?Project $project, Collection $records, Collection $projectRecords, array $filterLabels): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator('Elite · Inverconstrucción')->setTitle('Seguimiento FTRA');

        $this->recordsSheet($spreadsheet->getActiveSheet(), $project, $records, $filterLabels);

        $towers = $project
            ? Tower::where('project_id', $project->id)->orderBy('id')->get()->filter->hasUnits()
            : collect();
        if ($towers->isNotEmpty()) {
            $formats = FtraFormat::where('is_active', true)->orderBy('code')->get();
            $this->matrixSheet($spreadsheet->createSheet(), $towers, $formats, $projectRecords);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    protected function recordsSheet(Worksheet $sheet, ?Project $project, Collection $records, array $filterLabels): void
    {
        $sheet->setTitle('Registros');
        $last = Coordinate::stringFromColumnIndex(count(self::HEADERS));
        foreach (self::WIDTHS as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }

        $this->title($sheet, 'SEGUIMIENTO FTRA · ' . mb_strtoupper($project?->name ?? 'TODOS LOS PROYECTOS'), $last);
        $sheet->setCellValue('A2', 'Generado el ' . now()->timezone(config('app.timezone'))->format('d/m/Y h:i a')
            . ($filterLabels ? ' · Filtros: ' . implode(' · ', $filterLabels) : ''));
        $sheet->mergeCells("A2:{$last}2");
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        $row = 4;
        $sheet->fromArray(self::HEADERS, null, "A{$row}");
        $this->headerStyle($sheet, "A{$row}:{$last}{$row}");
        $first = $row + 1;

        foreach ($records as $r) {
            $row++;
            $lastReturn = $r->history->where('action', 'devuelto')->last();
            $values = [
                $r->id,
                $r->tower?->name,
                $r->piso,
                $r->apartamento,
                trim(($r->format?->code ? "[{$r->format->code}] " : '') . ($r->format?->name ?? '')),
                $r->contractor?->name,
                $r->contractor?->nit,
                $r->resultado_inspeccion,
                $r->orden_aseo,
                FtraWorkflow::statusLabel($r->status),
                $r->registeredBy?->name,
                $this->date($r->created_at),
                $r->supervisorUser?->name,
                $this->date($r->supervisor_at),
                $r->directorUser?->name,
                $this->date($r->director_at),
                $r->observations,
                $lastReturn ? trim(($lastReturn->user?->name ?? '') . ': ' . $lastReturn->comment) : null,
            ];
            foreach ($values as $i => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1) . $row, (string) ($value ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $sheet->setCellValue("A{$row}", $r->id);

            $fill = match ($r->status) {
                FtraWorkflow::STATUS_APROBADA => self::GREEN,
                FtraWorkflow::STATUS_DEVUELTA, FtraWorkflow::STATUS_RECHAZADA => self::RED,
                default => null,
            };
            if ($fill) {
                $sheet->getStyle("J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
            }
        }

        if ($records->isEmpty()) {
            $sheet->setCellValue('A5', 'No hay FTRA con los filtros seleccionados.');
        } else {
            $sheet->getStyle("A{$first}:{$last}{$row}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CBD5E1']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ]);
            $sheet->getStyle("Q{$first}:R{$row}")->getAlignment()->setWrapText(true);
            $sheet->setAutoFilter("A4:{$last}{$row}");
        }

        $sheet->freezePane('A5');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
    }

    /**
     * Matriz por torre: filas = apartamentos (del piso más alto al 1), columnas = formatos.
     */
    protected function matrixSheet(Worksheet $sheet, Collection $towers, Collection $formats, Collection $records): void
    {
        $sheet->setTitle('Matriz');
        $totalCols = 3 + max($formats->count(), 1);
        $last = Coordinate::stringFromColumnIndex($totalCols);

        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(9);
        foreach ($formats->values() as $i => $f) {
            $sheet->getColumnDimensionByColumn($i + 3)->setWidth(22);
        }
        $sheet->getColumnDimensionByColumn($totalCols)->setWidth(14);

        $this->title($sheet, 'MATRIZ FTRA POR APARTAMENTO', $last);
        $sheet->setCellValue('A2', 'Verde: aprobado (Supervisor y Director) · Gris: registrado / en revisión · Rojo: devuelto · Vacío: falta registrar');
        $sheet->mergeCells("A2:{$last}2");
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        // Último FTRA de cada (torre, apartamento, formato)
        $latest = $records->sortBy('id')->keyBy(fn (FtraRecord $r) => "{$r->tower_id}|{$r->apartamento}|{$r->format_id}");

        $row = 4;
        foreach ($towers as $tower) {
            $sheet->setCellValue("A{$row}", mb_strtoupper($tower->name) . " · {$tower->floors} pisos × {$tower->apartments_per_floor} aptos");
            $sheet->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ORANGE]],
            ]);
            $row++;

            $headers = ['PISO', 'APTO', ...$formats->map(fn ($f) => "[{$f->code}] {$f->name}")->all(), 'APROBADOS'];
            $sheet->fromArray($headers, null, "A{$row}");
            $this->headerStyle($sheet, "A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}:{$last}{$row}")->getAlignment()->setWrapText(true);
            $row++;
            $first = $row;

            for ($floor = $tower->floors; $floor >= 1; $floor--) {
                for ($i = 1; $i <= $tower->apartments_per_floor; $i++) {
                    $apto = (string) Tower::unitNumber($floor, $i);
                    $sheet->setCellValue("A{$row}", $floor);
                    $sheet->setCellValueExplicit("B{$row}", $apto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $approved = 0;

                    foreach ($formats->values() as $c => $format) {
                        $cell = Coordinate::stringFromColumnIndex($c + 3) . $row;
                        $record = $latest->get("{$tower->id}|{$apto}|{$format->id}");
                        if (!$record) {
                            continue;
                        }
                        [$label, $fill, $color] = match ($record->status) {
                            FtraWorkflow::STATUS_APROBADA => ['Aprobado', self::GREEN, self::GREEN_TEXT],
                            FtraWorkflow::STATUS_DEVUELTA, FtraWorkflow::STATUS_RECHAZADA => ['Devuelto', self::RED, 'B91C1C'],
                            FtraWorkflow::STATUS_SEGUIMIENTO => ['Pend. Director', self::GRAY, '334155'],
                            default => ['Pend. Supervisor', self::GRAY, '334155'],
                        };
                        $approved += $record->status === FtraWorkflow::STATUS_APROBADA ? 1 : 0;
                        $sheet->setCellValue($cell, $label);
                        $sheet->getStyle($cell)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
                            'font' => ['color' => ['rgb' => $color], 'bold' => $record->status === FtraWorkflow::STATUS_APROBADA],
                        ]);
                    }

                    $sheet->setCellValue("{$last}{$row}", $approved . '/' . $formats->count());
                    if ($formats->isNotEmpty() && $approved === $formats->count()) {
                        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::GREEN_TEXT);
                    }
                    $row++;
                }
            }

            $sheet->getStyle("A{$first}:{$last}" . ($row - 1))->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CBD5E1']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $row += 2;
        }

        $sheet->freezePane('C4');
    }

    protected function title(Worksheet $sheet, string $text, string $lastCol): void
    {
        $sheet->setCellValue('A1', $text);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
    }

    protected function headerStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::NAVY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F0F5']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::NAVY]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    protected function date($value): string
    {
        return $value ? $value->timezone(config('app.timezone'))->format('d/m/Y h:i a') : '';
    }
}
