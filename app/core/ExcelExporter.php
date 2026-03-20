<?php

namespace App\Core;

use RuntimeException;
use ZipArchive;

class ExcelExporter
{
    public static function download(string $baseFilename, array $headers, array $rows): void
    {
        $timestamp = date('Ymd_His');

        if (self::canGenerateXlsx()) {
            $filename = $baseFilename . '_' . $timestamp . '.xlsx';
            $binary = self::buildXlsx($headers, $rows);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($binary));
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $binary;
            exit;
        }

        self::downloadHtmlTable($baseFilename . '_' . $timestamp . '.xls', $headers, $rows);
    }

    private static function canGenerateXlsx(): bool
    {
        return class_exists(ZipArchive::class);
    }

    private static function buildXlsx(array $headers, array $rows): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'symphony_xlsx_');
        if ($tmpFile === false) {
            throw new RuntimeException('Impossible de creer un fichier temporaire pour l export Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpFile);
            throw new RuntimeException('Impossible de creer l archive Excel.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
        $zip->addFromString('_rels/.rels', self::rootRelsXml());
        $zip->addFromString('xl/workbook.xml', self::workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml());
        $zip->addFromString('xl/styles.xml', self::stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($headers, $rows));

        $zip->close();

        $binary = file_get_contents($tmpFile);
        @unlink($tmpFile);

        if (!is_string($binary) || $binary === '') {
            throw new RuntimeException('Impossible de lire le fichier Excel genere.');
        }

        return $binary;
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Export" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
    }

    private static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function sheetXml(array $headers, array $rows): string
    {
        $rowXml = [];
        $rowIndex = 1;

        $rowXml[] = self::buildRowXml($rowIndex, $headers);
        foreach ($rows as $row) {
            $rowIndex++;
            $rowXml[] = self::buildRowXml($rowIndex, $row);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $rowXml) . '</sheetData>'
            . '</worksheet>';
    }

    private static function buildRowXml(int $rowIndex, array $cells): string
    {
        $cellXml = [];
        $colIndex = 1;
        foreach ($cells as $value) {
            $cellRef = self::columnLetter($colIndex) . $rowIndex;
            $cellXml[] = '<c r="' . $cellRef . '" t="inlineStr"><is><t>'
                . self::escapeXml((string) $value)
                . '</t></is></c>';
            $colIndex++;
        }

        return '<row r="' . $rowIndex . '">' . implode('', $cellXml) . '</row>';
    }

    private static function columnLetter(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = intdiv($index, 26);
        }
        return $letters;
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function downloadHtmlTable(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = [];
        $out[] = '<table border="1">';
        $out[] = '<thead><tr>' . self::htmlRow($headers, 'th') . '</tr></thead>';
        $out[] = '<tbody>';
        foreach ($rows as $row) {
            $out[] = '<tr>' . self::htmlRow($row, 'td') . '</tr>';
        }
        $out[] = '</tbody></table>';

        echo implode('', $out);
        exit;
    }

    private static function htmlRow(array $row, string $cellTag): string
    {
        $cells = [];
        foreach ($row as $value) {
            $cells[] = '<' . $cellTag . '>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</' . $cellTag . '>';
        }
        return implode('', $cells);
    }
}
