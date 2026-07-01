<?php
declare(strict_types=1);

class DashboardController
{
    public function index(): void
    {
        auth_check();
        $pdo = db();

        $yearsAvailable = $this->availableYears($pdo);
        $latestPeriod = $this->latestPeriod($pdo);
        $defaultYear = $latestPeriod['year'] ?? (int)date('Y');
        $fYear = (int)($_GET['year'] ?? $defaultYear);
        if (!in_array($fYear, $yearsAvailable, true)) {
            $fYear = $defaultYear;
        }

        [$defaultMonthStart, $defaultMonthEnd] = $this->detectMonthRange($pdo, $fYear);
        $fMonthStart = isset($_GET['month_start']) && $_GET['month_start'] !== '' ? (int)$_GET['month_start'] : $defaultMonthStart;
        $fMonthEnd = isset($_GET['month_end']) && $_GET['month_end'] !== '' ? (int)$_GET['month_end'] : $defaultMonthEnd;
        [$fMonthStart, $fMonthEnd] = $this->normalizeMonthRange($fMonthStart, $fMonthEnd);
        $dashboardPeriodLabel = $this->periodLabel($fYear, $fMonthStart, $fMonthEnd);

        $stmt = $pdo->prepare(
            'SELECT i.id, i.year, i.month, i.status, i.imported_at,
                    i.original_filename,
                    c.name AS company_name,
                    bu.name AS unit_name, bu.code AS unit_code,
                    u.name AS imported_by_name
             FROM imports i
             JOIN companies c ON c.id = i.company_id
             JOIN business_units bu ON bu.id = i.business_unit_id
             JOIN users u ON u.id = i.imported_by
             WHERE i.year = ?
               AND i.month >= ?
               AND i.month <= ?
             ORDER BY i.imported_at DESC
             LIMIT 10'
        );
        $stmt->execute([$fYear, $fMonthStart, $fMonthEnd]);
        $recentImports = $stmt->fetchAll();

        $totalImports   = $this->countImports($pdo, $fYear, $fMonthStart, $fMonthEnd);
        $totalConfirmed = $this->countImports($pdo, $fYear, $fMonthStart, $fMonthEnd, 'confirmed');
        $totalUnits     = (int)$pdo->query("SELECT COUNT(*) FROM business_units WHERE active=1")->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT i.year, i.month, bu.name AS unit_name
             FROM imports i
             JOIN business_units bu ON bu.id = i.business_unit_id
             WHERE i.status = 'confirmed'
               AND i.year = ?
               AND i.month >= ?
               AND i.month <= ?
             ORDER BY i.year DESC, i.month DESC
             LIMIT 1"
        );
        $stmt->execute([$fYear, $fMonthStart, $fMonthEnd]);
        $lastImport = $stmt->fetch();

        $monthlySummary = $this->monthlySummary($pdo, $fYear, $fMonthStart, $fMonthEnd);
        $accountComparison = $this->accountComparisonByUnit($pdo, $fYear, $fMonthStart, $fMonthEnd);
        $annualComparison = $this->annualComparison($pdo);
        $lastMonthSummary = !empty($monthlySummary) ? $monthlySummary[array_key_last($monthlySummary)] : null;
        $financialSummary = $this->financialSummary($monthlySummary);

