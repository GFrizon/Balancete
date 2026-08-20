<?php $pageTitle = 'Simulacoes'; ?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<?php
$scopeLabel = static function (array $simulation): string {
    if (!empty($simulation['unit_name'])) {
        return trim((string)$simulation['unit_code'] . ' - ' . (string)$simulation['unit_name']);
    }

    if (!empty($simulation['group_name'])) {
        return 'Grupo: ' . (string)$simulation['group_name'];
    }

    if (!empty($simulation['company_name'])) {
        return (string)$simulation['company_name'];
    }

    return 'Todas';
};

$periodLabel = static function (array $simulation): string {
    $start = month_short((int)$simulation['month_start']);
    $end = month_short((int)$simulation['month_end']);
    $year = (int)$simulation['year'];

    return $start === $end ? "{$start}/{$year}" : "{$start}/{$year} a {$end}/{$year}";
};
?>

<div class="container py-4" style="max-width:1100px">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-sliders me-2 text-primary"></i>Simulacoes</h4>
      <small class="text-muted">Cenarios gerenciais separados da DRE real</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSimulationModal">
      <i class="bi bi-plus-lg me-1"></i>Nova Simulacao
    </button>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Base</div>
          <div class="fw-semibold mt-1">DRE importada permanece intacta</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Ajustes</div>
          <div class="fw-semibold mt-1">Valores, percentuais, notas e classificacoes</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Analise</div>
          <div class="fw-semibold mt-1">Real, simulado, impacto e rastreio</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Nome</th>
              <th>Escopo</th>
              <th>Periodo</th>
              <th>Ajustes</th>
              <th>Criado por</th>
              <th>Atualizado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($simulations as $simulation): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= e($simulation['name']) ?></div>
                <?php if (!empty($simulation['description'])): ?>
                <small class="text-muted"><?= e($simulation['description']) ?></small>
                <?php endif; ?>
              </td>
              <td><?= e($scopeLabel($simulation)) ?></td>
              <td><?= e($periodLabel($simulation)) ?></td>
              <td>
                <span class="badge bg-primary-subtle text-primary border border-primary">
                  <?= (int)$simulation['adjustments_count'] ?>
                </span>
              </td>
              <td><small class="text-muted"><?= e($simulation['created_by_name'] ?? '-') ?></small></td>
              <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime((string)$simulation['updated_at'])) ?></small></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-secondary" href="<?= url('simulations/' . (int)$simulation['id']) ?>" title="Abrir simulacao">
                    <i class="bi bi-table"></i>
                  </a>
                  <form method="POST" action="<?= url('simulations/delete') ?>"
                        onsubmit="return confirm('Excluir a simulacao <?= e(addslashes((string)$simulation['name'])) ?>?')" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$simulation['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($simulations)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Nenhuma simulacao criada.</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addSimulationModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="<?= url('simulations/store') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Nova Simulacao</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="Ex: Nao recorrentes" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Ano</label>
              <select name="year" class="form-select">
                <?php foreach ($yearsAvailable as $year): ?>
                <option value="<?= (int)$year ?>"><?= (int)$year ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Descricao</label>
              <textarea name="description" class="form-control" rows="2" placeholder="Objetivo do cenario"></textarea>
            </div>
            <div class="col-md-5">
              <label class="form-label fw-semibold">Empresa ou grupo</label>
              <select name="company_filter" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($groups as $group): ?>
                <option value="g:<?= (int)$group['id'] ?>">Grupo: <?= e($group['name']) ?> (<?= (int)$group['units_count'] ?>)</option>
                <?php endforeach; ?>
                <?php foreach ($companies as $company): ?>
                <?php
                  $companyLabel = (string)$company['name'];
                  if (!empty($company['units_label'])) {
                      $companyLabel .= ' - ' . (string)$company['units_label'];
                  }
                ?>
                <option value="c:<?= (int)$company['id'] ?>"><?= e($companyLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Unidade</label>
              <select name="unit_id" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($units as $unit): ?>
                <option value="<?= (int)$unit['id'] ?>"><?= e($unit['code']) ?> - <?= e($unit['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label fw-semibold">Mes inicial</label>
              <select name="month_start" class="form-select">
                <?php foreach (MONTHS_PT as $number => $name): ?>
                <option value="<?= (int)$number ?>"><?= e(substr($name, 0, 3)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label fw-semibold">Mes final</label>
              <select name="month_end" class="form-select">
                <?php foreach (MONTHS_PT as $number => $name): ?>
                <option value="<?= (int)$number ?>" <?= (int)$number === 12 ? 'selected' : '' ?>><?= e(substr($name, 0, 3)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Criar Simulacao</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>
