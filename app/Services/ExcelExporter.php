<?php
declare(strict_types=1);

class ExcelExporter
{
    public function exportDreToBrowser(array $report): void
    {
        $year = (int)($report['year'] ?? date('Y'));
        $previousYear = (int)($report['previousYear'] ?? ($year - 1));
        $monthStart = (int)($report['monthStart'] ?? 1);
        $monthEnd = (int)($report['monthEnd'] ?? 12);
        $months = $report['months'] ?? [];
        $rows = $report['matrixRows'] ?? [];

        $filename = sprintf(
            'dre_%d_%02d_%02d_%s.xls',
            $year,
            $monthStart,
            $monthEnd,
            date('Ymd_His')
        );

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        echo "\xEF\xBB\xBF";
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        echo '<style>';
        echo 'body{font-family:Segoe UI,Arial,sans-serif;font-size:11px;color:#212529;}';
        echo 'table{border-collapse:collapse;}';
        echo 'th{background:#f8fafc;color:#64748b;border:1px solid #e2e8f0;font-weight:500;text-align:right;white-space:nowrap;padding:6px 4px;}';
        echo 'th.code,th.desc{background:#e8f0f8;text-align:left;}';
        echo 'td{border:1px solid #f1f5f9;white-space:nowrap;padding:5px 4px;vertical-align:middle;}';
        echo 'td.code{background:#f3f7fb;color:#64748b;mso-number-format:"\@";}';
        echo 'td.desc{background:#f3f7fb;color:#1e293b;mso-number-format:"\@";}';
        echo 'td.money{text-align:right;mso-number-format:"\@";}';
        echo '.positive{color:#15803d;font-weight:700;}';
        echo '.negative{color:#b65f4a;font-weight:500;}';
        echo '.zero{color:#cbd5e1;}';
        echo '.percent{color:#235189;font-size:9px;font-weight:700;}';
        echo '.section td{background:#e8f4fd;color:#1e3a5f;font-weight:700;text-transform:uppercase;}';
        echo '.section td.code,.section td.desc{background:#d8ebf8;}';
        echo '.group td{background:#ffffff;color:#1e293b;font-weight:700;text-transform:uppercase;}';
        echo '.group td.code,.group td.desc{background:#eef4fa;}';
        echo '.account-group td{background:#ffffff;color:#475569;font-weight:500;}';
        echo '.account-group td.code,.account-group td.desc{background:#f3f7fb;}';
        echo '.analytical td{background:#ffffff;color:#64748b;font-weight:400;}';
        echo '.analytical td.code,.analytical td.desc{background:#f3f7fb;}';
        echo '</style></head><body>';
        echo '<table>';
        echo '<colgroup>';
        echo '<col style="width:80px"><col style="width:360px">';
        foreach ($months as $_) {
            echo '<col style="width:116px">';
        }
        echo '<col style="width:116px"><col style="width:116px"><col style="width:116px"><col style="width:116px">';
        echo '</colgroup>';
        echo '<thead><tr>';
        echo '<th class="code">C&oacute;digo</th>';
        echo '<th class="desc">Descri&ccedil;&atilde;o</th>';

        foreach ($months as $month) {
            echo '<th>' . e((string)($month['label'] ?? '')) . '</th>';
        }

        echo '<th>Acumulado</th>';
        echo '<th>Acumulado ' . $previousYear . '</th>';
        echo '<th>Media</th>';
        echo '<th>Media ' . $previousYear . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            if (!empty($row['hide_duplicate'])) {
                continue;
            }

            $kind = $this->visualKind($row);
            $level = (int)($row['indentation_level'] ?? 0);
            $description = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', max(0, $level))
                . e((string)($row['account_description'] ?? ''));

            echo '<tr class="' . $kind . '">';
            echo '<td class="code">' . e((string)($row['account_code'] ?? '')) . '</td>';
            echo '<td class="desc">' . $description . '</td>';

            foreach ($months as $month) {
                $monthKey = (string)($month['key'] ?? '');
                $this->moneyCell(
                    (float)($row['values'][$monthKey] ?? 0.0),
                    (float)($row['percentuais'][$monthKey] ?? 0.0)
                );
            }

            $this->moneyCell(
                (float)($row['acumulado'] ?? 0.0),
                (float)($row['acumulado_percentual'] ?? 0.0),
                $this->trend((float)($row['acumulado'] ?? 0.0), (float)($row['previous_year_acumulado'] ?? 0.0))
            );
            $this->moneyCell(
                (float)($row['previous_year_acumulado'] ?? 0.0),
                (float)($row['previous_year_acumulado_percentual'] ?? 0.0)
            );
            $this->moneyCell(
                (float)($row['media'] ?? 0.0),
                (float)($row['media_percentual'] ?? 0.0),
                $this->trend((float)($row['media'] ?? 0.0), (float)($row['previous_year_media'] ?? 0.0))
            );
            $this->moneyCell(
                (float)($row['previous_year_media'] ?? 0.0),
                (float)($row['previous_year_media_percentual'] ?? 0.0)
            );

            echo '</tr>';
        }

        echo '</tbody></table></body></html>';
        exit;
    }

    private function moneyCell(float $value, float $percent, string $trend = ''): void
    {
        $class = $value < 0 ? 'negative' : ($value > 0 ? 'positive' : 'zero');
        echo '<td class="money ' . $class . '">';
        echo $this->formatSigned($value) . $trend;

        if (abs($percent) >= 0.01) {
            echo '<br><span class="percent">' . e(number_format($percent, 1, ',', '.')) . '%</span>';
        }

        echo '</td>';
    }

    private function formatSigned(float $value): string
    {
        if (abs($value) < 0.005) {
            return '-';
        }

        $formatted = e(format_brl(abs($value)));
        return $value < 0 ? '(' . $formatted . ')' : $formatted;
    }

    private function trend(float $current, float $previous): string
    {
        if (abs($current - $previous) < 0.005) {
            return ' <span class="zero">&#8211;</span>';
        }

        return $current > $previous
            ? ' <span class="positive">&#8593;</span>'
            : ' <span class="negative">&#8595;</span>';
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
}
