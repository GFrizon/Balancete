<?php $pageTitle = 'Confirmar Substituição'; ?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<div class="container py-4" style="max-width:680px">
  <div class="d-flex align-items-center mb-4">
    <a href="<?= url('imports/create') ?>" class="btn btn-sm btn-outline-secondary me-3">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Substituir Importação?</h4>
      <small class="text-muted">Já existe um balancete para este período</small>
    </div>
  </div>

  <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div>
      <strong>Atenção:</strong> ao confirmar, o balancete anterior será <strong>removido</strong> e substituído pelo novo.
      Esta ação não pode ser desfeita.
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <div class="mb-4">
        <h5 class="fw-semibold mb-3">Importação atual</h5>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="text-muted small">Empresa</div>
            <div class="fw-semibold"><?= e($companyName) ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Unidade</div>
            <div class="fw-semibold"><?= e($unitCode) ?> — <?= e($unitName) ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Período</div>
            <div class="fw-semibold"><?= MONTHS_PT[(int)$month] ?? $month ?>/<?= (int)$year ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Arquivo</div>
            <div class="fw-semibold"><?= e($origName) ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Linhas</div>
            <div class="fw-semibold"><?= (int)$rowsCount ?></div>
          </div>
        </div>
      </div>

      <form method="POST" action="<?= url('imports/create') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="confirm_token" value="<?= e($token) ?>">

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-warning btn-lg flex-fill">
            <i class="bi bi-arrow-repeat me-2"></i>Sim, Substituir
          </button>
          <a href="<?= url('imports/create') ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>
