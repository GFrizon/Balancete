<?php
declare(strict_types=1);

class DashboardController
{
    public function index(): void
    {
        auth_check();
        $pdo = db();

        // Últimas 10 importações
        $stmt = $pdo->query(
            'SELECT i.id, i.year, i.month, i.status, i.imported_at,
                    i.original_filename,
                    c.name AS company_name,
                    bu.name AS unit_name, bu.code AS unit_code,
                    u.name AS imported_by_name
             FROM imports i
             JOIN companies c ON c.id = i.company_id
             JOIN business_units bu ON bu.id = i.business_unit_id
             JOIN users u ON u.id = i.imported_by
             ORDER BY i.imported_at DESC
             LIMIT 10'
        );
        $recentImports = $stmt->fetchAll();

        // Totais rápidos
        $totalImports   = (int)$pdo->query('SELECT COUNT(*) FROM imports')->fetchColumn();
        $totalConfirmed = (int)$pdo->query("SELECT COUNT(*) FROM imports WHERE status='confirmed'")->fetchColumn();
        $totalUnits     = (int)$pdo->query("SELECT COUNT(*) FROM business_units WHERE active=1")->fetchColumn();

        // Último import confirmado
        $lastImport = $pdo->query(
            "SELECT i.year, i.month, bu.name AS unit_name
             FROM imports i
             JOIN business_units bu ON bu.id = i.business_unit_id
             WHERE i.status = 'confirmed'
             ORDER BY i.year DESC, i.month DESC
             LIMIT 1"
        )->fetch();

        // Análises financeiras
        $monthlySummary = $this->monthlySummary($pdo);
        $unitComparison = $this->unitComparison($pdo);
        $lastMonthSummary = !empty($monthlySummary) ? $monthlySummary[array_key_last($monthlySummary)] : null;

        view('dashboard/index', compact(
            'recentImports', 'totalImports', 'totalConfirmed', 'totalUnits', 'lastImport',
            'monthlySummary', 'unitComparison', 'lastMonthSummary'
        ));
    }

    private function monthlySummary(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT i.year, i.month,
                    tbr.account_description,
                    tbr.movement_value,
                    tbr.movement_type
             FROM trial_balance_rows tbr
             JOIN imports i ON i.id = tbr.import_id
             JOIN (
                SELECT year, month
                FROM imports
                WHERE status = 'confirmed'
                GROUP BY year, month
                ORDER BY year DESC, month DESC
                LIMIT 12
             ) recent_periods ON recent_periods.year = i.year
                              AND recent_periods.month = i.month
             WHERE i.status = 'confirmed'
             ORDER BY i.year DESC, i.month DESC, tbr.line_number"
        );
        $rows = $stmt->fetchAll();
        $summary = [];
        $targetRevenue = $this->normalizeAccountDescription('RECEITA OPERACIONAL BRUTA MERC.INTERNO');
        $targetResult = $this->normalizeAccountDescription('RESULTADO LIQUIDO DO EXERCICIO');

