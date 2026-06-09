<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportXlsxService
{
    /**
     * Exporta dados para uma planilha XLSX de forma eficiente.
     *
     * @param string $sheetTitle Título da aba
     * @param array $headers Array de cabeçalhos
     * @param EloquentBuilder|QueryBuilder $query Query do Laravel
     * @param callable $rowCallback Função que recebe o registro e retorna o array de valores correspondente
     * @param array $columnFormats Mapeamento de formatos por coluna (1-based index ou letra da coluna, ex: ['D' => 'currency', 'E' => 'date'])
     * @param string $filename Nome do arquivo a ser gerado
     * @return StreamedResponse
     */
    public function export(
        string $sheetTitle,
        array $headers,
        $query,
        callable $rowCallback,
        array $columnFormats = [],
        string $filename = 'export.xlsx'
    ): StreamedResponse {
        // Para exportações de grande volume, evitamos estouro de tempo limite
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        return response()->streamDownload(function () use ($sheetTitle, $headers, $query, $rowCallback, $columnFormats) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($sheetTitle, 0, 31)); // Limite de 31 caracteres para título de aba no Excel

            // Escreve cabeçalhos
            $colIndex = 1;
            foreach ($headers as $header) {
                $sheet->setCellValue([$colIndex, 1], $header);
                $colIndex++;
            }

            // Congela a primeira linha (cabeçalho)
            $sheet->freezePane('A2');

            // Escreve os dados usando lazy chunks
            $rowIndex = 2;
            $query->lazy(1000)->each(function ($row) use ($sheet, $rowCallback, &$rowIndex) {
                $rowData = $rowCallback($row);
                $colIndex = 1;
                foreach ($rowData as $val) {
                    $sheet->setCellValue([$colIndex, $rowIndex], $val);
                    $colIndex++;
                }
                $rowIndex++;

                // Chamar coletor de lixo do PHP a cada 1000 linhas
                if ($rowIndex % 1000 === 0) {
                    gc_collect_cycles();
                }
            });

            $totalRows = $rowIndex - 1;
            $totalCols = count($headers);

            // Aplica os formatos de coluna
            for ($c = 1; $c <= $totalCols; $c++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                
                // Formato padrão para auto-fit
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);

                if (isset($columnFormats[$colLetter])) {
                    $formatCode = null;
                    if ($columnFormats[$colLetter] === 'currency') {
                        // Formato de moeda BRL amigável
                        $formatCode = 'R$ #,##0.00';
                    } elseif ($columnFormats[$colLetter] === 'date') {
                        // Formato de data brasileira
                        $formatCode = 'dd/mm/yyyy';
                    } elseif ($columnFormats[$colLetter] === 'integer') {
                        $formatCode = '#,##0';
                    }

                    if ($formatCode && $totalRows >= 2) {
                        $sheet->getStyle("{$colLetter}2:{$colLetter}{$totalRows}")
                            ->getNumberFormat()
                            ->setFormatCode($formatCode);
                    }
                }
            }

            // Aplica auto-filtro em todas as colunas caso existam linhas de dados
            if ($totalRows >= 2) {
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
                $sheet->setAutoFilter("A1:{$lastColLetter}{$totalRows}");
            }

            // Escreve para a saída direta
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            
            // Libera memória: disconnectCells() pertence à Worksheet, não ao Spreadsheet
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $worksheet->disconnectCells();
            }
            unset($spreadsheet);
            gc_collect_cycles();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
