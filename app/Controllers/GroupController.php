<?php
declare(strict_types=1);

class GroupController
{
    public function index(): void
    {
        auth_admin();
        $this->ensureTables();

        $groups = db()->query(
            "SELECT ug.id, ug.name, ug.active, ug.created_at,
                    COUNT(ugi.business_unit_id) AS units_count
               FROM unit_groups ug
               LEFT JOIN unit_group_items ugi ON ugi.unit_group_id = ug.id
              GROUP BY ug.id
              ORDER BY ug.name"
        )->fetchAll();

        $units = db()->query(
            "SELECT bu.id, bu.code, bu.name, c.name AS company_name
               FROM business_units bu
               JOIN companies c ON c.id = bu.company_id
              WHERE bu.active = 1
              ORDER BY c.name, bu.code, bu.name"
        )->fetchAll();

        $items = db()->query('SELECT unit_group_id, business_unit_id FROM unit_group_items')->fetchAll();
        $groupItems = [];
        foreach ($items as $item) {
            $groupItems[(int)$item['unit_group_id']][] = (int)$item['business_unit_id'];
        }

        view('groups/index', compact('groups', 'units', 'groupItems'));
    }

    public function store(): void
    {
        auth_admin();
        csrf_verify();
        $this->ensureTables();

        $name = trim($_POST['name'] ?? '');
        $unitIds = $this->unitIdsFromPost();

        if ($name === '') {
            flash('error', 'Nome do grupo obrigatorio.');
            redirect('groups');
        }
        if (empty($unitIds)) {
            flash('error', 'Selecione pelo menos uma unidade.');
            redirect('groups');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO unit_groups (name, active) VALUES (?, 1)');
            $stmt->execute([$name]);
            $groupId = (int)$pdo->lastInsertId();
            $this->syncItems($groupId, $unitIds);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        audit('unit_group_created', 'unit_group', $groupId, ['name' => $name]);
        flash('success', 'Grupo criado.');
        redirect('groups');
    }

    public function update(): void
    {
        auth_admin();
        csrf_verify();
        $this->ensureTables();

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $active = (int)($_POST['active'] ?? 1);
        $unitIds = $this->unitIdsFromPost();

        if ($id <= 0 || $name === '') {
            flash('error', 'Dados invalidos para atualizar o grupo.');
            redirect('groups');
        }
        if (empty($unitIds)) {
            flash('error', 'Selecione pelo menos uma unidade.');
            redirect('groups');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE unit_groups SET name = ?, active = ? WHERE id = ?')
                ->execute([$name, $active ? 1 : 0, $id]);
            $this->syncItems($id, $unitIds);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        audit('unit_group_updated', 'unit_group', $id, ['name' => $name]);
        flash('success', 'Grupo atualizado.');
        redirect('groups');
    }

    public function destroy(): void
    {
        auth_admin();
        csrf_verify();
        $this->ensureTables();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('DELETE FROM unit_groups WHERE id = ?')->execute([$id]);
            audit('unit_group_deleted', 'unit_group', $id);
            flash('success', 'Grupo removido.');
        }

        redirect('groups');
    }

    private function unitIdsFromPost(): array
    {
        $unitIds = $_POST['unit_ids'] ?? [];
        if (!is_array($unitIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $unitIds), static fn (int $id): bool => $id > 0)));
    }

    private function syncItems(int $groupId, array $unitIds): void
    {
        $pdo = db();
        $pdo->prepare('DELETE FROM unit_group_items WHERE unit_group_id = ?')->execute([$groupId]);

        $stmt = $pdo->prepare('INSERT INTO unit_group_items (unit_group_id, business_unit_id) VALUES (?, ?)');
        foreach ($unitIds as $unitId) {
            $stmt->execute([$groupId, $unitId]);
        }
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
    }
}
