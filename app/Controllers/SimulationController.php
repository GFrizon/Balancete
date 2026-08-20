<?php
declare(strict_types=1);

class SimulationController
{
    private const ADJUSTMENT_MODES = ['amount', 'percent', 'override'];
    private const CLASSIFICATIONS = ['none', 'revenue', 'variable', 'fixed', 'non_recurring', 'non_operational'];

    public function index(): void
    {
        auth_check();
        $this->ensureTables();

        $simulations = db()->query(
            "SELECT ds.*, u.name AS created_by_name,
                    c.name AS company_name,
                    bu.code AS unit_code, bu.name AS unit_name,
                    ug.name AS group_name,
                    (
                        SELECT COUNT(*)
                          FROM dre_simulation_adjustments dsa
                         WHERE dsa.simulation_id = ds.id
                    ) AS adjustments_count
               FROM dre_simulations ds
               LEFT JOIN users u ON u.id = ds.created_by
               LEFT JOIN companies c ON c.id = ds.company_id
               LEFT JOIN business_units bu ON bu.id = ds.unit_id
               LEFT JOIN unit_groups ug ON ug.id = ds.group_id
              ORDER BY ds.updated_at DESC, ds.created_at DESC"
        )->fetchAll();

        $companies = db()->query(
            "SELECT c.id, c.name,
                    GROUP_CONCAT(CONCAT(bu.code, ' - ', bu.name) ORDER BY bu.code SEPARATOR ' / ') AS units_label
               FROM companies c
               LEFT JOIN business_units bu ON bu.company_id = c.id AND bu.active = 1
              WHERE c.active = 1
              GROUP BY c.id, c.name
              ORDER BY c.name"
        )->fetchAll();

        $groups = db()->query(
            "SELECT ug.id, ug.name, COUNT(ugi.business_unit_id) AS units_count
               FROM unit_groups ug
               LEFT JOIN unit_group_items ugi ON ugi.unit_group_id = ug.id
              WHERE ug.active = 1
              GROUP BY ug.id, ug.name
              ORDER BY ug.name"
        )->fetchAll();

        $units = db()->query(
            "SELECT bu.id, bu.code, bu.name, c.name AS company_name
               FROM business_units bu
               JOIN companies c ON c.id = bu.company_id
              WHERE bu.active = 1
              ORDER BY c.name, bu.code, bu.name"
        )->fetchAll();

        $yearsAvailable = db()->query(
            "SELECT DISTINCT year FROM imports WHERE status='confirmed' ORDER BY year DESC"
        )->fetchAll(PDO::FETCH_COLUMN);
        if (empty($yearsAvailable)) {
            $yearsAvailable = [date('Y')];
        }

        view('simulations/index', compact('simulations', 'companies', 'groups', 'units', 'yearsAvailable'));
    }

    public function show(string $id): void
    {
        auth_check();
        $this->ensureTables();

        $id = (int)$id;
        $simulation = $this->findSimulation($id);
        if (!$simulation) {
            flash('error', 'Simulacao nao encontrada.');
            redirect('simulations');
        }

        $reportData = $this->baseReportData($simulation);
        $adjustments = $this->adjustmentsByHash($id);
        $matrixRows = $this->applyAdjustments($reportData['matrixRows'], $adjustments);
        $months = $reportData['months'];
        $summary = $this->simulationSummary($matrixRows);

        view('simulations/show', compact('simulation', 'matrixRows', 'months', 'adjustments', 'summary'));
    }

