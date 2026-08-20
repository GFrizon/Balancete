<?php
declare(strict_types=1);

class ExcelExporter
{
    private const STYLE_HEADER = 1;
    private const STYLE_HEADER_LEFT = 2;
    private const STYLE_SECTION = 3;
    private const STYLE_SECTION_TEXT = 4;
    private const STYLE_GROUP = 5;
    private const STYLE_GROUP_TEXT = 6;
    private const STYLE_ACCOUNT_GROUP = 7;
    private const STYLE_ACCOUNT_GROUP_TEXT = 8;
    private const STYLE_ANALYTICAL = 9;
    private const STYLE_ANALYTICAL_TEXT = 10;
    private const STYLE_POSITIVE = 11;
    private const STYLE_NEGATIVE = 12;
    private const STYLE_ZERO = 13;

    public function exportDreToBrowser(array $report): void
    {
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            die('Extensao PHP ZipArchive nao disponivel para gerar XLSX.');
        }

        $year = (int)($report['year'] ?? date('Y'));
        $previousYear = (int)($report['previousYear'] ?? ($year - 1));
        $monthStart = (int)($report['monthStart'] ?? 1);
        $monthEnd = (int)($report['monthEnd'] ?? 12);
        $months = $report['months'] ?? [];
        $rows = $report['matrixRows'] ?? [];

        $filename = sprintf('dre_%d_%02d_%02d_%s.xlsx', $year, $monthStart, $monthEnd, date('Ymd_His'));
        $path = tempnam(sys_get_temp_dir(), 'dre_xlsx_');

        if ($path === false) {
            http_response_code(500);
            die('Nao foi possivel preparar o arquivo XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            http_response_code(500);
            die('Nao foi possivel criar o arquivo XLSX.');
        }

        $sheetRows = $this->sheetRows($months, $rows, $previousYear);
        $lastColumn = $this->columnName(count($sheetRows[0] ?? []));
        $lastRow = count($sheetRows);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($sheetRows, $lastColumn, $lastRow));
        $zip->addFromString('docProps/core.xml', $this->coreXml());
        $zip->addFromString('docProps/app.xml', $this->appXml());
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache');

