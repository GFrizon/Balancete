<?php $pageTitle = 'Importações'; ?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<div class="container-fluid py-4 px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-cloud-upload me-2 text-primary"></i>Importações</h4>
      <small class="text-muted">Histórico de balancetes importados</small>
    </div>
    <a href="<?= url('imports/create') ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i>Nova Importação
    </a>
  </div>

  <!-- Filtros -->
  <div class="card shadow-sm mb-4">
    <div class="card-body py-3">
      <form method="GET" action="<?= url() ?>" class="row g-2 align-items-end">
        <input type="hidden" name="route" value="imports">
        <div class="col-sm-6 col-md-3">
          <label class="form-label small mb-1">Empresa</label>
          <select name="company_id" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($companies as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $fCompany == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6 col-md-2">
          <label class="form-label small mb-1">Unidade</label>
          <select name="unit_id" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($units as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $fUnit == $u['id'] ? 'selected' : '' ?>><?= e($u['code']) ?> — <?= e($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-1">
          <label class="form-label small mb-1">Ano</label>
          <input type="number" name="year" class="form-control form-control-sm" placeholder="2026" value="<?= $fYear ?: '' ?>" min="2000" max="2099">
        </div>
        <div class="col-6 col-md-1">
          <label class="form-label small mb-1">Mês</label>
          <select name="month" class="form-select form-select-sm">
            <option value="">—</option>
            <?php foreach (MONTHS_PT as $n => $name): ?>
            <option value="<?= $n ?>" <?= $fMonth == $n ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6 col-md-2">
          <label class="form-label small mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">Todos</option>
            <option value="pending"   <?= $fStatus==='pending'    ? 'selected' : '' ?>>Pendente</option>
            <option value="confirmed" <?= $fStatus==='confirmed'  ? 'selected' : '' ?>>Confirmado</option>
            <option value="error"     <?= $fStatus==='error'      ? 'selected' : '' ?>>Erro</option>
          </select>
        </div>
        <div class="col-sm-6 col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search me-1"></i>Filtrar</button>
          <a href="<?= url('imports') ?>" class="btn btn-outline-secondary btn-sm">Limpar</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabela -->
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <?php if (empty($imports)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        Nenhuma importação encontrada.
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Empresa / Unidade</th>
              <th>Período</th>
              <th>Arquivo</th>
              <th>Status</th>
              <th>Importado por</th>
              <th>Data</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($imports as $imp): ?>
            <?php
              $badges = ['pending'=>'warning','processing'=>'info','confirmed'=>'success','error'=>'danger'];
              $badge = $badges[$imp['status']] ?? 'secondary';
              $labels = ['pending'=>'Pendente','processing'=>'Processando','confirmed'=>'Confirmado','error'=>'Erro'];
            ?>
            <tr>
              <td class="text-muted small"><?= $imp['id'] ?></td>
              <td>
                <div class="fw-semibold"><?= e($imp['company_name']) ?></div>
                <small class="text-muted"><?= e($imp['unit_code']) ?> — <?= e($imp['unit_name']) ?></small>
              </td>
              <td class="fw-semibold"><?= month_short((int)$imp['month']) ?>/<?= $imp['year'] ?></td>
              <td><small class="text-muted text-truncate d-block" style="max-width:180px" title="<?= e($imp['original_filename']) ?>"><?= e($imp['original_filename']) ?></small></td>
              <td><span class="badge bg-<?= $badge ?>"><?= $labels[$imp['status']] ?? $imp['status'] ?></span></td>
              <td><small><?= e($imp['imported_by_name']) ?></small></td>
              <td><small><?= date('d/m/Y H:i', strtotime($imp['imported_at'])) ?></small></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a href="<?= url('imports/' . $imp['id'] . '/preview') ?>" class="btn btn-outline-secondary" title="Preview">
                    <i class="bi bi-eye"></i>
                  </a>
                  <?php if ($imp['status'] === 'confirmed'): ?>
                  <a href="<?= url('dre') ?>" class="btn btn-outline-primary" title="Ver DRE">
                    <i class="bi bi-list-tree"></i>
                  </a>
                  <?php endif; ?>
                  <?php if (current_user_role() === 'admin'): ?>
                  <form method="POST" action="<?= url('imports/' . $imp['id'] . '/delete') ?>"
                        onsubmit="return confirm('Excluir esta importação?')" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger" title="Excluir">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>
<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>