    public function store(): void
    {
        auth_check();
        csrf_verify();
        $this->ensureTables();

        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $companyFilter = trim((string)($_POST['company_filter'] ?? ''));
        $unitId = (int)($_POST['unit_id'] ?? 0);
        $year = (int)($_POST['year'] ?? date('Y'));
        $monthStart = (int)($_POST['month_start'] ?? 1);
        $monthEnd = (int)($_POST['month_end'] ?? 12);

        if ($name === '') {
            flash('error', 'Nome da simulacao obrigatorio.');
            redirect('simulations');
        }

        if ($monthStart < 1 || $monthStart > 12 || $monthEnd < 1 || $monthEnd > 12) {
            flash('error', 'Periodo invalido para a simulacao.');
            redirect('simulations');
        }

        if ($monthStart > $monthEnd) {
            [$monthStart, $monthEnd] = [$monthEnd, $monthStart];
        }

        [$companyId, $groupId] = $this->parseCompanyFilter($companyFilter);
        if ($unitId > 0) {
            $groupId = 0;
        }

        $stmt = db()->prepare(
            'INSERT INTO dre_simulations
                (name, description, company_id, group_id, unit_id, year, month_start, month_end, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $description,
            $companyId ?: null,
            $groupId ?: null,
            $unitId ?: null,
            $year,
            $monthStart,
            $monthEnd,
            current_user_id() ?: null,
        ]);

        $id = (int)db()->lastInsertId();
        audit('dre_simulation_created', 'dre_simulation', $id, [
            'name' => $name,
            'year' => $year,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ]);

        flash('success', 'Simulacao criada.');
        redirect('simulations');
    }

    public function destroy(): void
    {
        auth_check();
        csrf_verify();
        $this->ensureTables();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('DELETE FROM dre_simulations WHERE id = ?')->execute([$id]);
            audit('dre_simulation_deleted', 'dre_simulation', $id);
            flash('success', 'Simulacao removida.');
        }

