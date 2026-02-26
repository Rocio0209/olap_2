<?php

namespace App\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithEvents;
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

        // Fila 1: apartados (los fijos quedan en fila 1 y se combinan con fila 2)
        yield $this->buildTopHeaderRow();

        // Fila 2: variables
        yield $this->buildVariableHeaderRow();

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

                if ($totalColumns === 0) {
                    return;
                }

                // Fijos: merge vertical fila 1 y 2.
                for ($i = 1; $i <= $fixedCount; $i++) {
                    $col = Coordinate::stringFromColumnIndex($i);
                    $sheet->mergeCells("{$col}1:{$col}2");
                }

                // Dinámicos: merge horizontal por apartado en fila 1.
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
                        $sheet->mergeCells("{$startCol}1:{$endCol}1");

                        $startIndex = $endIndex + 1;
                    }
                }

                $lastCol = Coordinate::stringFromColumnIndex($totalColumns);
                $headerRange = "A1:{$lastCol}2";

                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => false,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // Colores encabezado base A-F
                $sheet->getStyle('A1:F2')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF902449'],
                    ],
                    'font' => [
                        'color' => ['argb' => 'FFFFFFFF'],
                    ],
                ]);

                // Paleta contrastada (incluye 1 color extra: #f4b183)
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

                // Bordes para datos también.
                $highestRow = $sheet->getHighestRow();
                if ($highestRow >= 3) {
                    $sheet->getStyle("A3:{$lastCol}{$highestRow}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                            ],
                        ],
                    ]);
                }

                // Colores por apartado (mismo color para fila 1 y 2),
                // excepto columnas "TOTAL ... MIGRANTES" que van en gris.
                if ($dynamicCount > 0) {
                    $startIndex = $fixedCount + 1;

                    while ($startIndex <= $totalColumns) {
                        $offset = $startIndex - ($fixedCount + 1);
                        $apartado = $this->dynamicColumns[$offset]['apartado'] ?? '';
                        $variable = $this->dynamicColumns[$offset]['variable'] ?? '';
                        $isMigrante = $this->isMigranteVariable($variable);

                        $endIndex = $startIndex;

                        if (!$isMigrante) {
                            while ($endIndex < $totalColumns) {
                                $nextOffset = ($endIndex + 1) - ($fixedCount + 1);
                                $nextApartado = $this->dynamicColumns[$nextOffset]['apartado'] ?? null;
                                $nextVariable = $this->dynamicColumns[$nextOffset]['variable'] ?? '';
                                $nextIsMigrante = $this->isMigranteVariable($nextVariable);

                                if ($nextApartado !== $apartado || $nextIsMigrante) {
                                    break;
                                }

                                $endIndex++;
                            }
                        }

                        $startCol = Coordinate::stringFromColumnIndex($startIndex);
                        $endCol = Coordinate::stringFromColumnIndex($endIndex);

                        if ($isMigrante) {
                            $fillColor = 'FFECECEC';
                        } else {
                            if (!isset($apartadoColorMap[$apartado])) {
                                $apartadoColorMap[$apartado] = $palette[$nextColorIdx % count($palette)];
                                $nextColorIdx++;
                            }
                            $fillColor = $apartadoColorMap[$apartado];
                        }

                        $sheet->getStyle("{$startCol}1:{$endCol}2")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => $fillColor],
                            ],
                            'font' => [
                                'color' => ['argb' => 'FF000000'],
                            ],
                        ]);

                        $startIndex = $endIndex + 1;
                    }
                }

                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getRowDimension(2)->setRowHeight(160);

                // Ancho fijo para columnas base (A con ancho especial).
                $sheet->getColumnDimension('A')->setWidth(17);
                foreach (range('B', 'F') as $baseCol) {
                    $sheet->getColumnDimension($baseCol)->setWidth(13);
                }

                // Ancho fijo para columnas dinamicas (G en adelante).
                for ($i = 7; $i <= $totalColumns; $i++) {
                    $col = Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($col)->setWidth(15);
                }

                // Refuerzo global de wrap para evitar texto encimado.
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
                continue;
            }
            $row[] = '';
        }

        return $row;
    }

    /**
     * @return array<int,string>
     */
    protected function buildVariableHeaderRow(): array
    {
        $row = array_fill(0, count($this->fixedHeaders), '');

        foreach ($this->dynamicColumns as $col) {
            $row[] = $col['variable'];
        }

        return $row;
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
}