        readfile($path);
        @unlink($path);
        exit;
    }

    private function sheetRows(array $months, array $rows, int $previousYear): array
    {
        $sheetRows = [[
            $this->cell('Codigo', self::STYLE_HEADER_LEFT),
            $this->cell('Descricao', self::STYLE_HEADER_LEFT),
        ]];

        foreach ($months as $month) {
            $sheetRows[0][] = $this->cell((string)($month['label'] ?? ''), self::STYLE_HEADER);
        }

        $sheetRows[0][] = $this->cell('Acumulado', self::STYLE_HEADER);
        $sheetRows[0][] = $this->cell('Acumulado ' . $previousYear, self::STYLE_HEADER);
        $sheetRows[0][] = $this->cell('Media', self::STYLE_HEADER);
        $sheetRows[0][] = $this->cell('Media ' . $previousYear, self::STYLE_HEADER);

        foreach ($rows as $row) {
            if (!empty($row['hide_duplicate'])) {
                continue;
            }

            $kind = $this->visualKind($row);
            $level = (int)($row['indentation_level'] ?? 0);
            $line = [
                $this->cell((string)($row['account_code'] ?? ''), $this->textStyle($kind)),
                $this->cell(str_repeat('    ', max(0, $level)) . (string)($row['account_description'] ?? ''), $this->textStyle($kind)),
            ];

            foreach ($months as $month) {
                $monthKey = (string)($month['key'] ?? '');
                $line[] = $this->moneyCell(
                    (float)($row['values'][$monthKey] ?? 0.0),
                    (float)($row['percentuais'][$monthKey] ?? 0.0),
                    $kind
                );
            }

            $line[] = $this->moneyCell(
                (float)($row['acumulado'] ?? 0.0),
                (float)($row['acumulado_percentual'] ?? 0.0),
                $kind,
                $this->trend((float)($row['acumulado'] ?? 0.0), (float)($row['previous_year_acumulado'] ?? 0.0))
            );
            $line[] = $this->moneyCell(
                (float)($row['previous_year_acumulado'] ?? 0.0),
                (float)($row['previous_year_acumulado_percentual'] ?? 0.0),
                $kind
            );
            $line[] = $this->moneyCell(
                (float)($row['media'] ?? 0.0),
                (float)($row['media_percentual'] ?? 0.0),
                $kind,
                $this->trend((float)($row['media'] ?? 0.0), (float)($row['previous_year_media'] ?? 0.0))
            );
            $line[] = $this->moneyCell(
                (float)($row['previous_year_media'] ?? 0.0),
                (float)($row['previous_year_media_percentual'] ?? 0.0),
                $kind
            );

            $sheetRows[] = $line;
        }

        return $sheetRows;
    }

    private function worksheetXml(array $rows, string $lastColumn, int $lastRow): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane xSplit="2" ySplit="1" topLeftCell="C2" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>'
            . '<cols><col min="1" max="1" width="12" customWidth="1"/><col min="2" max="2" width="48" customWidth="1"/><col min="3" max="16384" width="16" customWidth="1"/></cols>'
            . '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $height = $number === 1 ? 20 : 30;
            $xml .= '<row r="' . $number . '" ht="' . $height . '" customHeight="1">';

            foreach ($row as $columnIndex => $cell) {
                $ref = $this->columnName($columnIndex + 1) . $number;
                $xml .= '<c r="' . $ref . '" s="' . (int)$cell['style'] . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $this->xml((string)$cell['value'])
                    . '</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData>';

        if ($lastRow > 1 && $lastColumn !== '') {
            $xml .= '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>';
        }

        return $xml . '</worksheet>';
    }

    private function cell(string $value, int $style): array
    {
        return ['value' => $value, 'style' => $style];
    }

    private function moneyCell(float $value, float $percent, string $kind, string $trend = ''): array
    {
        $text = $this->formatSigned($value) . $trend;

        if (abs($percent) >= 0.01) {
            $text .= "\n" . number_format($percent, 2, ',', '.') . '%';
        }

        return $this->cell($text, $this->moneyStyle($value, $kind));
    }

    private function formatSigned(float $value): string
    {
        if (abs($value) < 0.005) {
            return '-';
        }

        $formatted = format_brl(abs($value));
        return $value < 0 ? '(' . $formatted . ')' : $formatted;
    }

    private function trend(float $current, float $previous): string
    {
        if (abs($current - $previous) < 0.005) {
            return ' -';
        }

        return $current > $previous ? ' ^' : ' v';
    }

    private function textStyle(string $kind): int
    {
        return match ($kind) {
            'section' => self::STYLE_SECTION_TEXT,
            'group' => self::STYLE_GROUP_TEXT,
            'account-group' => self::STYLE_ACCOUNT_GROUP_TEXT,
            default => self::STYLE_ANALYTICAL_TEXT,
        };
    }

    private function moneyStyle(float $value, string $kind): int
    {
        if ($kind === 'section') {
            return self::STYLE_SECTION;
        }

        if ($kind === 'group') {
            return self::STYLE_GROUP;
        }

        if ($kind === 'account-group') {
            return self::STYLE_ACCOUNT_GROUP;
        }

        if ($value < 0) {
            return self::STYLE_NEGATIVE;
        }

        if ($value > 0) {
            return self::STYLE_POSITIVE;
        }

        return self::STYLE_ZERO;
    }

    private function visualKind(array $row): string
    {
        $indent = (int)($row['indentation_level'] ?? 0);
        $hasChildren = !empty($row['has_children']);
        $rowKind = !empty($row['is_section']) ? 'section' : ($hasChildren ? 'group' : 'item');

        if ($rowKind === 'section') {
            return 'section';
        }

        if ($rowKind === 'group' && $indent >= 3) {
            return 'account-group';
        }

        if ($rowKind === 'item' && !empty($row['is_analytical'])) {
            return 'analytical';
        }

        return $rowKind === 'group' ? 'group' : 'analytical';
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="DRE" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="8">'
            . '<font><sz val="9"/><color rgb="FF212529"/><name val="Segoe UI"/></font>'
            . '<font><sz val="9"/><color rgb="FF64748B"/><name val="Segoe UI"/></font>'
            . '<font><b/><sz val="9"/><color rgb="FF1E3A5F"/><name val="Segoe UI"/></font>'
            . '<font><b/><sz val="9"/><color rgb="FF1E293B"/><name val="Segoe UI"/></font>'
            . '<font><sz val="9"/><color rgb="FF475569"/><name val="Segoe UI"/></font>'
            . '<font><sz val="9"/><color rgb="FF64748B"/><name val="Segoe UI"/></font>'
            . '<font><b/><sz val="9"/><color rgb="FF15803D"/><name val="Segoe UI"/></font>'
            . '<font><sz val="9"/><color rgb="FFB65F4A"/><name val="Segoe UI"/></font>'
            . '</fonts>'
            . '<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8F0F8"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8F4FD"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F7FB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFF1F5F9"/></left><right style="thin"><color rgb="FFF1F5F9"/></right><top style="thin"><color rgb="FFF1F5F9"/></top><bottom style="thin"><color rgb="FFF1F5F9"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="14">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . $this->xf(1, 2, 1, 'right') . $this->xf(1, 3, 1, 'left')
            . $this->xf(2, 4, 1, 'right') . $this->xf(2, 4, 1, 'left')
            . $this->xf(3, 0, 1, 'right') . $this->xf(3, 5, 1, 'left')
            . $this->xf(4, 0, 1, 'right') . $this->xf(4, 5, 1, 'left')
            . $this->xf(5, 0, 1, 'right') . $this->xf(5, 5, 1, 'left')
            . $this->xf(6, 0, 1, 'right') . $this->xf(7, 0, 1, 'right')
            . $this->xf(1, 0, 1, 'right')
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function xf(int $fontId, int $fillId, int $borderId, string $horizontal): string
    {
        return '<xf numFmtId="0" fontId="' . $fontId . '" fillId="' . $fillId . '" borderId="' . $borderId . '" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            . '<alignment horizontal="' . $horizontal . '" vertical="center" wrapText="1"/></xf>';
    }

    private function coreXml(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>Balancete DRE</dc:creator><cp:lastModifiedBy>Balancete DRE</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"'
            . ' xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Balancete DRE</Application></Properties>';
    }
}
