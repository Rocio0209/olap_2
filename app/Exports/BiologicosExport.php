<?php

namespace App\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BiologicosExport implements FromGenerator, WithEvents, WithStrictNullComparison
{
    protected string $tmpPath;

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

    public function __construct(string $tmpPath)
    {
        $this->tmpPath = $tmpPath;
    }

    public function generator(): Generator
    {
        $this->prepareHeaders();

        // Encabezado 3 niveles.
        yield $this->buildTopHeaderRow();
        yield $this->buildMiddleHeaderRow();
        yield $this->buildBottomHeaderRow();

        $files = glob(storage_path("app/{$this->tmpPath}/*.jsonl"));
        sort($files);

        foreach ($files as $file) {
            $handle = fopen($file, 'r');

            while (($line = fgets($handle)) !== false) {
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
                if (count($coverageIndexes) >= 11) {
                    // 1-5
                    $c1 = Coordinate::stringFromColumnIndex($coverageIndexes[0]);
                    $c5 = Coordinate::stringFromColumnIndex($coverageIndexes[4]);
                    $safeMerge("{$c1}2:{$c5}2");

                    // 6-9
                    $c6 = Coordinate::stringFromColumnIndex($coverageIndexes[5]);
                    $c9 = Coordinate::stringFromColumnIndex($coverageIndexes[8]);
                    $safeMerge("{$c6}2:{$c9}2");

                    // 10 y 11 verticales (fila 2-3)
                    $c10 = Coordinate::stringFromColumnIndex($coverageIndexes[9]);
                    $c11 = Coordinate::stringFromColumnIndex($coverageIndexes[10]);
                    $safeMerge("{$c10}2:{$c10}3");
                    $safeMerge("{$c11}2:{$c11}3");
                }

                $lastCol = Coordinate::stringFromColumnIndex($totalColumns);
                $headerRange = "A1:{$lastCol}3";

                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => false],
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

                // Datos desde fila 4.
                $highestRow = $sheet->getHighestRow();
                if ($highestRow >= 4) {
                    $sheet->getStyle("A4:{$lastCol}{$highestRow}")->applyFromArray([
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
        if ($this->headersPrepared) {
            return;
        }

        $files = glob(storage_path("app/{$this->tmpPath}/*.jsonl"));
        sort($files);

        $dynamicMap = [];

        foreach ($files as $file) {
            $handle = fopen($file, 'r');

            while (($line = fgets($handle)) !== false) {
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
        $v = mb_strtoupper($variable);

        $firstGroup = [
            '% BCG',
            '% HEPATITIS B (<1 ANO)',
            '% HEXAVALENTE (<1 ANO)',
            '% ROTAVIRUS RV1',
            '% NEUMOCOCICA CONJUGADA (<1 ANO)',
        ];

        $secondGroup = [
            '% HEXAVALENTE (1 ANO)',
            '% NEUMOCOCICA CONJUGADA (1 ANO)',
            '% SRP 1RA',
            '% SRP 2DA',
        ];

        if (in_array($v, $firstGroup, true)) {
            return 'ESQUEMAS POR BIOLOGICO PARA MENORES DE 1 ANO';
        }

        if (in_array($v, $secondGroup, true)) {
            return 'ESQUEMAS COMPLETOS POR BIOLOGICO EN 1 ANO';
        }

        return $variable;
    }

    protected function getCoverageBottomLabel(string $variable): string
    {
        $v = mb_strtoupper($variable);
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
        if ($apartado !== $variable) {
            return false;
        }

        $normalized = $this->normalizeText($apartado);
        return str_starts_with($normalized, 'POBLACION');
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

    protected function normalizeText(string $value): string
    {
        $value = mb_strtoupper($value);
        return strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ñ' => 'N',
        ]);
    }

    /**
     * @return array<int,array{apartado:string,variable:string}>
     */
    protected function getAdditionalHeaderDefinitions(): array
    {
        return [
            ['apartado' => 'POBLACION <1 ANO', 'variable' => 'POBLACION <1 ANO'],
            ['apartado' => 'POBLACION 1 ANO', 'variable' => 'POBLACION 1 ANO'],
            ['apartado' => 'POBLACION 4 ANO', 'variable' => 'POBLACION 4 ANO'],
            ['apartado' => 'POBLACION 6 ANO', 'variable' => 'POBLACION 6 ANO'],

            ['apartado' => 'COBERTURA PVU', 'variable' => '% BCG'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Hepatitis B (<1 ANO)'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Hexavalente (<1 ANO)'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Rotavirus RV1'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Neumococica conjugada (<1 ANO)'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Hexavalente (1 ANO)'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% Neumococica conjugada (1 ANO)'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% SRP 1ra'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% SRP 2da'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% ESQUEMA COMPLETO DE DPT EN 4 ANOS'],
            ['apartado' => 'COBERTURA PVU', 'variable' => '% ESQUEMA COMPLETO DE SRP 2a EN 6 ANOS'],
        ];
    }
}