        view('dashboard/index', compact(
            'recentImports', 'totalImports', 'totalConfirmed', 'totalUnits', 'lastImport',
            'monthlySummary', 'accountComparison', 'annualComparison', 'lastMonthSummary',
            'financialSummary', 'yearsAvailable', 'fYear', 'fMonthStart', 'fMonthEnd',
            'dashboardPeriodLabel'
        ));
    }
    private function monthlySummary(PDO $pdo, int $year, int $monthStart, int $monthEnd): array
    {
        $stmt = $pdo->prepare(
            "SELECT i.year, i.month,
                    tbr.account_description,
                    tbr.movement_value,
                    tbr.movement_type
             FROM trial_balance_rows tbr
             JOIN imports i ON i.id = tbr.import_id
             WHERE i.status = 'confirmed'
               AND i.year = ?
               AND i.month >= ?
               AND i.month <= ?
             ORDER BY i.year, i.month, tbr.line_number"
        );
        $stmt->execute([$year, $monthStart, $monthEnd]);
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

    private function annualComparison(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT i.id AS import_id,
                    i.year,
                    i.month,
                    tbr.account_description,
                    tbr.movement_value,
                    tbr.movement_type
             FROM trial_balance_rows tbr
             JOIN imports i ON i.id = tbr.import_id
             WHERE i.status = 'confirmed'
               AND tbr.is_analytical = 0
             ORDER BY i.year, i.month, i.id, tbr.line_number"
        );

        $targetRevenue = $this->normalizeAccountDescription('RECEITA OPERACIONAL LIQUIDA');
        $targetResult = $this->normalizeAccountDescription('RESULTADO LIQUIDO DO EXERCICIO');
        $imports = [];

        foreach ($stmt->fetchAll() as $row) {
            $importId = (int)$row['import_id'];
            if (!isset($imports[$importId])) {
                $imports[$importId] = [
                    'year' => (int)$row['year'],
                    'month' => (int)$row['month'],
                    'revenue' => 0.0,
                    'result' => 0.0,
                ];
            }

            $description = $this->normalizeAccountDescription((string)$row['account_description']);
            $movement = $this->signedMovement((float)$row['movement_value'], (string)($row['movement_type'] ?? ''));

            if ($description === $targetRevenue) {
                $imports[$importId]['revenue'] += abs($movement);
            } elseif ($description === $targetResult) {
                $imports[$importId]['result'] += $movement;
            }
        }

        $years = [];
        foreach ($imports as $import) {
            $year = (int)$import['year'];
            if (!isset($years[$year])) {
                $years[$year] = [
                    'year' => $year,
                    'revenue' => 0.0,
                    'result' => 0.0,
                    'months' => [],
                    'months_count' => 0,
                    'avg_revenue' => 0.0,
                    'avg_result' => 0.0,
                    'margin' => 0.0,
                    'result_change' => null,
                ];
            }

            $years[$year]['revenue'] += (float)$import['revenue'];
            $years[$year]['result'] += (float)$import['result'];
            $years[$year]['months'][(int)$import['month']] = true;
        }

        ksort($years);
        $rows = array_values($years);
        $previous = null;

        foreach ($rows as &$year) {
            $year['months_count'] = max(1, count($year['months']));
            unset($year['months']);

            $year['avg_revenue'] = $year['revenue'] / $year['months_count'];
            $year['avg_result'] = $year['result'] / $year['months_count'];
            $year['margin'] = $year['revenue'] > 0 ? ($year['result'] / $year['revenue']) * 100 : 0.0;
            $year['result_change'] = $previous && abs((float)$previous['result']) >= 0.005
                ? (($year['result'] - (float)$previous['result']) / abs((float)$previous['result'])) * 100
                : null;
            $previous = $year;
        }
        unset($year);

        return $rows;
    }

    private function accountComparisonByUnit(PDO $pdo, int $year, int $monthStart, int $monthEnd): array
    {
        // Usa o primeiro grupo ativo, se existir
        $firstGroup = $pdo->query(
            "SELECT ug.id, ug.name
               FROM unit_groups ug
               JOIN unit_group_items ugi ON ugi.unit_group_id = ug.id
              WHERE ug.active = 1
              GROUP BY ug.id
              ORDER BY ug.id
              LIMIT 1"
        )->fetch();

        $groupJoin  = '';
        $groupLabel = '';
        if ($firstGroup) {
            $gid = (int)$firstGroup['id'];
            $groupJoin = "JOIN unit_group_items ugi ON ugi.business_unit_id = bu.id AND ugi.unit_group_id = {$gid}";
            $groupLabel = ' - ' . $firstGroup['name'];
        }

        $stmt = $pdo->prepare(
            "SELECT i.id, i.year, i.month,
                    bu.id AS unit_id,
                    bu.code AS unit_code,
                    bu.name AS unit_name
             FROM imports i
             JOIN business_units bu ON bu.id = i.business_unit_id
             {$groupJoin}
             WHERE i.status = 'confirmed'
               AND i.year = ?
               AND i.month >= ?
               AND i.month <= ?
               AND bu.active = 1
             ORDER BY bu.code, i.month"
        );
        $stmt->execute([$year, $monthStart, $monthEnd]);
        $yearImports = $stmt->fetchAll();

        if (empty($yearImports)) {
            return ['period' => null, 'rows' => [], 'months_count' => 0];
        }

        $importIds = array_map(static fn (array $import): int => (int)$import['id'], $yearImports);
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
            'resultado' => $this->normalizeAccountDescription('RESULTADO LIQUIDO DO EXERCICIO'),
            'receita' => $this->normalizeAccountDescription('RECEITA OPERACIONAL LIQUIDA'),
            'devolucoes' => $this->normalizeAccountDescription('DEVOLUCOES DE VENDAS'),
            'custo' => $this->normalizeAccountDescription('CUSTO PRODUTOS DOS VENDIDOS'),
            'desp_operacionais' => $this->normalizeAccountDescription('DESPESAS OPERACIONAIS'),
            'desp_administrativas' => $this->normalizeAccountDescription('DESPESAS ADMINISTRATIVAS'),
        ];

        $rowsByUnit = [];
        $monthsByUnit = [];

        foreach ($yearImports as $import) {
            $unitId = (int)$import['unit_id'];
            if (!isset($rowsByUnit[$unitId])) {
                $rowsByUnit[$unitId] = [
                    'unit_id' => $unitId,
                    'unit_code' => (string)$import['unit_code'],
                    'unit_name' => (string)$import['unit_name'],
                    'resultado_acum' => 0.0,
                    'receita_acum' => 0.0,
                    'devolucoes_acum' => 0.0,
                    'custo_acum' => 0.0,
                    'desp_operacionais_acum' => 0.0,
                    'desp_administrativas_acum' => 0.0,
                    'resultado_media' => 0.0,
                    'receita_media' => 0.0,
                    'devolucoes_media' => 0.0,
                    'custo_media' => 0.0,
                    'desp_operacionais_media' => 0.0,
                    'desp_administrativas_media' => 0.0,
                    'margin' => 0.0,
                ];
                $monthsByUnit[$unitId] = [];
            }
            $monthsByUnit[$unitId][(int)$import['month']] = true;
        }

        foreach ($stmt->fetchAll() as $row) {
            $unitId = (int)$row['unit_id'];
            if (!isset($rowsByUnit[$unitId])) {
                continue;
            }

            $description = $this->normalizeAccountDescription((string)$row['account_description']);
            $movement = $this->signedMovement((float)$row['movement_value'], (string)($row['movement_type'] ?? ''));

            if ($description === $targets['resultado']) {
                $rowsByUnit[$unitId]['resultado_acum'] += $movement;
            } elseif ($description === $targets['receita']) {
                $rowsByUnit[$unitId]['receita_acum'] += abs($movement);
            } elseif ($description === $targets['devolucoes']) {
                $rowsByUnit[$unitId]['devolucoes_acum'] += abs($movement);
            } elseif ($description === $targets['custo']) {
                $rowsByUnit[$unitId]['custo_acum'] += abs($movement);
            } elseif ($description === $targets['desp_operacionais']) {
                $rowsByUnit[$unitId]['desp_operacionais_acum'] += abs($movement);
            } elseif ($description === $targets['desp_administrativas']) {
                $rowsByUnit[$unitId]['desp_administrativas_acum'] += abs($movement);
            }
        }

        $maxMonth = 0;
        foreach ($rowsByUnit as $unitId => &$unit) {
            $monthsCount = max(1, count($monthsByUnit[$unitId]));
            $maxMonth = max($maxMonth, max(array_keys($monthsByUnit[$unitId])));

            $unit['resultado_media'] = $unit['resultado_acum'] / $monthsCount;
            $unit['receita_media'] = $unit['receita_acum'] / $monthsCount;
            $unit['devolucoes_media'] = $unit['devolucoes_acum'] / $monthsCount;
            $unit['custo_media'] = $unit['custo_acum'] / $monthsCount;
            $unit['desp_operacionais_media'] = $unit['desp_operacionais_acum'] / $monthsCount;
            $unit['desp_administrativas_media'] = $unit['desp_administrativas_acum'] / $monthsCount;
            $unit['margin'] = $unit['receita_acum'] > 0 ? ($unit['resultado_acum'] / $unit['receita_acum']) * 100 : 0.0;
            $unit['months_count'] = $monthsCount;
        }
        unset($unit);

        $rows = array_values($rowsByUnit);

        usort($rows, static function (array $a, array $b): int {
            return [(float)$b['receita_acum'], (float)$b['resultado_acum']] <=> [(float)$a['receita_acum'], (float)$a['resultado_acum']];
        });

        $bestMargin = null;
        $worstResult = null;
        foreach ($rows as $row) {
            if ((float)$row['receita_acum'] > 0) {
                if ($bestMargin === null || (float)$row['margin'] > (float)$bestMargin['margin']) {
                    $bestMargin = $row;
                }
            }
            if ($worstResult === null || (float)$row['resultado_acum'] < (float)$worstResult['resultado_acum']) {
                $worstResult = $row;
            }
        }

        $totalReceita = array_sum(array_map(static fn (array $unit): float => (float)$unit['receita_acum'], $rows));
        $totalResultado = array_sum(array_map(static fn (array $unit): float => (float)$unit['resultado_acum'], $rows));

        return [
            'period' => [
                'label' => $this->periodLabel($year, $monthStart, $monthEnd),
                'year' => $year,
                'max_month' => $maxMonth,
                'group_name' => $groupLabel,
            ],
            'totals' => [
                'units' => count($rows),
                'receita' => $totalReceita,
                'resultado' => $totalResultado,
                'positive_units' => count(array_filter($rows, static fn (array $unit): bool => (float)$unit['resultado_acum'] > 0)),
                'best_margin' => $bestMargin,
                'worst_result' => $worstResult,
            ],
            'rows' => $rows,
            'months_count' => max(1, $monthEnd - $monthStart + 1),
        ];
    }

    private function availableYears(PDO $pdo): array
    {
        $years = $pdo->query(
            "SELECT DISTINCT year FROM imports WHERE status = 'confirmed' ORDER BY year DESC"
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $years ?: [(int)date('Y')]);
    }

    private function latestPeriod(PDO $pdo): ?array
    {
        $row = $pdo->query(
            "SELECT year, month
               FROM imports
              WHERE status = 'confirmed'
              ORDER BY year DESC, month DESC
              LIMIT 1"
        )->fetch();

        if (!$row) {
            return null;
        }

        return ['year' => (int)$row['year'], 'month' => (int)$row['month']];
    }

    private function detectMonthRange(PDO $pdo, int $year): array
    {
        $stmt = $pdo->prepare(
            "SELECT MIN(month) AS min_month, MAX(month) AS max_month
               FROM imports
              WHERE status = 'confirmed'
                AND year = ?"
        );
        $stmt->execute([$year]);
        $row = $stmt->fetch();

        if ($row && $row['min_month'] !== null && $row['max_month'] !== null) {
            return [(int)$row['min_month'], (int)$row['max_month']];
        }

        return [1, 12];
    }

    private function normalizeMonthRange(int $monthStart, int $monthEnd): array
    {
        $monthStart = max(1, min(12, $monthStart));
        $monthEnd = max(1, min(12, $monthEnd));

        if ($monthStart > $monthEnd) {
            [$monthStart, $monthEnd] = [$monthEnd, $monthStart];
        }

        return [$monthStart, $monthEnd];
    }

    private function periodLabel(int $year, int $monthStart, int $monthEnd): string
    {
        if ($monthStart === $monthEnd) {
            return month_short($monthStart) . "/{$year}";
        }

        return month_short($monthStart) . "/{$year} a " . month_short($monthEnd) . "/{$year}";
    }

    private function countImports(PDO $pdo, int $year, int $monthStart, int $monthEnd, string $status = ''): int
    {
        $where = 'WHERE year = ? AND month >= ? AND month <= ?';
        $params = [$year, $monthStart, $monthEnd];

        if ($status !== '') {
            $where .= ' AND status = ?';
            $params[] = $status;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM imports {$where}");
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    private function financialSummary(array $monthlySummary): array
    {
        return [
            'receita' => array_sum(array_map(static fn (array $month): float => (float)$month['receita'], $monthlySummary)),
            'custo_despesa' => array_sum(array_map(static fn (array $month): float => abs((float)$month['custo_despesa']), $monthlySummary)),
            'resultado' => array_sum(array_map(static fn (array $month): float => (float)$month['resultado'], $monthlySummary)),
        ];
    }
}
