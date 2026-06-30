<?php
declare(strict_types=1);

class ImportController
{
    // -------------------------------------------------------
    // Lista de importações
    // -------------------------------------------------------

    public function index(): void
    {
        auth_check();
        $pdo = db();

        // Filtros
        $fCompany = (int)($_GET['company_id'] ?? 0);
        $fUnit    = (int)($_GET['unit_id'] ?? 0);
        $fYear    = (int)($_GET['year'] ?? 0);
        $fMonth   = (int)($_GET['month'] ?? 0);
        $fStatus  = $_GET['status'] ?? '';

        $where  = [];
        $params = [];

        if ($fCompany) { $where[] = 'i.company_id = ?';       $params[] = $fCompany; }
        if ($fUnit)    { $where[] = 'i.business_unit_id = ?'; $params[] = $fUnit;    }
        if ($fYear)    { $where[] = 'i.year = ?';             $params[] = $fYear;    }
        if ($fMonth)   { $where[] = 'i.month = ?';            $params[] = $fMonth;   }
        if ($fStatus)  { $where[] = 'i.status = ?';           $params[] = $fStatus;  }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare(
            "SELECT i.id, i.year, i.month, i.status, i.original_filename,
                    i.imported_at, i.error_message,
                    c.name AS company_name,
                    bu.name AS unit_name, bu.code AS unit_code,
                    u.name AS imported_by_name
             FROM imports i
             JOIN companies c ON c.id = i.company_id
             JOIN business_units bu ON bu.id = i.business_unit_id
             JOIN users u ON u.id = i.imported_by
             {$whereClause}
             ORDER BY i.year DESC, i.month DESC, i.imported_at DESC"
        );
        $stmt->execute($params);
        $imports = $stmt->fetchAll();

        $companies = $pdo->query('SELECT id, name FROM companies WHERE active=1 ORDER BY name')->fetchAll();
        $units     = $pdo->query('SELECT id, name, code FROM business_units WHERE active=1 ORDER BY name')->fetchAll();

        view('imports/index', compact('imports', 'companies', 'units', 'fCompany', 'fUnit', 'fYear', 'fMonth', 'fStatus'));
    }

    // -------------------------------------------------------
    // Formulário de upload
    // -------------------------------------------------------

    public function create(): void
    {
        auth_check();
        $pdo = db();

        $companies = $pdo->query('SELECT id, name FROM companies WHERE active=1 ORDER BY name')->fetchAll();
        $units     = $pdo->query('SELECT id, name, code, company_id FROM business_units WHERE active=1 ORDER BY name')->fetchAll();

        view('imports/create', compact('companies', 'units'));
    }

    // -------------------------------------------------------
    // Processar upload
    // -------------------------------------------------------

    public function store(): void
    {
        auth_check();
        csrf_verify();

        $pdo = db();

        // Verificar arquivo
        if (empty($_FILES['balancete']) || $_FILES['balancete']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['balancete']['error'] ?? -1;
            $errMsg  = $errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE
                ? 'Arquivo muito grande. Máximo: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB.'
                : 'Erro no upload do arquivo (código ' . $errCode . ').';
            flash('error', $errMsg);
            redirect('imports/create');
        }

        $file     = $_FILES['balancete'];
        $origName = $file['name'];
        $tmpPath  = $file['tmp_name'];
        $fileSize = $file['size'];

        if ($fileSize > MAX_UPLOAD_SIZE) {
            flash('error', 'Arquivo muito grande.');
            redirect('imports/create');
        }

        if (!allowed_extension($origName)) {
            flash('error', 'Tipo de arquivo não permitido. Use: TXT, RTF ou DOC.');
            redirect('imports/create');
        }

        $fileHash = hash_file('sha256', $tmpPath);

        // Verificar duplicata
        $dup = $pdo->prepare('SELECT id FROM imports WHERE file_hash = ? LIMIT 1');
        $dup->execute([$fileHash]);
        if ($dup->fetch()) {
            flash('warning', 'Este arquivo já foi importado anteriormente.');
            redirect('imports/create');
        }

        // Mover arquivo
        ensure_dir(UPLOADS_PATH);
        $ext         = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $storedName  = date('Ymd_His') . '_' . $fileHash . '.' . $ext;
        $storedPath  = UPLOADS_PATH . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($tmpPath, $storedPath)) {
            flash('error', 'Não foi possível salvar o arquivo.');
            redirect('imports/create');
        }

