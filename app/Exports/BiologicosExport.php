<?php

namespace App\Exports;

use App\Exceptions\ExportCancelledException;
use App\Models\Export;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BiologicosExport implements FromGenerator, WithEvents, WithStrictNullComparison
{
    protected string $tmpPath;
    protected int $exportId;
    protected int $cancelCheckCounter = 0;

    /** @var array<int,string> */
    protected array $fixedHeaders = [
        'CLUES',
        'Unidad',
        'Entidad',
        'Jurisdiccion',
        'Municipio',
        'Institucion',
    ];

    /**
     * @var array<int,array{key:string,apartado:string,variable:string}>
     */
    protected array $dynamicColumns = [];

    protected bool $headersPrepared = false;

    public function __construct(string $tmpPath, int $exportId)
    {
        $this->tmpPath = $tmpPath;
        $this->exportId = $exportId;
    }

    public function generator(): Generator
    {
        $this->ensureNotCancelled(true);
        $this->prepareHeaders();

        // Encabezado 3 niveles.
        yield $this->buildTopHeaderRow();
        yield $this->buildMiddleHeaderRow();
        yield $this->buildBottomHeaderRow();

        $files = glob(storage_path("app/{$this->tmpPath}/*.jsonl"));
        sort($files);

        foreach ($files as $file) {
            $this->ensureNotCancelled(true);
            $handle = fopen($file, 'r');

            while (($line = fgets($handle)) !== false) {
                $this->ensureNotCancelled();
                $decoded = json_decode(trim($line), true);
                if (!$decoded) {
                    continue;
                }

                $row = [
                    $decoded['clues'] ?? '',
                    $decoded['unidad']['nombre'] ?? '',
                    $decoded['unidad']['entidad'] ?? '',
                    $decoded['unidad']['jurisdiccion'] ?? '',
                    $decoded['unidad']['municipio'] ?? '',
                    $decoded['unidad']['institucion'] ?? '',
                ];

                $dynamicValues = [];
                foreach ($this->dynamicColumns as $col) {
                    $dynamicValues[$col['key']] = 0;
                }

                foreach (($decoded['biologicos'] ?? []) as $bio) {
                    $apartado = (string) ($bio['apartado'] ?? '');

                    foreach (($bio['grupos'] ?? []) as $grupo) {
                        foreach (($grupo['variables'] ?? []) as $variable) {
                            $variableLabel = (string) ($variable['variable'] ?? '');
                            $key = $this->makeDynamicKey($apartado, $variableLabel);
                            $dynamicValues[$key] = ($dynamicValues[$key] ?? 0)
                                + $this->normalizeTotal($variable['total'] ?? 0);
                        }
                    }
                }

                $orderedValues = [];
                foreach ($this->dynamicColumns as $col) {
                    $orderedValues[] = $dynamicValues[$col['key']] ?? 0;
                }

                yield array_merge($row, $orderedValues);
            }

            fclose($handle);
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $this->prepareHeaders();

                $sheet = $event->sheet->getDelegate();
                $fixedCount = count($this->fixedHeaders);
                $dynamicCount = count($this->dynamicColumns);
                $totalColumns = $fixedCount + $dynamicCount;
                $usedMergeCells = [];

                $safeMerge = function (string $range) use ($sheet, &$usedMergeCells): bool {
                    [$start, $end] = explode(':', $range);

                    preg_match('/^([A-Z]+)(\d+)$/', $start, $m1);
                    preg_match('/^([A-Z]+)(\d+)$/', $end, $m2);
                    if (!$m1 || !$m2) {
                        return false;
                    }

                    $startCol = Coordinate::columnIndexFromString($m1[1]);
                    $startRow = (int) $m1[2];
                    $endCol = Coordinate::columnIndexFromString($m2[1]);
                    $endRow = (int) $m2[2];

                    for ($c = $startCol; $c <= $endCol; $c++) {
                        for ($r = $startRow; $r <= $endRow; $r++) {
                            $key = $c . ':' . $r;
                            if (isset($usedMergeCells[$key])) {
                                return false;
                            }
                        }
                    }

                    for ($c = $startCol; $c <= $endCol; $c++) {
                        for ($r = $startRow; $r <= $endRow; $r++) {
                            $usedMergeCells[$c . ':' . $r] = true;
                        }
                    }

                    $sheet->mergeCells($range);
                    return true;
                };

                if ($totalColumns === 0) {
                    return;
                }

                // Fijos: merge vertical en 3 filas de header.
                for ($i = 1; $i <= $fixedCount; $i++) {
                    $col = Coordinate::stringFromColumnIndex($i);
                    $safeMerge("{$col}1:{$col}3");
                }

                // Merge horizontal por apartado en fila 1.
                if ($dynamicCount > 0) {
                    $startIndex = $fixedCount + 1;
                    while ($startIndex <= $totalColumns) {
                        $offset = $startIndex - ($fixedCount + 1);
                        $apartado = $this->dynamicColumns[$offset]['apartado'] ?? '';

                        $endIndex = $startIndex;
                        while ($endIndex < $totalColumns) {
                            $nextOffset = ($endIndex + 1) - ($fixedCount + 1);
                            $nextApartado = $this->dynamicColumns[$nextOffset]['apartado'] ?? null;
                            if ($nextApartado !== $apartado) {
                                break;
                            }
                            $endIndex++;
                        }

                        $startCol = Coordinate::stringFromColumnIndex($startIndex);
                        $endCol = Coordinate::stringFromColumnIndex($endIndex);
                        // Evitar merges de una sola celda (p.ej. EQ1:EQ1),
                        // porque bloquean merges verticales posteriores.
                        if ($endIndex > $startIndex) {
                            $safeMerge("{$startCol}1:{$endCol}1");
                        }
                        $startIndex = $endIndex + 1;
                    }
                }

                // Merge vertical en columnas dinámicas normales (fila 2 a 3).
                for ($i = 0; $i < $dynamicCount; $i++) {
                    $absoluteIndex = $fixedCount + 1 + $i;
                    $col = Coordinate::stringFromColumnIndex($absoluteIndex);
                    $apartado = $this->dynamicColumns[$i]['apartado'];
                    $variable = $this->dynamicColumns[$i]['variable'];

                    if ($this->isPopulationHeader($apartado, $variable)) {
                        // Poblaciones como celda completa de 3 filas.
                        $safeMerge("{$col}1:{$col}3");
                        continue;
                    }

                    if ($apartado !== 'COBERTURA PVU') {
                        $safeMerge("{$col}2:{$col}3");
                    }
                }

                // Subgrupos de COBERTURA PVU (fila 2).
                $coverageIndexes = $this->getCoverageColumnIndexes($fixedCount);
                if (count($coverageIndexes) >= 15) {
                    // 1-7
                    $c1 = Coordinate::stringFromColumnIndex($coverageIndexes[0]);
                    $c7 = Coordinate::stringFromColumnIndex($coverageIndexes[6]);
                    $safeMerge("{$c1}2:{$c7}2");

                    // 8-13
                    $c8 = Coordinate::stringFromColumnIndex($coverageIndexes[7]);
                    $c13 = Coordinate::stringFromColumnIndex($coverageIndexes[12]);
                    $safeMerge("{$c8}2:{$c13}2");

                    // 14 y 15 verticales (fila 2-3)
                    $c14 = Coordinate::stringFromColumnIndex($coverageIndexes[13]);
                    $c15 = Coordinate::stringFromColumnIndex($coverageIndexes[14]);
                    $safeMerge("{$c14}2:{$c14}3");
                    $safeMerge("{$c15}2:{$c15}3");
                }

                $lastCol = Coordinate::stringFromColumnIndex($totalColumns);
                $headerRange = "A1:{$lastCol}3";

                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => false,
                        'size' => 11,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                // Colores encabezado base A-F
                $sheet->getStyle('A1:F3')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF902449'],
                    ],
                    'font' => ['color' => ['argb' => 'FFFFFFFF']],
                ]);

                // Paleta contrastada (incluye 1 color extra).
                $palette = [
                    'FF0066CC',
                    'FFFFD965',
                    'FFFF6600',
                    'FF00CCFF',
                    'FF548135',
                    'FFFF99CC',
                    'FF6699FF',
                    'FFFF9900',
                    'FFA8D08D',
                    'FF9933FF',
                    'FFFFCC99',
                    'FF00B0F0',
                    'FFFFC000',
                    'FFD4C19C',
                    'FFF4B183',
                ];

                $apartadoColorMap = [];
                $nextColorIdx = 0;

                if ($dynamicCount > 0) {
                    for ($i = 0; $i < $dynamicCount; $i++) {
                        $absoluteIndex = $fixedCount + 1 + $i;
                        $col = Coordinate::stringFromColumnIndex($absoluteIndex);
                        $apartado = $this->dynamicColumns[$i]['apartado'];
                        $variable = $this->dynamicColumns[$i]['variable'];

                        if ($this->isPopulationHeader($apartado, $variable)) {
                            $fillColor = 'FF902449';
                            $fontColor = 'FFFFFFFF';
                        } elseif ($this->isMigranteVariable($variable)) {
                            $fillColor = 'FFECECEC';
                            $fontColor = 'FF000000';
                        } else {
                            if (!isset($apartadoColorMap[$apartado])) {
                                $apartadoColorMap[$apartado] = $palette[$nextColorIdx % count($palette)];
                                $nextColorIdx++;
                            }
                            $fillColor = $apartadoColorMap[$apartado];
                            $fontColor = 'FF000000';
                        }

                        $sheet->getStyle("{$col}1:{$col}3")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => $fillColor],
                            ],
                            'font' => ['color' => ['argb' => $fontColor]],
                        ]);
                    }
                }

                // Colores específicos solicitados para COBERTURA PVU.
                $coverageIndexes = $this->getCoverageColumnIndexes($fixedCount);
                if (!empty($coverageIndexes)) {
                    $covStartCol = Coordinate::stringFromColumnIndex($coverageIndexes[0]);
                    $covEndCol = Coordinate::stringFromColumnIndex($coverageIndexes[count($coverageIndexes) - 1]);

                    // Fila 1: COBERTURA PVU
                    $sheet->getStyle("{$covStartCol}1:{$covEndCol}1")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE8EEF7'],
                        ],
                    ]);

                    // Fila 2: subgrupos.
                    if (count($coverageIndexes) >= 15) {
                        $g1Start = Coordinate::stringFromColumnIndex($coverageIndexes[0]);
                        $g1End = Coordinate::stringFromColumnIndex($coverageIndexes[6]);
                        $g2Start = Coordinate::stringFromColumnIndex($coverageIndexes[7]);
                        $g2End = Coordinate::stringFromColumnIndex($coverageIndexes[12]);

                        $sheet->getStyle("{$g1Start}2:{$g1End}2")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFD9C27A'],
                            ],
                        ]);

                        $sheet->getStyle("{$g2Start}2:{$g2End}2")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FF9DBF88'],
                            ],
                        ]);
                    }

                    // Fila 3: variables de COBERTURA PVU.
                    foreach ($this->dynamicColumns as $i => $colData) {
                        if ($colData['apartado'] !== 'COBERTURA PVU') {
                            continue;
                        }

                        $absoluteIndex = $fixedCount + 1 + $i;
                        $col = Coordinate::stringFromColumnIndex($absoluteIndex);
                        $varColor = $this->getCoverageVariableColor($colData['variable']);
                        if ($varColor === null) {
                            continue;
                        }

                        $sheet->getStyle("{$col}3:{$col}3")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => $varColor],
                            ],
                        ]);

                        // Para columnas que están en merge 2:3 (DPT/SRP2a), colorear todo el merge.
                        if (
                            $this->normalizeText($colData['variable']) === '% ESQUEMA COMPLETO DE DPT EN 4 ANOS'
                            || $this->normalizeText($colData['variable']) === '% ESQUEMA COMPLETO DE SRP 2A EN 6 ANOS'
                        ) {
                            $sheet->getStyle("{$col}2:{$col}3")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => $varColor],
                                ],
                            ]);
                        }
                    }

                    // Refuerzo por posicion para que las 2 nuevas columnas
                    // hereden exactamente el color de su bloque.
                    if (count($coverageIndexes) >= 15) {
                        $m1c1 = Coordinate::stringFromColumnIndex($coverageIndexes[5]);
                        $m1c2 = Coordinate::stringFromColumnIndex($coverageIndexes[6]);
                        $sheet->getStyle("{$m1c1}3:{$m1c2}3")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFD9C27A'],
                            ],
                        ]);

                        $m2c1 = Coordinate::stringFromColumnIndex($coverageIndexes[11]);
                        $m2c2 = Coordinate::stringFromColumnIndex($coverageIndexes[12]);
                        $sheet->getStyle("{$m2c1}3:{$m2c2}3")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FF9DBF88'],
                            ],
                        ]);
                    }
                }

                // Tipografia solicitada:
                // A-F: 13 bold
                $sheet->getStyle('A1:F3')->applyFromArray([
                    'font' => [
                        'size' => 13,
                        'bold' => true,
                    ],
                ]);

                if ($dynamicCount > 0) {
                    $dynStartCol = Coordinate::stringFromColumnIndex($fixedCount + 1);

                    // Apartados (fila 1): 13 bold
                    $sheet->getStyle("{$dynStartCol}1:{$lastCol}1")->applyFromArray([
                        'font' => [
                            'size' => 13,
                            'bold' => true,
                        ],
                    ]);

                    // Variables/subniveles (filas 2 y 3): 11 normal
                    $sheet->getStyle("{$dynStartCol}2:{$lastCol}3")->applyFromArray([
                        'font' => [
                            'size' => 11,
                            'bold' => false,
                        ],
                    ]);

                    // POBLACION ... : 11 bold
                    for ($i = 0; $i < $dynamicCount; $i++) {
                        $apartado = $this->dynamicColumns[$i]['apartado'];
                        $variable = $this->dynamicColumns[$i]['variable'];
                        if (!$this->isPopulationHeader($apartado, $variable)) {
                            continue;
                        }

                        $absoluteIndex = $fixedCount + 1 + $i;
                        $col = Coordinate::stringFromColumnIndex($absoluteIndex);
                        $sheet->getStyle("{$col}1:{$col}3")->applyFromArray([
                            'font' => [
                                'size' => 11,
                                'bold' => true,
                            ],
                        ]);
                    }

                    // COBERTURA PVU (fila 1): 13 bold
                    if (!empty($coverageIndexes)) {
                        $covStartCol = Coordinate::stringFromColumnIndex($coverageIndexes[0]);
                        $covEndCol = Coordinate::stringFromColumnIndex($coverageIndexes[count($coverageIndexes) - 1]);
                        $sheet->getStyle("{$covStartCol}1:{$covEndCol}1")->applyFromArray([
                            'font' => [
                                'size' => 13,
                                'bold' => true,
                            ],
                        ]);
                    }

                    // ESQUEMAS ... (fila 2): 11 bold
                    if (count($coverageIndexes) >= 11) {
                        $g1Start = Coordinate::stringFromColumnIndex($coverageIndexes[0]);
                        $g1End = Coordinate::stringFromColumnIndex($coverageIndexes[4]);
                        $g2Start = Coordinate::stringFromColumnIndex($coverageIndexes[5]);
                        $g2End = Coordinate::stringFromColumnIndex($coverageIndexes[8]);

                        $sheet->getStyle("{$g1Start}2:{$g1End}2")->applyFromArray([
                            'font' => [
                                'size' => 11,
                                'bold' => true,
                            ],
                        ]);
                        $sheet->getStyle("{$g2Start}2:{$g2End}2")->applyFromArray([
                            'font' => [
                                'size' => 11,
                                'bold' => true,
                            ],
                        ]);

                        // % ESQUEMA COMPLETO ... (columnas 14 y 15 de cobertura): 11 bold
                        $c14 = Coordinate::stringFromColumnIndex($coverageIndexes[13]);
                        $c15 = Coordinate::stringFromColumnIndex($coverageIndexes[14]);
                        $sheet->getStyle("{$c14}2:{$c14}3")->applyFromArray([
                            'font' => [
                                'size' => 11,
                                'bold' => true,
                            ],
                        ]);
                        $sheet->getStyle("{$c15}2:{$c15}3")->applyFromArray([
                            'font' => [
                                'size' => 11,
                                'bold' => true,
                            ],
                        ]);
                    }
                }

                // Datos desde fila 4.
                $highestRow = $sheet->getHighestRow();
                if ($highestRow >= 4) {
                    $sheet->getStyle("A4:{$lastCol}{$highestRow}")->applyFromArray([
                        'font' => [
                            'size' => 10,
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                        ],
                    ]);
                }

                // Aplicar formulas de cobertura (fila 4 en adelante).
                $this->applyCoverageFormulas($sheet, $totalColumns, $highestRow);

                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getRowDimension(2)->setRowHeight(36);
                $sheet->getRowDimension(3)->setRowHeight(124);

                // Anchos
                $sheet->getColumnDimension('A')->setWidth(17);
                foreach (range('B', 'F') as $baseCol) {
                    $sheet->getColumnDimension($baseCol)->setWidth(13);
                }
                for ($i = 7; $i <= $totalColumns; $i++) {
                    $col = Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($col)->setWidth(15);
                }

                $sheet->getStyle("A1:{$lastCol}{$highestRow}")
                    ->getAlignment()
                    ->setWrapText(true);
            },
        ];
    }

    protected function prepareHeaders(): void
    {
        $this->ensureNotCancelled(true);
        if ($this->headersPrepared) {
            return;
        }

        $files = glob(storage_path("app/{$this->tmpPath}/*.jsonl"));
        sort($files);

        $dynamicMap = [];

        foreach ($files as $file) {
            $this->ensureNotCancelled(true);
            $handle = fopen($file, 'r');

            while (($line = fgets($handle)) !== false) {
                $this->ensureNotCancelled();
                $decoded = json_decode(trim($line), true);
                if (!$decoded) {
                    continue;
                }

                foreach (($decoded['biologicos'] ?? []) as $bio) {
                    $apartado = (string) ($bio['apartado'] ?? '');

                    foreach (($bio['grupos'] ?? []) as $grupo) {
                        foreach (($grupo['variables'] ?? []) as $variable) {
                            $variableLabel = (string) ($variable['variable'] ?? '');
                            $key = $this->makeDynamicKey($apartado, $variableLabel);
                            if (!isset($dynamicMap[$key])) {
                                $dynamicMap[$key] = [
                                    'key' => $key,
                                    'apartado' => $apartado,
                                    'variable' => $variableLabel,
                                ];
                            }
                        }
                    }
                }
            }

            fclose($handle);
        }

        foreach ($this->getAdditionalHeaderDefinitions() as $extra) {
            $key = $this->makeDynamicKey($extra['apartado'], $extra['variable']);
            if (!isset($dynamicMap[$key])) {
                $dynamicMap[$key] = [
                    'key' => $key,
                    'apartado' => $extra['apartado'],
                    'variable' => $extra['variable'],
                ];
            }
        }

        $this->dynamicColumns = array_values($dynamicMap);
        $this->headersPrepared = true;
    }

    protected function ensureNotCancelled(bool $force = false): void
    {
        if (!$force) {
            $this->cancelCheckCounter++;
            if ($this->cancelCheckCounter % 100 !== 0) {
                return;
            }
        }

        $status = Export::query()
            ->whereKey($this->exportId)
            ->value('status');

        if ($status === 'cancelled') {
            throw new ExportCancelledException('Export cancelled by user.');
        }
    }

    /**
     * @return array<int,string>
     */
    protected function buildTopHeaderRow(): array
    {
        $row = $this->fixedHeaders;

        $lastApartado = null;
        foreach ($this->dynamicColumns as $col) {
            if ($col['apartado'] !== $lastApartado) {
                $row[] = $col['apartado'];
                $lastApartado = $col['apartado'];
            } else {
                $row[] = '';
            }
        }

        return $row;
    }

    /**
     * @return array<int,string>
     */
    protected function buildMiddleHeaderRow(): array
    {
        $row = array_fill(0, count($this->fixedHeaders), '');

        foreach ($this->dynamicColumns as $col) {
            if ($this->isPopulationHeader($col['apartado'], $col['variable'])) {
                $row[] = '';
                continue;
            }

            if ($col['apartado'] !== 'COBERTURA PVU') {
                $row[] = $col['variable'];
                continue;
            }

            // Bloques intermedios para COBERTURA PVU.
            $row[] = $this->getCoverageGroupLabel($col['variable']);
        }

        return $row;
    }

    /**
     * @return array<int,string>
     */
    protected function buildBottomHeaderRow(): array
    {
        $row = array_fill(0, count($this->fixedHeaders), '');

        foreach ($this->dynamicColumns as $col) {
            if ($this->isPopulationHeader($col['apartado'], $col['variable'])) {
                $row[] = '';
                continue;
            }

            if ($col['apartado'] !== 'COBERTURA PVU') {
                $row[] = '';
                continue;
            }

            $row[] = $this->getCoverageBottomLabel($col['variable']);
        }

        return $row;
    }

    protected function getCoverageGroupLabel(string $variable): string
    {
        $v = $this->normalizeText($variable);

        $firstGroup = [
            '% BCG',
            '% HEPATITIS B (<1 ANO)',
            '% HEXAVALENTE (<1 ANO)',
            '% ROTAVIRUS RV1',
            '% NEUMOCOCICA CONJUGADA (<1 ANO)',
            'DOSIS APLICADAS PARA CALCULO DE PROMEDIO DE ESQUEMAS COMPLETOS <1 ANO',
            'PROMEDIO ESQUEMA COMPLETO COBERTURAS EN <1 ANO',
        ];

        $secondGroup = [
            '% HEXAVALENTE (1 ANO)',
            '% NEUMOCOCICA CONJUGADA (1 ANO)',
            '% SRP 1RA',
            '% SRP 2DA',
            'DOSIS APLICADAS PARA CALCULO DE PROMEDIO DE ESQUEMAS COMPLETOS 1 ANO',
            '% PROMEDIO ESQUEMA COMPLETO EN 1 ANO',
        ];

        if (in_array($v, $firstGroup, true)) {
            return 'ESQUEMAS POR BIOLOGICO PARA MENORES DE 1 AÑO';
        }

        if (in_array($v, $secondGroup, true)) {
            return 'ESQUEMAS COMPLETOS POR BIOLOGICO EN 1 AÑO';
        }

        return $variable;
    }

    protected function getCoverageBottomLabel(string $variable): string
    {
        $v = $this->normalizeText($variable);
        $standalone = [
            '% ESQUEMA COMPLETO DE DPT EN 4 ANOS',
            '% ESQUEMA COMPLETO DE SRP 2A EN 6 ANOS',
        ];

        if (in_array($v, $standalone, true)) {
            return '';
        }

        return $variable;
    }

    /**
     * @return array<int,int>
     */
    protected function getCoverageColumnIndexes(int $fixedCount): array
    {
        $indexes = [];
        foreach ($this->dynamicColumns as $i => $col) {
            if ($col['apartado'] === 'COBERTURA PVU') {
                $indexes[] = $fixedCount + 1 + $i;
            }
        }
        return $indexes;
    }

    protected function isPopulationHeader(string $apartado, string $variable): bool
    {
        $apartadoN = $this->normalizeText($apartado);
        $variableN = $this->normalizeText($variable);

        if ($apartadoN !== $variableN) {
            return false;
        }

        return str_starts_with($apartadoN, 'POBLACION');
    }

    protected function makeDynamicKey(string $apartado, string $variable): string
    {
        return $apartado . ' | ' . $variable;
    }

    protected function normalizeTotal(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    protected function isMigranteVariable(string $variable): bool
    {
        $v = mb_strtoupper($variable);
        return str_contains($v, 'MIGRANTE');
    }

    protected function getCoverageVariableColor(string $variable): ?string
    {
        return match ($this->normalizeText($variable)) {
            '% BCG' => 'FF0066CC',
            '% HEPATITIS B (<1 ANO)' => 'FFFFD965',
            '% HEXAVALENTE (<1 ANO)' => 'FF6699FF',
            '% ROTAVIRUS RV1' => 'FFFFC000',
            '% NEUMOCOCICA CONJUGADA (<1 ANO)' => 'FF548135',
            '% HEXAVALENTE (1 ANO)' => 'FFD4C19C',
            '% NEUMOCOCICA CONJUGADA (1 ANO)' => 'FF548135',
            '% SRP 1RA' => 'FF6699FF',
            '% SRP 2DA' => 'FF6699FF',
            'DOSIS APLICADAS PARA CALCULO DE PROMEDIO DE ESQUEMAS COMPLETOS <1 ANO' => 'FFD9C27A',
            'PROMEDIO ESQUEMA COMPLETO COBERTURAS EN <1 ANO' => 'FFD9C27A',
            'DOSIS APLICADAS PARA CALCULO DE PROMEDIO DE ESQUEMAS COMPLETOS 1 ANO' => 'FF9DBF88',
            '% PROMEDIO ESQUEMA COMPLETO EN 1 ANO' => 'FF9DBF88',
            '% ESQUEMA COMPLETO DE DPT EN 4 ANOS' => 'FF00CCFF',
            '% ESQUEMA COMPLETO DE SRP 2A EN 6 ANOS' => 'FF6699FF',
            default => null,
        };
    }

    protected function normalizeText(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = strtr($value, [
            'Á' => 'A',
            'À' => 'A',
            'Ä' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'É' => 'E',
            'È' => 'E',
            'Ë' => 'E',
            'Ê' => 'E',
            'Í' => 'I',
            'Ì' => 'I',
            'Ï' => 'I',
            'Î' => 'I',
            'Ó' => 'O',
            'Ò' => 'O',
            'Ö' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ú' => 'U',
            'Ù' => 'U',
            'Ü' => 'U',
            'Û' => 'U',
            'Ñ' => 'N',
        ]);

        // Limpieza extra por si llegan variantes de codificacion rara.
        $value = str_replace(['Ã‘', 'Ã“', 'Ã', 'Ã‰', 'Ã', 'Ãš'], ['N', 'O', 'A', 'E', 'I', 'U'], $value);

        return $value;
    }

    protected function applyCoverageFormulas(Worksheet $sheet, int $totalColumns, int $highestRow): void
    {
        if ($highestRow < 4) {
            return;
        }

        [$labelToColumn, $codeToColumn] = $this->buildLabelAndCodeMaps($sheet, $totalColumns);
        $populationColumns = $this->resolvePopulationColumns($labelToColumn);
        $formulaDefinitions = $this->getCoverageFormulaDefinitions();
        $selectedVariantsByLabel = [];

        foreach ($formulaDefinitions as $targetLabel => $def) {
            $targetKey = $this->normalizeText($targetLabel);
            $targetColumn = $labelToColumn[$targetKey] ?? null;
            if (!$targetColumn) {
                continue;
            }

            $populationColumn = $populationColumns[$def['population_key']] ?? null;
            if (!$populationColumn) {
                continue;
            }

            $selectedVariant = null;
            foreach ($def['variants'] as $variant) {
                $allCodesExist = true;
                foreach ($variant as $code) {
                    if (!isset($codeToColumn[$code])) {
                        $allCodesExist = false;
                        break;
                    }
                }
                if ($allCodesExist) {
                    $selectedVariant = $variant;
                    break;
                }
            }

            if ($selectedVariant === null) {
                continue;
            }
            $selectedVariantsByLabel[$targetKey] = $selectedVariant;

            $targetColLetter = Coordinate::stringFromColumnIndex($targetColumn);
            for ($row = 4; $row <= $highestRow; $row++) {
                $numeratorParts = [];
                foreach ($selectedVariant as $code) {
                    $srcCol = Coordinate::stringFromColumnIndex($codeToColumn[$code]);
                    $numeratorParts[] = "{$srcCol}{$row}";
                }

                $numeratorExpr = count($numeratorParts) > 1
                    ? '(' . implode('+', $numeratorParts) . ')'
                    : $numeratorParts[0];

                $populationColLetter = Coordinate::stringFromColumnIndex($populationColumn);
                $formula = "=IFERROR(($numeratorExpr)/(($populationColLetter$row*0.0833)*12),0)";
                $sheet->setCellValue("{$targetColLetter}{$row}", $formula);
            }
        }

        // DOSIS APLICADAS ... <1 AÑO = suma de los numeradores seleccionados
        // de las 5 primeras formulas de menores de 1 año.
        $this->applyDoseAppliedFormula(
            $sheet,
            $highestRow,
            $labelToColumn,
            $codeToColumn,
            $selectedVariantsByLabel,
            'DOSIS APLICADAS PARA CALCULO DE PROMEDIO DE ESQUEMAS COMPLETOS <1 ANO',
            [
                '% BCG',
                '% HEPATITIS B (<1 ANO)',
                '% HEXAVALENTE (<1 ANO)',
                '% ROTAVIRUS RV1',
                '% NEUMOCOCICA CONJUGADA (<1 ANO)',
            ]
        );

        // DOSIS APLICADAS ... 1 AÑO = suma de numeradores de las formulas 1 año.
        $this->applyDoseAppliedFormula(
            $sheet,
            $highestRow,
            $labelToColumn,
            $codeToColumn,
            $selectedVariantsByLabel,
            'DOSIS APLICADAS PARA CALCULO DE PROMEDIO DE ESQUEMAS COMPLETOS 1 ANO',
            [
                '% HEXAVALENTE (1 ANO)',
                '% NEUMOCOCICA CONJUGADA (1 ANO)',
                '% SRP 1RA',
                '% SRP 2DA',
            ]
        );

        // PROMEDIO ESQUEMA COMPLETO COBERTURAS EN <1 AÑO
        // Formula solicitada: POBLACION / (((DOSIS * 0.0833) * 12) * 5)
        $this->applyPopulationOverDoseFormula(
            $sheet,
            $highestRow,
            $labelToColumn,
            $populationColumns,
            'PROMEDIO ESQUEMA COMPLETO COBERTURAS EN <1 ANO',
            'DOSIS APLICADAS PARA CALCULO DE PROMEDIO DE ESQUEMAS COMPLETOS <1 ANO',
            'POBLACION_MENOR_1_ANO',
            5
        );

        // % PROMEDIO ESQUEMA COMPLETO EN 1 AÑO
        $this->applyPopulationOverDoseFormula(
            $sheet,
            $highestRow,
            $labelToColumn,
            $populationColumns,
            '% PROMEDIO ESQUEMA COMPLETO EN 1 ANO',
            'DOSIS APLICADAS PARA CALCULO DE PROMEDIO DE ESQUEMAS COMPLETOS 1 ANO',
            'POBLACION_1_ANO',
            4
        );
    }

    /**
     * @param array<string,int> $labelToColumn
     * @param array<string,int> $codeToColumn
     * @param array<string,array<int,string>> $selectedVariantsByLabel
     * @param array<int,string> $sourceFormulaLabels
     */
    protected function applyDoseAppliedFormula(
        Worksheet $sheet,
        int $highestRow,
        array $labelToColumn,
        array $codeToColumn,
        array $selectedVariantsByLabel,
        string $targetDoseLabel,
        array $sourceFormulaLabels
    ): void {
        $targetKey = $this->normalizeText($targetDoseLabel);
        $targetColumn = $labelToColumn[$targetKey] ?? null;
        if (!$targetColumn) {
            return;
        }

        $targetColLetter = Coordinate::stringFromColumnIndex($targetColumn);

        for ($row = 4; $row <= $highestRow; $row++) {
            $terms = [];

            foreach ($sourceFormulaLabels as $label) {
                $sourceKey = $this->normalizeText($label);
                $codes = $selectedVariantsByLabel[$sourceKey] ?? null;
                if (!$codes) {
                    continue;
                }

                $cells = [];
                foreach ($codes as $code) {
                    $colIdx = $codeToColumn[$code] ?? null;
                    if (!$colIdx) {
                        $cells = [];
                        break;
                    }
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $cells[] = "{$col}{$row}";
                }

                if (empty($cells)) {
                    continue;
                }

                $terms[] = count($cells) > 1
                    ? '(' . implode('+', $cells) . ')'
                    : $cells[0];
            }

            if (empty($terms)) {
                $sheet->setCellValue("{$targetColLetter}{$row}", 0);
                continue;
            }

            $sumExpr = implode('+', $terms);
            $sheet->setCellValue("{$targetColLetter}{$row}", "=IFERROR({$sumExpr},0)");
        }
    }

    /**
     * @param array<string,int> $labelToColumn
     * @param array<string,int> $populationColumns
     */
    protected function applyPopulationOverDoseFormula(
        Worksheet $sheet,
        int $highestRow,
        array $labelToColumn,
        array $populationColumns,
        string $targetLabel,
        string $doseAppliedLabel,
        string $populationKey,
        int $divisorFactor
    ): void {
        $targetColumn = $labelToColumn[$this->normalizeText($targetLabel)] ?? null;
        $doseColumn = $labelToColumn[$this->normalizeText($doseAppliedLabel)] ?? null;
        $populationColumn = $populationColumns[$populationKey] ?? null;

        if (!$targetColumn || !$doseColumn || !$populationColumn) {
            return;
        }

        $targetCol = Coordinate::stringFromColumnIndex($targetColumn);
        $doseCol = Coordinate::stringFromColumnIndex($doseColumn);
        $populationCol = Coordinate::stringFromColumnIndex($populationColumn);

        for ($row = 4; $row <= $highestRow; $row++) {
            $formula = "=IFERROR(($populationCol$row)/(((($doseCol$row)*0.0833)*12)*$divisorFactor),0)";
            $sheet->setCellValue("{$targetCol}{$row}", $formula);
        }
    }

    /**
     * @return array{0: array<string,int>, 1: array<string,int>}
     */
    protected function buildLabelAndCodeMaps(Worksheet $sheet, int $totalColumns): array
    {
        $labelToColumn = [];
        $codeToColumn = [];

        for ($col = 1; $col <= $totalColumns; $col++) {
            $texts = [];
            for ($row = 1; $row <= 3; $row++) {
                $value = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                if (!is_string($value) || trim($value) === '') {
                    continue;
                }
                $texts[] = trim($value);
            }

            foreach ($texts as $text) {
                $normalized = $this->normalizeText($text);
                if ($normalized !== '' && !isset($labelToColumn[$normalized])) {
                    $labelToColumn[$normalized] = $col;
                }

                if (preg_match_all('/\b([A-Z]{3}\d{2})\b/u', $normalized, $matches)) {
                    foreach ($matches[1] as $code) {
                        if (!isset($codeToColumn[$code])) {
                            $codeToColumn[$code] = $col;
                        }
                    }
                }
            }
        }

        return [$labelToColumn, $codeToColumn];
    }

    /**
     * @param array<string,int> $labelToColumn
     * @return array<string,int>
     */
    protected function resolvePopulationColumns(array $labelToColumn): array
    {
        return [
            'POBLACION_MENOR_1_ANO' => $labelToColumn['POBLACION <1 ANO'] ?? 0,
            'POBLACION_1_ANO' => $labelToColumn['POBLACION 1 ANO'] ?? 0,
            'POBLACION_4_ANOS' => $labelToColumn['POBLACION 4 ANO'] ?? 0,
            'POBLACION_6_ANOS' => $labelToColumn['POBLACION 6 ANO'] ?? 0,
        ];
    }

    /**
     * @return array<string,array{population_key:string,variants:array<int,array<int,string>>}>
     */
    protected function getCoverageFormulaDefinitions(): array
    {
        return [
            '% BCG' => [
                'population_key' => 'POBLACION_MENOR_1_ANO',
                'variants' => [
                    ['BIO01', 'BIO50'],
                    ['VBC02', 'BIO50'],
                ],
            ],
            '% HEPATITIS B (<1 ANO)' => [
                'population_key' => 'POBLACION_MENOR_1_ANO',
                'variants' => [
                    ['VAC06'],
                    ['BIO08'],
                ],
            ],
            '% HEXAVALENTE (<1 ANO)' => [
                'population_key' => 'POBLACION_MENOR_1_ANO',
                'variants' => [
                    ['BIO05'],
                    ['VAC03'],
                    ['VAC69'],
                ],
            ],
            '% ROTAVIRUS RV1' => [
                'population_key' => 'POBLACION_MENOR_1_ANO',
                'variants' => [
                    ['BIO56'],
                    ['VAC14'],
                    ['VRV02', 'VRV04'],
                ],
            ],
            '% NEUMOCOCICA CONJUGADA (<1 ANO)' => [
                'population_key' => 'POBLACION_MENOR_1_ANO',
                'variants' => [
                    ['BIO15'],
                    ['VAC18'],
                ],
            ],
            '% HEXAVALENTE (1 ANO)' => [
                'population_key' => 'POBLACION_1_ANO',
                'variants' => [
                    ['BIO06'],
                    ['VAC04'],
                    ['VAC70'],
                ],
            ],
            '% NEUMOCOCICA CONJUGADA (1 ANO)' => [
                'population_key' => 'POBLACION_1_ANO',
                'variants' => [
                    ['BIO16'],
                    ['VAC19'],
                ],
            ],
            '% SRP 1RA' => [
                'population_key' => 'POBLACION_1_ANO',
                'variants' => [
                    ['BIO30'],
                    ['VAC23'],
                ],
            ],
            '% SRP 2DA' => [
                'population_key' => 'POBLACION_1_ANO',
                'variants' => [
                    ['BIO63'],
                    ['VAC25'],
                    ['VTV01'],
                ],
            ],
            '% ESQUEMA COMPLETO DE DPT EN 4 ANOS' => [
                'population_key' => 'POBLACION_4_ANOS',
                'variants' => [
                    ['BIO55'],
                    ['BIO90'],
                    ['VAC12'],
                ],
            ],
            '% ESQUEMA COMPLETO DE SRP 2A EN 6 ANOS' => [
                'population_key' => 'POBLACION_6_ANOS',
                'variants' => [
                    ['BIO64'],
                    ['BIO98'],
                    ['VAC24'],
                    ['VAC81'],
                ],
            ],
        ];
    }

    /**
     * @return array<int,array{apartado:string,variable:string}>
     */
    protected function getAdditionalHeaderDefinitions(): array
    {
        return [
            ['apartado' => 'POBLACION <1 AÑO', 'variable' => 'POBLACIÓN <1 AÑO'],
            ['apartado' => 'POBLACION 1 AÑO', 'variable' => 'POBLACIÓN 1 AÑO'],
            ['apartado' => 'POBLACION 4 AÑO', 'variable' => 'POBLACIÓN 4 AÑO'],
            ['apartado' => 'POBLACION 6 AÑO', 'variable' => 'POBLACIÓN 6 AÑO'],

            ['apartado' => 'COBERTURA PVU', 'variable' => '% BCG'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Hepatitis B (<1 AÑO)'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Hexavalente (<1 AÑO)'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Rotavirus RV1'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Neumococica conjugada (<1 AÑO)'],
            ['apartado' => 'COBERTURA PVU', 'variable' => 'DOSIS APLICADAS PARA CÁLCULO DE PROMEDIO DE ESQUEMAS COMPLETOS <1 AÑO'],
            ['apartado' => 'COBERTURA PVU', 'variable' => 'PROMEDIO ESQUEMA COMPLETO COBERTURAS EN <1 AÑO'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Hexavalente (1 AÑO)'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Neumococica conjugada (1 AÑO)'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% SRP 1ra'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% SRP 2da'],
            ['apartado' => 'COBERTURA PVU', 'variable' => 'DOSIS APLICADAS PARA CÁLCULO DE PROMEDIO DE ESQUEMAS COMPLETOS 1 AÑO'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% PROMEDIO ESQUEMA COMPLETO EN 1 AÑO'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% ESQUEMA COMPLETO DE DPT EN 4 AÑOS'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% ESQUEMA COMPLETO DE SRP 2a EN 6 AÑOS'],
        ];
    }
}
