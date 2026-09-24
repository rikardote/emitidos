<?php
declare(strict_types=1);

class SimpleXlsx
{
    private array $sheets = [];

    /**
     * Agrega una hoja con su nombre y filas.
     * $rows es un array de arrays: [['Cuenta', 'Cheque', 'Monto'], ...]
     */
    public function addSheet(string $name, array $rows): void
    {
        $this->sheets[] = [
            'name' => $name,
            'rows' => $rows
        ];
    }

    /**
     * Genera el archivo XLSX y lo guarda en $filePath.
     */
    public function save(string $filePath): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFromString('_rels/.rels', $this->buildRootRels());
        $zip->addFromString('[Content_Types].xml', $this->buildContentTypes());
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRels());
        $zip->addFromString('xl/styles.xml', $this->buildStyles());

        foreach ($this->sheets as $index => $sheet) {
            $sheetNum = $index + 1;
            $zip->addFromString("xl/worksheets/sheet{$sheetNum}.xml", $this->buildWorksheet($sheet['rows']));
        }

        $zip->close();
        return true;
    }

    /**
     * Envía el archivo XLSX como descarga HTTP.
     */
    public function download(string $filename = 'reporte.xlsx'): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $this->save($tempFile);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        readfile($tempFile);
        @unlink($tempFile);
        exit;
    }

    private function buildRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    private function buildContentTypes(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

        foreach ($this->sheets as $index => $sheet) {
            $sheetNum = $index + 1;
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $sheetNum . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        $xml .= '</Types>';
        return $xml;
    }

    private function buildWorkbook(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets>';

        foreach ($this->sheets as $index => $sheet) {
            $sheetNum = $index + 1;
            $escapedName = htmlspecialchars($sheet['name'], ENT_XML1, 'UTF-8');
            $xml .= '<sheet name="' . $escapedName . '" sheetId="' . $sheetNum . '" r:id="rId' . $sheetNum . '"/>';
        }

        $xml .= '</sheets></workbook>';
        return $xml;
    }

    private function buildWorkbookRels(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach ($this->sheets as $index => $sheet) {
            $sheetNum = $index + 1;
            $xml .= '<Relationship Id="rId' . $sheetNum . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNum . '.xml"/>';
        }

        $stylesId = count($this->sheets) + 1;
        $xml .= '<Relationship Id="rId' . $stylesId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    private function buildStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<numFmts count="1">' .
            '  <numFmt numFmtId="164" formatCode="#,##0.00"/>' .
            '</numFmts>' .
            '<fonts count="2">' .
            '  <font><name val="Calibri"/><sz val="11"/></font>' .
            '  <font><b/><name val="Calibri"/><sz val="11"/></font>' .
            '</fonts>' .
            '<fills count="2">' .
            '  <fill><patternFill patternType="none"/></fill>' .
            '  <fill><patternFill patternType="gray125"/></fill>' .
            '</fills>' .
            '<borders count="1">' .
            '  <border><left/><right/><top/><bottom/><diagonal/></border>' .
            '</borders>' .
            '<cellXfs count="3">' .
            '  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . // 0: Normal
            '  <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' . // 1: Header Bold
            '  <xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' . // 2: Decimal currency
            '</cellXfs>' .
            '</styleSheet>';
    }

    private function buildWorksheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<sheetData>';

        $rowIdx = 1;
        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowIdx . '">';
            $colIdx = 0;
            $isHeader = ($rowIdx === 1);

            foreach ($row as $cell) {
                $cellRef = $this->colToLetter($colIdx) . $rowIdx;

                if ($isHeader) {
                    $xml .= '<c r="' . $cellRef . '" t="inlineStr" s="1"><is><t>' . htmlspecialchars((string)$cell, ENT_XML1, 'UTF-8') . '</t></is></c>';
                } elseif (is_float($cell)) {
                    // Decimal con formato monetario/decimal
                    $xml .= '<c r="' . $cellRef . '" s="2"><v>' . $cell . '</v></c>';
                } elseif (is_int($cell)) {
                    // Entero
                    $xml .= '<c r="' . $cellRef . '"><v>' . $cell . '</v></c>';
                } else {
                    // Texto / string (preserva formato exacto y ceros a la izquierda)
                    $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . htmlspecialchars((string)$cell, ENT_XML1, 'UTF-8') . '</t></is></c>';
                }

                $colIdx++;
            }
            $xml .= '</row>';
            $rowIdx++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function colToLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex >= 0) {
            $letter = chr($colIndex % 26 + 65) . $letter;
            $colIndex = intval($colIndex / 26) - 1;
        }
        return $letter;
    }
}