        foreach ($rows as $row) {
            $key = (int)$row['year'] . '-' . str_pad((string)(int)$row['month'], 2, '0', STR_PAD_LEFT);
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'year' => (int)$row['year'],
                    'month' => (int)$row['month'],
                    'receita' => 0.0,
                    'custo_despesa' => 0.0,
                    'resultado' => 0.0,
                ];
            }

            $movement = (float)$row['movement_value'];
            $type = (string)($row['movement_type'] ?? '');
            $description = $this->normalizeAccountDescription((string)$row['account_description']);
            $signedMovement = $this->signedMovement($movement, $type);

            if ($description === $targetRevenue) {
                $summary[$key]['receita'] += abs($signedMovement);
            }

            if ($description === $targetResult) {
                $summary[$key]['resultado'] += $signedMovement;
            }
        }

        foreach ($summary as &$period) {
            $period['custo_despesa'] = (float)$period['receita'] - (float)$period['resultado'];
        }
        unset($period);

        usort($summary, static function (array $a, array $b): int {
            return [$a['year'], $a['month']] <=> [$b['year'], $b['month']];
        });

        return $summary;
    }

    private function normalizeAccountDescription(string $description): string
    {
        $description = mb_strtoupper(trim($description));
        $description = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $description) ?: $description;
        $description = preg_replace('/[^A-Z0-9]+/', ' ', $description) ?? $description;
        return trim(preg_replace('/\s+/', ' ', $description) ?? $description);
    }

    private function signedMovement(float $value, string $type): float
    {
        if ($type === 'DB') {
            return -abs($value);
        }
        if ($type === 'CR') {
            return abs($value);
        }
        return $value;
    }

    private function unitComparison(PDO $pdo): array
    {
        $latestImports = $pdo->query(
            "SELECT i.id, i.year, i.month,
                    bu.id AS unit_id,
                    bu.code AS unit_code,
                    bu.name AS unit_name
             FROM imports i
             JOIN business_units bu ON bu.id = i.business_unit_id
             JOIN (
                SELECT business_unit_id, MAX((year * 100) + month) AS period_key
                FROM imports
                WHERE status = 'confirmed'
                GROUP BY business_unit_id
             ) latest ON latest.business_unit_id = i.business_unit_id
                     AND latest.period_key = ((i.year * 100) + i.month)
             WHERE i.status = 'confirmed'
               AND bu.active = 1
               AND i.id = (
                    SELECT MAX(i2.id)
                    FROM imports i2
                    WHERE i2.status = 'confirmed'
                      AND i2.business_unit_id = i.business_unit_id
                      AND i2.year = i.year
                      AND i2.month = i.month
               )
             ORDER BY bu.code"
        )->fetchAll();

        if (empty($latestImports)) {
            return ['period' => null, 'rows' => []];
        }

        $importIds = array_map(static fn (array $import): int => (int)$import['id'], $latestImports);
        $placeholders = implode(',', array_fill(0, count($importIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT bu.id AS unit_id,
                    bu.code AS unit_code,
                    bu.name AS unit_name,
                    i.year,
                    i.month,
                    tbr.account_description,
                    tbr.movement_value,
                    tbr.movement_type
             FROM trial_balance_rows tbr
             JOIN imports i ON i.id = tbr.import_id
             JOIN business_units bu ON bu.id = i.business_unit_id
             WHERE i.id IN ({$placeholders})
               AND tbr.is_analytical = 0
             ORDER BY bu.code, tbr.line_number"
        );
        $stmt->execute($importIds);

        $targets = [
            'revenue' => $this->normalizeAccountDescription('RECEITA OPERACIONAL LIQUIDA'),
            'cost' => $this->normalizeAccountDescription('CUSTO DAS VENDAS'),
            'result' => $this->normalizeAccountDescription('RESULTADO LIQUIDO DO EXERCICIO'),
        ];
        $rowsByUnit = [];

        foreach ($latestImports as $import) {
            $unitId = (int)$import['unit_id'];
            $unitCode = (string)$import['unit_code'];
            $rowsByUnit[$unitId] = [
                'unit_id' => $unitId,
                'unit_code' => $unitCode,
                'unit_name' => (string)$import['unit_name'],
                'year' => (int)$import['year'],
                'month' => (int)$import['month'],
                'period_label' => month_short((int)$import['month']) . '/' . (int)$import['year'],
                'revenue' => 0.0,
                'cost' => 0.0,
                'result' => 0.0,
                'margin' => 0.0,
            ];
        }

        foreach ($stmt->fetchAll() as $row) {
            $unitId = (int)$row['unit_id'];
            $unitCode = (string)$row['unit_code'];
            if (!isset($rowsByUnit[$unitId])) {
                $rowsByUnit[$unitId] = [
                    'unit_id' => $unitId,
                    'unit_code' => $unitCode,
                    'unit_name' => (string)$row['unit_name'],
                    'year' => (int)$row['year'],
                    'month' => (int)$row['month'],
                    'period_label' => month_short((int)$row['month']) . '/' . (int)$row['year'],
                    'revenue' => 0.0,
                    'cost' => 0.0,
                    'result' => 0.0,
                    'margin' => 0.0,
                ];
            }

            $description = $this->normalizeAccountDescription((string)$row['account_description']);
            $movement = $this->signedMovement((float)$row['movement_value'], (string)($row['movement_type'] ?? ''));

            if ($description === $targets['revenue']) {
                $rowsByUnit[$unitId]['revenue'] += abs($movement);
            } elseif ($description === $targets['cost']) {
                $rowsByUnit[$unitId]['cost'] += abs($movement);
            } elseif ($description === $targets['result']) {
                $rowsByUnit[$unitId]['result'] += $movement;
            }
        }

        foreach ($rowsByUnit as &$unit) {
            $unit['margin'] = $unit['revenue'] > 0 ? ($unit['result'] / $unit['revenue']) * 100 : 0.0;
        }
        unset($unit);

        $rows = array_values($rowsByUnit);

        usort($rows, static function (array $a, array $b): int {
            return [$a['unit_code'], $a['unit_name']] <=> [$b['unit_code'], $b['unit_name']];
        });

        return [
            'period' => [
                'label' => 'Último balancete por unidade',
            ],
            'rows' => $rows,
        ];
    }
}
