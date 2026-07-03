<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

use App\Modules\Reports\Enums\ExportFormat;
use ZipArchive;

class CenterReportExportBuilder
{
    public function build(CenterReportData $report, ExportFormat $format): string
    {
        return match ($format) {
            ExportFormat::Csv => $this->buildCsv($report),
            ExportFormat::Xlsx => $this->buildXlsx($report),
            ExportFormat::Pdf => $this->buildPdf($report),
        };
    }

    public function extension(ExportFormat $format): string
    {
        return $format->value;
    }

    public function mimeType(ExportFormat $format): string
    {
        return match ($format) {
            ExportFormat::Csv => 'text/csv; charset=UTF-8',
            ExportFormat::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ExportFormat::Pdf => 'application/pdf',
        };
    }

    private function buildCsv(CenterReportData $report): string
    {
        $handle = fopen('php://temp', 'rb+');

        if ($handle === false) {
            throw new \RuntimeException('Unable to create export stream.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            __('reports.export.file.center'),
            $report->centerName,
        ], ';');
        fputcsv($handle, [
            __('reports.export.file.period'),
            $report->periodLabel,
        ], ';');
        fputcsv($handle, [], ';');
        fputcsv($handle, [
            __('reports.columns.business_date'),
            __('reports.columns.record_count'),
            __('reports.columns.ht'),
            __('reports.columns.vat'),
            __('reports.columns.ttc'),
        ], ';');

        foreach ($report->dailyRows as $row) {
            fputcsv($handle, [
                $row->businessDate,
                $row->recordCount,
                $row->totalHt,
                $row->totalVat,
                $row->totalTtc,
            ], ';');
        }

        fputcsv($handle, [], ';');
        fputcsv($handle, [
            __('reports.export.file.totals'),
            $report->recordCount,
            $report->totalHt,
            $report->totalVat,
            $report->totalTtc,
        ], ';');

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    private function buildXlsx(CenterReportData $report): string
    {
        $rows = [
            [__('reports.export.file.center'), $report->centerName],
            [__('reports.export.file.period'), $report->periodLabel],
            [],
            [
                __('reports.columns.business_date'),
                __('reports.columns.record_count'),
                __('reports.columns.ht'),
                __('reports.columns.vat'),
                __('reports.columns.ttc'),
            ],
        ];

        foreach ($report->dailyRows as $row) {
            $rows[] = [
                $row->businessDate,
                $row->recordCount,
                $row->totalHt,
                $row->totalVat,
                $row->totalTtc,
            ];
        }

        $rows[] = [];
        $rows[] = [
            __('reports.export.file.totals'),
            $report->recordCount,
            $report->totalHt,
            $report->totalVat,
            $report->totalTtc,
        ];

        return $this->buildMinimalXlsx($rows);
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function buildMinimalXlsx(array $rows): string
    {
        $sharedStrings = [];
        $sharedIndex = [];

        $sharedStringIndex = static function (string $value) use (&$sharedStrings, &$sharedIndex): int {
            if (! array_key_exists($value, $sharedIndex)) {
                $sharedIndex[$value] = count($sharedStrings);
                $sharedStrings[] = $value;
            }

            return $sharedIndex[$value];
        };

        $sheetRows = '';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $sheetRows .= '<row r="'.$rowNumber.'">';

            if ($row === []) {
                $sheetRows .= '</row>';

                continue;
            }

            foreach (array_values($row) as $columnIndex => $cell) {
                $column = $this->columnLetter($columnIndex);
                $reference = $column.$rowNumber;

                if (is_int($cell)) {
                    $sheetRows .= '<c r="'.$reference.'"><v>'.$cell.'</v></c>';

                    continue;
                }

                $index = $sharedStringIndex((string) $cell);
                $sheetRows .= '<c r="'.$reference.'" t="s"><v>'.$index.'</v></c>';
            }

            $sheetRows .= '</row>';
        }

        $sharedItems = '';

        foreach ($sharedStrings as $sharedString) {
            $sharedItems .= '<si><t>'.htmlspecialchars($sharedString, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></si>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$sheetRows.'</sheetData>'
            .'</worksheet>';

        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">'
            .$sharedItems
            .'</sst>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>';

        $rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';

        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx-');

        if ($tempPath === false) {
            throw new \RuntimeException('Unable to create temporary XLSX file.');
        }

        $zip = new ZipArchive;

        if ($zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to open temporary XLSX archive.');
        }

        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->addFromString('_rels/.rels', $rootRelsXml);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->close();

        $contents = file_get_contents($tempPath);
        @unlink($tempPath);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read temporary XLSX file.');
        }

        return $contents;
    }

    private function buildPdf(CenterReportData $report): string
    {
        $lines = [
            __('reports.export.file.title'),
            __('reports.export.file.center').': '.$report->centerName,
            __('reports.export.file.period').': '.$report->periodLabel,
            '',
            implode(' | ', [
                __('reports.columns.business_date'),
                __('reports.columns.record_count'),
                __('reports.columns.ht'),
                __('reports.columns.vat'),
                __('reports.columns.ttc'),
            ]),
        ];

        foreach ($report->dailyRows as $row) {
            $lines[] = implode(' | ', [
                $row->businessDate,
                (string) $row->recordCount,
                $row->totalHt,
                $row->totalVat,
                $row->totalTtc,
            ]);
        }

        $lines[] = '';
        $lines[] = implode(' | ', [
            __('reports.export.file.totals'),
            (string) $report->recordCount,
            $report->totalHt,
            $report->totalVat,
            $report->totalTtc,
        ]);
        $lines[] = '';
        $lines[] = __('reports.export.file.generated_at', ['datetime' => now()->format('d/m/Y H:i')]);

        return $this->buildSimplePdf($lines);
    }

    /**
     * @param  list<string>  $lines
     */
    private function buildSimplePdf(array $lines): string
    {
        $pageWidth = 595;
        $pageHeight = 842;
        $margin = 48;
        $lineHeight = 14;
        $fontSize = 10;
        $startY = $pageHeight - $margin;

        $content = "BT\n/F1 {$fontSize} Tf\n";
        $y = $startY;

        foreach ($lines as $line) {
            $escaped = $this->pdfEscape($this->pdfText($line));
            $content .= "1 0 0 1 {$margin} {$y} Tm\n({$escaped}) Tj\n";
            $y -= $lineHeight;
        }

        $content .= 'ET';

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$pageWidth.' '.$pageHeight.'] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
        $objects[] = '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $objectNumber = $index + 1;
            $pdf .= $objectNumber." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }

        $pdf .= 'trailer'."\n";
        $pdf .= '<< /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n";
        $pdf .= 'startxref'."\n".$xrefOffset."\n";
        $pdf .= '%%EOF';

        return $pdf;
    }

    private function pdfText(string $text): string
    {
        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);

        return $converted === false ? $text : $converted;
    }

    private function pdfEscape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function columnLetter(int $index): string
    {
        $index++;
        $letters = '';

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }
}
