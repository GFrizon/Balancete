<?php $pageTitle = 'Preview da DRE'; ?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<div class="container-fluid py-4 px-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center">
      <a href="<?= url('imports') ?>" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
      <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>DRE #<?= $import['id'] ?></h4>
        <small class="text-muted">
          <?= e($import['company_name']) ?> - <?= e($import['unit_code']) ?>/<?= e($import['unit_name']) ?> -
          <?= month_short((int)$import['month']) ?>/<?= $import['year'] ?>
        </small>
      </div>
    </div>
    <span class="badge bg-<?= $import['status'] === 'confirmed' ? 'success' : 'warning text-dark' ?>">
      <?= $import['status'] === 'confirmed' ? 'Confirmado' : 'Pendente' ?>
    </span>
  </div>

  <div class="alert alert-primary d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1 fs-5"></i>
    <div>
      A DRE abaixo vem diretamente do arquivo importado. Subgrupos são definidos pela indentação/quebra visual do balancete.
      A tela usa somente <strong>Movimento</strong>; débito e crédito ficam armazenados apenas para auditoria.
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small mb-1"><i class="bi bi-building me-1"></i>Empresa</div>
          <div class="fw-bold"><?= e($import['company_name']) ?></div>
          <div class="text-muted small"><?= e($import['cnpj']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small mb-1"><i class="bi bi-diagram-3 me-1"></i>Unidade</div>
          <div class="fw-bold"><?= e($import['unit_code']) ?> - <?= e($import['unit_name']) ?></div>
          <div class="text-muted small">detectada pelo arquivo</div>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small mb-1"><i class="bi bi-calendar me-1"></i>Período</div>
          <div class="fw-bold fs-4"><?= month_short((int)$import['month']) ?>/<?= $import['year'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small mb-1"><i class="bi bi-list-ol me-1"></i>Linhas</div>
          <div class="fw-bold fs-4 text-primary"><?= number_format($totalRows, 0, ',', '.') ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small mb-1"><i class="bi bi-file-earmark me-1"></i>Arquivo</div>
          <div class="fw-semibold text-truncate" title="<?= e($import['original_filename']) ?>"><?= e($import['original_filename']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
      <div class="fw-semibold"><i class="bi bi-list-tree me-1"></i>Estrutura da DRE</div>
      <input type="search" id="treeSearch" class="form-control form-control-sm" style="max-width:260px" placeholder="Buscar conta...">
    </div>
    <div class="table-responsive" style="max-height:65vh;overflow:auto">
      <table class="table table-sm table-hover align-middle mb-0" id="balanceteTree">
        <thead class="table-light sticky-top">
          <tr>
            <th style="width:90px">Código</th>
            <th>Descrição</th>
            <th style="width:160px" class="text-end">Movimento</th>
            <th style="width:70px">Tipo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($treeRows as $row): ?>
          <?php
            $indent = (int)$row['indentation_level'];
            $hasChildren = !empty($row['has_children']);
            $movement = (float)$row['movement_value'];
            $type = (string)$row['movement_type'];
            $isDb = $type === 'DB';
            $isCr = $type === 'CR';
          ?>
          <tr class="<?= $hasChildren ? 'table-light fw-semibold' : '' ?> <?= $row['is_analytical'] ? 'text-muted' : '' ?>"
              data-search="<?= e(mb_strtolower($row['account_code'] . ' ' . $row['account_description'])) ?>">
            <td><code><?= e($row['account_code']) ?></code></td>
            <td style="padding-left: <?= 12 + ($indent * 22) ?>px">
              <?php if ($hasChildren): ?>
                <i class="bi bi-caret-down-fill text-secondary me-1"></i>
              <?php else: ?>
                <span class="d-inline-block me-1" style="width:14px"></span>
              <?php endif; ?>
              <?= e($row['account_description']) ?>
            </td>
            <td class="text-end <?= $isDb ? 'text-danger' : ($isCr ? 'text-success' : '') ?>">
              <?= $movement != 0.0 ? format_brl($movement) : '<span class="text-muted">-</span>' ?>
            </td>
            <td>
              <span class="badge bg-<?= $isCr ? 'success' : ($isDb ? 'danger' : 'secondary') ?>">
                <?= $type !== '' ? e($type) : '-' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex gap-3 mt-4 justify-content-end">
    <?php if ($import['status'] === 'pending' || $import['status'] === 'processing'): ?>
    <form method="POST" action="<?= url('imports/' . $import['id'] . '/delete') ?>"
          onsubmit="return confirm('Cancelar esta importação?')">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline-danger">
        <i class="bi bi-x-circle me-1"></i>Cancelar Importação
      </button>
    </form>
    <form method="POST" action="<?= url('imports/' . $import['id'] . '/confirm') ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-success btn-lg">
        <i class="bi bi-check-circle me-2"></i>Confirmar DRE
      </button>
    </form>
    <?php else: ?>
    <a href="<?= url('dre') ?>" class="btn btn-primary">
      <i class="bi bi-list-tree me-1"></i>Ver DRE
    </a>
    <?php endif; ?>
  </div>
</div>

<?php $extraJs = <<<'JS'
<script>
document.getElementById('treeSearch').addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  document.querySelectorAll('#balanceteTree tbody tr').forEach(row => {
    row.style.display = !q || row.dataset.search.includes(q) ? '' : 'none';
  });
});
</script>
JS; ?>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>
