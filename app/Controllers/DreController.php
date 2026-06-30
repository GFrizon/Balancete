<?php
declare(strict_types=1);

class DreController
{
    public function index(): void
    {
        auth_check();
        $pdo = db();

        $fCompany    = (int)($_GET['company_id'] ?? 0);
        $fUnit       = (int)($_GET['unit_id'] ?? 0);
        $fYear       = (int)($_GET['year'] ?? date('Y'));

        $monthStartSet = isset($_GET['month_start']) && $_GET['month_start'] !== '';
        $monthEndSet   = isset($_GET['month_end'])   && $_GET['month_end']   !== '';
        $fMonthStart   = $monthStartSet ? (int)$_GET['month_start'] : 0;
        $fMonthEnd     = $monthEndSet   ? (int)$_GET['month_end']   : 0;

        $companies = $pdo->query('SELECT id, name FROM companies WHERE active=1 ORDER BY name')->fetchAll();
        $units     = $pdo->query('SELECT id, name, code, company_id FROM business_units WHERE active=1 ORDER BY code')->fetchAll();

        $yearsAvailable = $pdo->query(
            "SELECT DISTINCT year FROM imports WHERE status='confirmed' ORDER BY year DESC"
        )->fetchAll(PDO::FETCH_COLUMN);
        if (empty($yearsAvailable)) {
            $yearsAvailable = [date('Y')];
        }

        // Se mês não foi explicitamente enviado, detectar range disponível
        if (!$fMonthStart || !$fMonthEnd) {
            [$defaultStart, $defaultEnd] = $this->detectMonthRange($fYear, $fCompany, $fUnit);
            if (!$fMonthStart) {
                $fMonthStart = $defaultStart;
            }
            if (!$fMonthEnd) {
                $fMonthEnd = $defaultEnd;
            }
        }

        $importIds = $this->resolveImportIds($fCompany, $fUnit, $fYear, $fMonthStart, $fMonthEnd);
        $imports = $this->getImportMeta($importIds);
        $treeRows = (new BalanceteTree())->rowsForImports($importIds);
        $months = $this->selectedMonths($fYear, $fMonthStart, $fMonthEnd);
        $matrixRows = $this->buildMatrix($treeRows, $months);

        view('dre/index', compact(
            'companies', 'units', 'yearsAvailable',
            'fCompany', 'fUnit', 'fYear', 'fMonthStart', 'fMonthEnd',
            'imports', 'months', 'matrixRows'
        ));
    }

    public function details(): void
    {
        auth_check();

        $rowId = (int)($_GET['row_id'] ?? 0);
        if (!$rowId) {
            json_response(['error' => 'Linha inválida.'], 400);
        }

        $stmt = db()->prepare(
            'SELECT account_code, account_description, movement_value, movement_type,
                    indentation_level, is_analytical
             FROM trial_balance_rows
             WHERE id = ?'
        );
        $stmt->execute([$rowId]);
        $row = $stmt->fetch();

        if (!$row) {
            json_response(['error' => 'Linha não encontrada.'], 404);
        }

        json_response($row);
    }

    public function export(): void
    {
        auth_check();

        $filters = [
            'company_id'  => (int)($_GET['company_id'] ?? 0),
            'unit_id'     => (int)($_GET['unit_id'] ?? 0),
            'year'        => (int)($_GET['year'] ?? 0),
            'month_start' => (int)($_GET['month_start'] ?? 0),
            'month_end'   => (int)($_GET['month_end'] ?? 0),
        ];

        (new CsvExporter())->exportToBrowser($filters);
    }

