<?php
declare(strict_types=1);

class CsvExporter
{
    public function exportToBrowser(array $filters): void
    {
        $rows = $this->fetchData($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="dre_export_' . date('Ymd') . '.csv"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'empresa', 'cnpj', 'unidade_codigo', 'unidade_nome',
            'ano', 'mes', 'linha', 'nivel', 'codigo', 'descricao',
            'movimento', 'tipo',
        ], ';', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['company_name'],
                $row['cnpj'],
                $row['unit_code'],
                $row['unit_name'],
                $row['year'],
                $row['month'],
                $row['line_number'],
                $row['indentation_level'],
                $row['account_code'],
                $row['account_description'],
                number_format((float)$row['movement_value'], 2, ',', '.'),
                $row['movement_type'] ?: '',
            ], ';', '"', '\\');
        }

        fclose($out);
        exit;
    }

    private function fetchData(array $filters): array
    {
        $where = ["i.status = 'confirmed'"];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'i.company_id = ?';
            $params[] = (int)$filters['company_id'];
        }
        if (!empty($filters['unit_id'])) {
            $where[] = 'i.business_unit_id = ?';
            $params[] = (int)$filters['unit_id'];
        } elseif (!empty($filters['group_id'])) {
            $where[] = 'i.business_unit_id IN (
                SELECT business_unit_id FROM unit_group_items WHERE unit_group_id = ?
            )';
            $params[] = (int)$filters['group_id'];
        }
        if (!empty($filters['year'])) {
            $where[] = 'i.year = ?';
            $params[] = (int)$filters['year'];
        }
        if (!empty($filters['month_start'])) {
            $where[] = 'i.month >= ?';
            $params[] = (int)$filters['month_start'];
        }
        if (!empty($filters['month_end'])) {
            $where[] = 'i.month <= ?';
            $params[] = (int)$filters['month_end'];
        }

        $stmt = db()->prepare(
            'SELECT c.name AS company_name, c.cnpj,
                    bu.code AS unit_code, bu.name AS unit_name,
                    i.year, i.month,
                    tbr.line_number, tbr.indentation_level,
                    tbr.account_code, tbr.account_description,
                    tbr.movement_value, tbr.movement_type
             FROM trial_balance_rows tbr
             JOIN imports i ON i.id = tbr.import_id
             JOIN companies c ON c.id = i.company_id
             JOIN business_units bu ON bu.id = i.business_unit_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY i.year, i.month, bu.code, tbr.line_number'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