        redirect('simulations');
    }

    public function updateAdjustments(string $id): void
    {
        auth_check();
        csrf_verify();
        $this->ensureTables();

        $id = (int)$id;
        $simulation = $this->findSimulation($id);
        if (!$simulation) {
            flash('error', 'Simulacao nao encontrada.');
            redirect('simulations');
        }

        $rowKeys = $_POST['row_key'] ?? [];
        if (!is_array($rowKeys)) {
            flash('error', 'Ajustes invalidos.');
            redirect('simulations/' . $id);
        }

        $modes = is_array($_POST['adjustment_mode'] ?? null) ? $_POST['adjustment_mode'] : [];
        $values = is_array($_POST['adjustment_value'] ?? null) ? $_POST['adjustment_value'] : [];
        $percents = is_array($_POST['adjustment_percent'] ?? null) ? $_POST['adjustment_percent'] : [];
        $classifications = is_array($_POST['classification'] ?? null) ? $_POST['classification'] : [];
        $notes = is_array($_POST['note'] ?? null) ? $_POST['note'] : [];
        $codes = is_array($_POST['account_code'] ?? null) ? $_POST['account_code'] : [];
        $descriptions = is_array($_POST['account_description'] ?? null) ? $_POST['account_description'] : [];
        $levels = is_array($_POST['indentation_level'] ?? null) ? $_POST['indentation_level'] : [];

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $pdo->prepare('DELETE FROM dre_simulation_adjustments WHERE simulation_id = ? AND target_period = ?')
                ->execute([$id, '']);

            $stmt = $pdo->prepare(
                'INSERT INTO dre_simulation_adjustments
                    (simulation_id, row_key_hash, row_key, account_code, account_description, indentation_level,
                     target_period, adjustment_mode, adjustment_value, adjustment_percent, classification, note,
                     created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($rowKeys as $hash => $rowKey) {
                $hash = (string)$hash;
                $rowKey = (string)$rowKey;
                if (!preg_match('/^[a-f0-9]{64}$/', $hash) || hash('sha256', $rowKey) !== $hash) {
                    continue;
                }

                $mode = in_array(($modes[$hash] ?? 'amount'), self::ADJUSTMENT_MODES, true)
                    ? (string)$modes[$hash]
                    : 'amount';
                $classification = in_array(($classifications[$hash] ?? 'none'), self::CLASSIFICATIONS, true)
                    ? (string)$classifications[$hash]
                    : 'none';
                $value = $this->parseOptionalDecimal((string)($values[$hash] ?? ''));
                $percent = $this->parseOptionalDecimal((string)($percents[$hash] ?? ''));
                $note = trim((string)($notes[$hash] ?? ''));

                $hasNumericAdjustment = ($mode === 'percent' && $percent !== null)
                    || ($mode !== 'percent' && $value !== null);
                if (!$hasNumericAdjustment && $classification === 'none' && $note === '') {
                    continue;
                }

                $stmt->execute([
                    $id,
                    $hash,
                    $rowKey,
                    mb_substr((string)($codes[$hash] ?? ''), 0, 20),
                    mb_substr((string)($descriptions[$hash] ?? ''), 0, 500),
                    max(0, min(255, (int)($levels[$hash] ?? 0))),
                    '',
                    $mode,
                    $mode === 'percent' ? null : $value,
                    $mode === 'percent' ? $percent : null,
                    $classification,
                    $note === '' ? null : $note,
                    current_user_id() ?: null,
                    current_user_id() ?: null,
                ]);
            }

            $pdo->prepare('UPDATE dre_simulations SET updated_at = NOW() WHERE id = ?')->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        audit('dre_simulation_adjustments_updated', 'dre_simulation', $id);
        flash('success', 'Ajustes salvos e simulacao recalculada.');
        redirect('simulations/' . $id);
    }

    private function parseCompanyFilter(string $filter): array
    {
        if (preg_match('/^c:(\d+)$/', $filter, $matches)) {
            return [(int)$matches[1], 0];
        }

        if (preg_match('/^g:(\d+)$/', $filter, $matches)) {
            return [0, (int)$matches[1]];
        }

        return [0, 0];
    }

    private function findSimulation(int $id): ?array
    {
        $stmt = db()->prepare(
            "SELECT ds.*, u.name AS created_by_name,
                    c.name AS company_name,
                    bu.code AS unit_code, bu.name AS unit_name,
                    ug.name AS group_name
               FROM dre_simulations ds
               LEFT JOIN users u ON u.id = ds.created_by
               LEFT JOIN companies c ON c.id = ds.company_id
               LEFT JOIN business_units bu ON bu.id = ds.unit_id
               LEFT JOIN unit_groups ug ON ug.id = ds.group_id
              WHERE ds.id = ?
              LIMIT 1"
        );
        $stmt->execute([$id]);
        $simulation = $stmt->fetch();

        return $simulation ?: null;
    }

    private function baseReportData(array $simulation): array
    {
        $controller = new DreController();
        $method = new ReflectionMethod($controller, 'buildReportData');
        $method->setAccessible(true);

        return $method->invoke(
            $controller,
            (int)($simulation['company_id'] ?? 0),
            (int)($simulation['unit_id'] ?? 0),
            (int)($simulation['group_id'] ?? 0),
            (int)$simulation['year'],
            (int)$simulation['month_start'],
            (int)$simulation['month_end']
        );
    }

    private function adjustmentsByHash(int $simulationId): array
    {
        $stmt = db()->prepare(
            "SELECT *
               FROM dre_simulation_adjustments
              WHERE simulation_id = ?
                AND target_period = ''
              ORDER BY id"
        );
        $stmt->execute([$simulationId]);

        $adjustments = [];
        foreach ($stmt->fetchAll() as $row) {
            $adjustments[(string)$row['row_key_hash']] = $row;
        }

        return $adjustments;
    }

    private function applyAdjustments(array $rows, array $adjustments): array
    {
        $indexByUid = [];
        foreach ($rows as $index => &$row) {
            $rowKey = (string)($row['row_key'] ?? '');
            $hash = hash('sha256', $rowKey);
            $adjustment = $adjustments[$hash] ?? null;
            $base = (float)($row['acumulado'] ?? 0.0);

            $row['simulation_hash'] = $hash;
            $row['simulation_adjustment'] = $adjustment;
            $row['direct_adjustment_delta'] = $adjustment ? $this->adjustmentDelta($base, $adjustment) : 0.0;
            $row['simulated_acumulado'] = $base + (float)$row['direct_adjustment_delta'];
            $row['simulated_delta'] = (float)$row['direct_adjustment_delta'];
            $row['has_simulation_change'] = abs((float)$row['direct_adjustment_delta']) >= 0.005
                || ($adjustment && ((string)($adjustment['classification'] ?? 'none') !== 'none' || trim((string)($adjustment['note'] ?? '')) !== ''));
            $indexByUid[(string)($row['row_uid'] ?? '')] = $index;
        }
        unset($row);

        foreach ($rows as $row) {
            $delta = (float)($row['direct_adjustment_delta'] ?? 0.0);
            if (abs($delta) < 0.005) {
                continue;
            }

            $parentUid = (string)($row['parent_uid'] ?? '');
            while ($parentUid !== '' && isset($indexByUid[$parentUid])) {
                $parentIndex = $indexByUid[$parentUid];
                $rows[$parentIndex]['simulated_acumulado'] = (float)($rows[$parentIndex]['simulated_acumulado'] ?? 0.0) + $delta;
                $rows[$parentIndex]['simulated_delta'] = (float)($rows[$parentIndex]['simulated_delta'] ?? 0.0) + $delta;
                $rows[$parentIndex]['has_simulation_change'] = true;
                $parentUid = (string)($rows[$parentIndex]['parent_uid'] ?? '');
            }
        }

        return $rows;
    }

    private function adjustmentDelta(float $base, array $adjustment): float
    {
        $mode = (string)($adjustment['adjustment_mode'] ?? 'amount');

        if ($mode === 'percent') {
            if ($adjustment['adjustment_percent'] === null || $adjustment['adjustment_percent'] === '') {
                return 0.0;
            }

            return ($base * ((float)$adjustment['adjustment_percent'] / 100.0)) - $base;
        }

        if ($mode === 'override') {
            if ($adjustment['adjustment_value'] === null || $adjustment['adjustment_value'] === '') {
                return 0.0;
            }

            return (float)$adjustment['adjustment_value'] - $base;
        }

        return (float)($adjustment['adjustment_value'] ?? 0.0);
    }

    private function simulationSummary(array $rows): array
    {
        $changed = 0;
        $baseTotal = 0.0;
        $simulatedTotal = 0.0;

        foreach ($rows as $row) {
            if (!empty($row['hide_duplicate'])) {
                continue;
            }

            if (!empty($row['has_simulation_change'])) {
                $changed++;
            }

            if ((int)($row['indentation_level'] ?? 0) === 0) {
                $baseTotal = (float)($row['acumulado'] ?? 0.0);
                $simulatedTotal = (float)($row['simulated_acumulado'] ?? $baseTotal);
                break;
            }
        }

        return [
            'changed_rows' => $changed,
            'base_total' => $baseTotal,
            'simulated_total' => $simulatedTotal,
            'delta_total' => $simulatedTotal - $baseTotal,
        ];
    }

    private function parseOptionalDecimal(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $value = trim($value, " \t\n\r\0\x0B()");
        $value = str_replace(['R$', '%', ' '], '', $value);

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float)$value;
        return $negative ? -$number : $number;
    }

    private function ensureTables(): void
    {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS unit_groups (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(200) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS unit_group_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                unit_group_id INT UNSIGNED NOT NULL,
                business_unit_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ugi (unit_group_id, business_unit_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS dre_simulations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                company_id INT UNSIGNED NULL,
                group_id INT UNSIGNED NULL,
                unit_id INT UNSIGNED NULL,
                year SMALLINT UNSIGNED NOT NULL,
                month_start TINYINT UNSIGNED NOT NULL,
                month_end TINYINT UNSIGNED NOT NULL,
                status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_dre_simulations_period (year, month_start, month_end),
                KEY idx_dre_simulations_company (company_id),
                KEY idx_dre_simulations_group (group_id),
                KEY idx_dre_simulations_unit (unit_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS dre_simulation_adjustments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                simulation_id INT UNSIGNED NOT NULL,
                row_key_hash CHAR(64) NOT NULL,
                row_key TEXT NOT NULL,
                account_code VARCHAR(20) NOT NULL DEFAULT '',
                account_description VARCHAR(500) NOT NULL DEFAULT '',
                indentation_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
                target_period VARCHAR(7) NOT NULL DEFAULT '',
                adjustment_mode ENUM('amount','percent','override') NOT NULL DEFAULT 'amount',
                adjustment_value DECIMAL(18,2) NULL,
                adjustment_percent DECIMAL(10,4) NULL,
                classification ENUM('none','revenue','variable','fixed','non_recurring','non_operational') NOT NULL DEFAULT 'none',
                note TEXT NULL,
                created_by INT UNSIGNED NULL,
                updated_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_dre_sim_adjustment (simulation_id, row_key_hash, target_period),
                KEY idx_dre_sim_adjustment_simulation (simulation_id),
                CONSTRAINT fk_dre_sim_adjustment_simulation
                    FOREIGN KEY (simulation_id) REFERENCES dre_simulations (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