    private function resolveImportIds(int $companyId, int $unitId, int $year, int $monthStart, int $monthEnd): array
    {
        $where = ["i.status = 'confirmed'", 'i.year = ?', 'i.month >= ?', 'i.month <= ?'];
        $params = [$year, $monthStart, $monthEnd];

        if ($unitId) {
            $where[] = 'i.business_unit_id = ?';
            $params[] = $unitId;
        } elseif ($companyId) {
            $where[] = 'i.company_id = ?';
            $params[] = $companyId;
        }

        $stmt = db()->prepare('SELECT i.id FROM imports i WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function getImportMeta(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT i.id, i.year, i.month, c.name AS company_name,
                    bu.code AS unit_code, bu.name AS unit_name
             FROM imports i
             JOIN companies c ON c.id = i.company_id
             JOIN business_units bu ON bu.id = i.business_unit_id
             WHERE i.id IN ({$placeholders})
             ORDER BY i.year, i.month, bu.code"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    private function selectedMonths(int $year, int $monthStart, int $monthEnd): array
    {
        $months = [];
        $monthStart = max(1, min(12, $monthStart));
        $monthEnd = max(1, min(12, $monthEnd));

        if ($monthStart > $monthEnd) {
            [$monthStart, $monthEnd] = [$monthEnd, $monthStart];
        }

        for ($month = $monthStart; $month <= $monthEnd; $month++) {
            $key = sprintf('%04d-%02d', $year, $month);
            $months[] = [
                'key' => $key,
                'year' => $year,
                'month' => $month,
                'label' => month_short($month) . '/' . substr((string)$year, -2),
            ];
        }

        return $months;
    }

    private function buildMatrix(array $treeRows, array $months): array
    {
        $monthKeys = array_column($months, 'key');
        $matrix = [];

        foreach ($treeRows as $row) {
            $key = $this->rowKey($row);
            $monthKey = sprintf('%04d-%02d', (int)$row['year'], (int)$row['month']);

            if (!isset($matrix[$key])) {
                $matrix[$key] = [
                    'account_code' => $row['account_code'],
                    'account_description' => $row['account_description'],
                    'indentation_level' => (int)$row['indentation_level'],
                    'is_analytical' => (int)$row['is_analytical'],
                    'has_children' => !empty($row['has_children']),
                    'line_number' => (int)$row['line_number'],
                    'sort_line_number' => (float)$row['line_number'],
                    'sort_year' => (int)$row['year'],
                    'sort_month' => (int)$row['month'],
                    'values' => array_fill_keys($monthKeys, 0.0),
                    'types' => array_fill_keys($monthKeys, ''),
                    'debit_total' => 0.0,
                    'credit_total' => 0.0,
                    'movimento' => 0.0,
                    'acumulado' => 0.0,
                    'media' => 0.0,
                    'count_nonzero' => 0,
                ];
            } else {
                $currentPeriod = [(int)$matrix[$key]['sort_year'], (int)$matrix[$key]['sort_month']];
                $rowPeriod = [(int)$row['year'], (int)$row['month']];
                if ($rowPeriod > $currentPeriod || ($rowPeriod === $currentPeriod && (int)$row['line_number'] < (int)$matrix[$key]['line_number'])) {
                    $matrix[$key]['line_number'] = (int)$row['line_number'];
                    $matrix[$key]['sort_line_number'] = (float)$row['line_number'];
                    $matrix[$key]['sort_year'] = (int)$row['year'];
                    $matrix[$key]['sort_month'] = (int)$row['month'];
                }
            }

            $amount = (float)$row['signed_movement'];
            $matrix[$key]['values'][$monthKey] = ($matrix[$key]['values'][$monthKey] ?? 0.0) + $amount;
            $matrix[$key]['debit_total'] += abs((float)($row['debit'] ?? 0));
            $matrix[$key]['credit_total'] += abs((float)($row['credit'] ?? 0));
            $matrix[$key]['movimento'] += $amount;

            $type = (string)($row['movement_type'] ?? '');
            if ($type !== '') {
                $currentType = $matrix[$key]['types'][$monthKey] ?? '';
                $matrix[$key]['types'][$monthKey] = $currentType === '' || $currentType === $type ? $type : 'mix';
            }
        }

        // Calcular receita bruta por mês (linha RECEITA BRUTA DE VENDAS E SERVICOS)
        $receitaPorMes = [];
        
        foreach ($matrix as $row) {
            $desc = mb_strtoupper(trim($row['account_description']));
            
            // Procura por "RECEITA BRUTA DE VENDAS E SERVICOS" ou similar
            if (preg_match('/RECEITA\s+BRUTA\s+DE\s+VENDAS\s+E\s+SERVI[CÇ]OS/u', $desc)) {
                foreach ($monthKeys as $monthKey) {
                    $value = abs((float)($row['values'][$monthKey] ?? 0.0));
                    $receitaPorMes[$monthKey] = $value;
                }
                break;
            }
        }

        $visibleMonthCount = max(1, count($monthKeys));

        foreach ($matrix as &$row) {
            $sum = 0.0;
            $count = 0;
            $row['percentuais'] = array_fill_keys($monthKeys, 0.0);
            
            foreach ($monthKeys as $monthKey) {
                $value = (float)($row['values'][$monthKey] ?? 0.0);
                $sum += $value;
                if ($value != 0.0) {
                    $count++;
                }
                
                // Calcular percentual em relação à receita do mês
                $receitaMes = $receitaPorMes[$monthKey] ?? 0.0;
                if ($receitaMes != 0.0) {
                    $row['percentuais'][$monthKey] = (abs($value) / $receitaMes) * 100;
                }
            }
            $row['acumulado'] = $sum;
            $row['count_nonzero'] = $count;
            $row['media'] = $sum / $visibleMonthCount;
            $row['movimento'] = $sum;
        }
        unset($row);

        $matrixRows = array_values($matrix);
        $parentLineByCode = [];
        foreach ($matrixRows as $row) {
            if (!empty($row['is_analytical'])) {
                continue;
            }

            $code = (string)$row['account_code'];
            $line = (float)$row['line_number'];
            $parentLineByCode[$code] = isset($parentLineByCode[$code])
                ? min($parentLineByCode[$code], $line)
                : $line;
        }

        foreach ($matrixRows as &$row) {
            if (empty($row['is_analytical'])) {
                continue;
            }

            $code = (string)$row['account_code'];
            if (!isset($parentLineByCode[$code])) {
                continue;
            }

            $unitOrder = 0;
            if (preg_match('/^(\d{3})\s+/', (string)$row['account_description'], $matches)) {
                $unitOrder = (int)$matches[1];
            }
            $row['sort_line_number'] = $parentLineByCode[$code] + ($unitOrder / 10000);
        }
        unset($row);

        usort($matrixRows, static function (array $a, array $b): int {
            return [
                (float)$a['sort_line_number'],
                (int)$a['indentation_level'],
                (string)$a['account_code'],
                (string)$a['account_description'],
            ] <=> [
                (float)$b['sort_line_number'],
                (int)$b['indentation_level'],
                (string)$b['account_code'],
                (string)$b['account_description'],
            ];
        });

        return $this->attachHierarchy($matrixRows);
    }

    private function attachHierarchy(array $rows): array
    {
        $stackByLevel = [];
        $count = count($rows);
        $isFlat = $this->isFlatHierarchy($rows);

        for ($i = 0; $i < $count; $i++) {
            if ($isFlat) {
                $rows[$i]['indentation_level'] = $this->inferDreLevel($rows[$i]);
                $rows[$i]['is_section'] = $this->isUpperHeading((string)$rows[$i]['account_description']);
            } else {
                $rows[$i]['is_section'] = (int)$rows[$i]['indentation_level'] === 0
                    && $this->isUpperHeading((string)$rows[$i]['account_description']);
            }
        }

        for ($i = 0; $i < $count; $i++) {
            $currentLevel = (int)$rows[$i]['indentation_level'];
            $nextLevel = $i + 1 < $count ? (int)$rows[$i + 1]['indentation_level'] : -1;
            $rows[$i]['has_children'] = $nextLevel > $currentLevel;
            $rows[$i]['is_group'] = $rows[$i]['has_children'];
        }

        foreach ($rows as $index => &$row) {
            $level = (int)$row['indentation_level'];
            foreach (array_keys($stackByLevel) as $stackLevel) {
                if ($stackLevel >= $level) {
                    unset($stackByLevel[$stackLevel]);
                }
            }

            ksort($stackByLevel);
            $parentUid = empty($stackByLevel) ? '' : end($stackByLevel);
            $row['row_uid'] = 'dre-row-' . $index;
            $row['parent_uid'] = $parentUid;
            $row['hide_duplicate'] = false;
            $stackByLevel[$level] = $row['row_uid'];
        }
        unset($row);

        // Oculta linhas pai que têm mesmo código do filho imediato (ex: 327 SALARIOS -> 327 001 Salarios)
        for ($i = 0; $i < $count - 1; $i++) {
            if (empty($rows[$i]['has_children'])) {
                continue;
            }
            $j = $i + 1;
            while ($j < $count && (int)$rows[$j]['indentation_level'] <= (int)$rows[$i]['indentation_level']) {
                $j++;
            }
            if ($j >= $count) {
                continue;
            }
            $firstChildLevel = (int)$rows[$j]['indentation_level'];
            $currentCode = trim((string)$rows[$i]['account_code']);
            $childCode = trim((string)$rows[$j]['account_code']);
            if ($currentCode !== '' && $currentCode === $childCode && $firstChildLevel === (int)$rows[$i]['indentation_level'] + 1 && $this->countImmediateChildren($rows, $i) === 1) {
                $rows[$i]['hide_duplicate'] = true;
                $rows[$i]['has_children'] = false;
                $rows[$i]['is_group'] = false;
                $oldParentUid = $rows[$i]['row_uid'];
                $newParentUid = $rows[$i]['parent_uid'];
                for ($k = $j; $k < $count; $k++) {
                    if ((int)$rows[$k]['indentation_level'] > $firstChildLevel) {
                        continue;
                    }
                    if ((int)$rows[$k]['indentation_level'] <= (int)$rows[$i]['indentation_level']) {
                        break;
                    }
                    if ($rows[$k]['parent_uid'] === $oldParentUid) {
                        $rows[$k]['parent_uid'] = $newParentUid;
                    }
                }
            }
        }

        return $rows;
    }

    private function countImmediateChildren(array $rows, int $parentIndex): int
    {
        $parentLevel = (int)$rows[$parentIndex]['indentation_level'];
        $childLevel = $parentLevel + 1;
        $count = 0;

        for ($i = $parentIndex + 1, $total = count($rows); $i < $total; $i++) {
            $level = (int)$rows[$i]['indentation_level'];
            if ($level <= $parentLevel) {
                break;
            }
            if ($level === $childLevel) {
                $count++;
            }
        }

        return $count;
    }

    private function isFlatHierarchy(array $rows): bool
    {
        foreach ($rows as $row) {
            if ((int)$row['indentation_level'] !== 0) {
                return false;
            }
        }

        return !empty($rows);
    }

    private function inferDreLevel(array $row): int
    {
        $description = trim((string)$row['account_description']);

        if (preg_match('/^\d{3}\s+\S/u', $description)) {
            return 2;
        }

        if ($this->isUpperHeading($description)) {
            return 0;
        }

        return 1;
    }

    private function isUpperHeading(string $description): bool
    {
        $description = trim($description);
        if ($description === '' || !preg_match('/[A-Za-zÀ-ÿ]/u', $description)) {
            return false;
        }

        return mb_strtoupper($description, 'UTF-8') === $description;
    }

    private function rowKey(array $row): string
    {
        return implode('|', [
            (int)$row['indentation_level'],
            (string)$row['account_code'],
            mb_strtolower(trim((string)$row['account_description'])),
        ]);
    }

    /**
     * Retorna [minMes, maxMes] dos imports confirmados que realmente têm linhas
     * de balancete para o ano/empresa/unidade. Se nenhum dado existir, usa o mês corrente.
     */
    private function detectMonthRange(int $year, int $companyId, int $unitId): array
    {
        $where  = ["i.status = 'confirmed'", 'i.year = ?'];
        $params = [$year];

        if ($unitId) {
            $where[]  = 'i.business_unit_id = ?';
            $params[] = $unitId;
        } elseif ($companyId) {
            $where[]  = 'i.company_id = ?';
            $params[] = $companyId;
        }

        $stmt = db()->prepare(
            'SELECT MIN(i.month) AS min_month, MAX(i.month) AS max_month
               FROM imports i
               JOIN trial_balance_rows tbr ON tbr.import_id = i.id
              WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        if ($row && $row['min_month']) {
            return [(int)$row['min_month'], (int)$row['max_month']];
        }

        // Sem dados: usar apenas o mês corrente como fallback
        $currentMonth = (int)date('n');
        return [$currentMonth, $currentMonth];
    }
}
