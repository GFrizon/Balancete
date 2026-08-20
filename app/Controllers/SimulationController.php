<?php
declare(strict_types=1);

class SimulationController
{
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