        // Parse
        $parser = new BalanceteParser();
        $result = $parser->parse($storedPath);

        if (!$result['success'] && empty($result['rows'])) {
            flash('error', 'Erro ao processar arquivo: ' . implode(', ', $result['errors']));
            @unlink($storedPath);
            redirect('imports/create');
        }

        $header = $result['header'];

        $companyId = (int)($_POST['company_id'] ?? 0);
        $unitId    = (int)($_POST['unit_id'] ?? 0);

        try {
            [$companyId, $unitId] = $this->resolveCompanyAndUnit($header, $result['rows'], $companyId, $unitId);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            @unlink($storedPath);
            redirect('imports/create');
        }

        // Determinar ano/mês do arquivo (usa o do cabeçalho se detectado, senão pede ao usuário)
        $year  = $header['periodo_ano'] ?: (int)($_POST['year'] ?? date('Y'));
        $month = $header['periodo_mes'] ?: (int)($_POST['month'] ?? date('n'));

        // Inserir import
        $stmt = $pdo->prepare(
            'INSERT INTO imports (company_id, business_unit_id, year, month, original_filename,
                                  file_hash, status, imported_by, raw_text_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $companyId, $unitId, $year, $month, $origName,
            $fileHash, 'confirmed', current_user_id(), $storedPath,
        ]);
        $importId = (int)$pdo->lastInsertId();

        // Salvar rows no banco
        $this->saveRows($importId, $result['rows']);

        audit('import_uploaded', 'import', $importId, [
            'file' => $origName, 'rows' => count($result['rows'])
        ]);

        audit('import_confirmed', 'import', $importId);
        flash('success', 'Importação concluída com sucesso!');
        redirect('dre');
    }

    // -------------------------------------------------------
    // Preview da importação
    // -------------------------------------------------------

    public function preview(string $id): void
    {
        auth_check();
        $importId = (int)$id;

        $import = $this->findImport($importId);
        if (!$import) {
            flash('error', 'Importação não encontrada.');
            redirect('imports');
        }

        $stmtCount = db()->prepare('SELECT COUNT(*) FROM trial_balance_rows WHERE import_id = ?');
        $stmtCount->execute([$importId]);
        $totalRows = (int)$stmtCount->fetchColumn();

        $treeRows = (new BalanceteTree())->rowsForImport($importId);

        view('imports/preview', compact(
            'import', 'totalRows', 'treeRows'
        ));
    }

    // -------------------------------------------------------
    // Confirmar importação
    // -------------------------------------------------------

    public function confirm(string $id): void
    {
        auth_check();
        csrf_verify();

        $importId = (int)$id;
        $import   = $this->findImport($importId);

        if (!$import) {
            flash('error', 'Importação não encontrada.');
            redirect('imports');
        }

        // Marcar como confirmada
        db()->prepare("UPDATE imports SET status='confirmed' WHERE id=?")->execute([$importId]);

        audit('import_confirmed', 'import', $importId);
        flash('success', 'Importação confirmada com sucesso!');
        redirect('dre');
    }

    // -------------------------------------------------------
    // Excluir importação
    // -------------------------------------------------------

    public function destroy(string $id): void
    {
        auth_check();
        csrf_verify();

        $importId = (int)$id;
        $import   = $this->findImport($importId);

        if (!$import) {
            flash('error', 'Importação não encontrada.');
            redirect('imports');
        }

        // Remover arquivo físico
        if ($import['raw_text_path'] && file_exists($import['raw_text_path'])) {
            @unlink($import['raw_text_path']);
        }

        db()->prepare('DELETE FROM imports WHERE id=?')->execute([$importId]);

        audit('import_deleted', 'import', $importId);
        flash('success', 'Importação removida.');
        redirect('imports');
    }

    // -------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------

    private function resolveCompanyAndUnit(array $header, array $rows, int $companyId, int $unitId): array
    {
        $pdo = db();

        if ($unitId > 0) {
            $stmt = $pdo->prepare('SELECT id, company_id FROM business_units WHERE id = ? AND active = 1');
            $stmt->execute([$unitId]);
            $unit = $stmt->fetch();
            if (!$unit) {
                throw new RuntimeException('Unidade selecionada não encontrada.');
            }
            return [(int)$unit['company_id'], (int)$unit['id']];
        }

        if ($companyId <= 0) {
            $companyId = $this->findOrCreateCompany($header);
        } else {
            $stmt = $pdo->prepare('SELECT id FROM companies WHERE id = ? AND active = 1');
            $stmt->execute([$companyId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Empresa selecionada não encontrada.');
            }
        }

        $unitCode = trim((string)($header['unidade_codigo'] ?? ''));
        $unitName = trim((string)($header['unidade_nome'] ?? ''));

        if ($unitCode === '') {
            [$unitCode, $unitName] = $this->inferSingleUnitFromRows($rows);
        }

        if ($unitCode === '') {
            throw new RuntimeException('Não foi possível identificar a unidade do balancete. Selecione a unidade manualmente no upload.');
        }

        if ($unitName === '') {
            $unitName = 'Unidade ' . $unitCode;
        }

        $stmt = $pdo->prepare('SELECT id FROM business_units WHERE company_id = ? AND code = ? LIMIT 1');
        $stmt->execute([$companyId, $unitCode]);
        $existingId = $stmt->fetchColumn();
        if ($existingId) {
            $pdo->prepare('UPDATE business_units SET name = ?, active = 1 WHERE id = ?')
                ->execute([$unitName, $existingId]);
            return [$companyId, (int)$existingId];
        }

        $stmt = $pdo->prepare('INSERT INTO business_units (company_id, code, name, active) VALUES (?, ?, ?, 1)');
        $stmt->execute([$companyId, $unitCode, $unitName]);

        return [$companyId, (int)$pdo->lastInsertId()];
    }

    private function findOrCreateCompany(array $header): int
    {
        $pdo = db();
        $cnpj = trim((string)($header['cnpj'] ?? ''));
        $name = trim((string)($header['empresa_nome'] ?? ''));

        if ($cnpj !== '') {
            $stmt = $pdo->prepare('SELECT id FROM companies WHERE cnpj = ? LIMIT 1');
            $stmt->execute([$cnpj]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                if ($name !== '') {
                    $pdo->prepare('UPDATE companies SET name = ?, active = 1 WHERE id = ?')
                        ->execute([$name, $existingId]);
                }
                return (int)$existingId;
            }
        }

        if ($name === '') {
            throw new RuntimeException('Não foi possível identificar a empresa do balancete. Selecione a empresa manualmente no upload.');
        }

        $stmt = $pdo->prepare('INSERT INTO companies (name, cnpj, active) VALUES (?, ?, 1)');
        $stmt->execute([$name, $cnpj]);

        return (int)$pdo->lastInsertId();
    }

    private function inferSingleUnitFromRows(array $rows): array
    {
        $units = [];
        foreach ($rows as $row) {
            if (empty($row['is_analytical'])) {
                continue;
            }
            if (preg_match('/^(\d{3})\s+(.+)$/', (string)$row['account_description'], $m)) {
                $units[$m[1]] = 'Unidade ' . $m[1];
            }
        }

        if (count($units) === 1) {
            $code = array_key_first($units);
            return [$code, $units[$code]];
        }

        if (count($units) > 1) {
            throw new RuntimeException('O balancete contém mais de uma unidade analítica. Selecione a unidade manualmente no upload.');
        }

        return ['', ''];
    }

    private function saveRows(int $importId, array $rows): void
    {
        $pdo  = db();
        $stmt = $pdo->prepare(
            'INSERT INTO trial_balance_rows
                (import_id, line_number, account_code, account_description, indentation_level,
                 is_analytical, movement_value, movement_type, debit, credit, raw_line)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );

        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $stmt->execute([
                    $importId,
                    $row['line_number'],
                    $row['account_code'],
                    $row['account_description'],
                    $row['indentation_level'],
                    $row['is_analytical'],
                    $row['movement_value'],
                    $row['movement_type'],
                    $row['debit'],
                    $row['credit'],
                    $row['raw_line'],
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function findImport(int $id): array|false
    {
        $stmt = db()->prepare(
            'SELECT i.*, c.name AS company_name, c.cnpj,
                    bu.name AS unit_name, bu.code AS unit_code
             FROM imports i
             JOIN companies c ON c.id = i.company_id
             JOIN business_units bu ON bu.id = i.business_unit_id
             WHERE i.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

}
