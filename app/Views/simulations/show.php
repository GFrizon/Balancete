<?php
$pageTitle = 'Simulacao';
$mainClass = 'app-main';

$formatSigned = static function (float $value): string {
    if (abs($value) < 0.005) {
        return '<span class="text-muted">-</span>';
    }

    $formatted = format_brl(abs($value));
    return $value < 0
        ? '<span class="text-danger">(' . $formatted . ')</span>'
        : '<span class="text-success">' . $formatted . '</span>';
};

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

$modeValue = static fn (?array $adjustment): string => (string)($adjustment['adjustment_mode'] ?? 'amount');
$classificationValue = static fn (?array $adjustment): string => (string)($adjustment['classification'] ?? 'none');
$moneyInput = static function (?array $adjustment): string {
    if (!$adjustment || ($adjustment['adjustment_mode'] ?? 'amount') === 'percent' || $adjustment['adjustment_value'] === null) {
        return '';
    }

    return number_format((float)$adjustment['adjustment_value'], 2, ',', '.');
};
$percentInput = static function (?array $adjustment): string {
    if (!$adjustment || ($adjustment['adjustment_mode'] ?? '') !== 'percent' || $adjustment['adjustment_percent'] === null) {
        return '';
    }

    return number_format((float)$adjustment['adjustment_percent'], 2, ',', '.');
};
?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<div class="container-fluid py-3 px-3 simulation-page">
  <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <a href="<?= url('simulations') ?>" class="btn btn-outline-secondary btn-sm" title="Voltar">
          <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="fw-bold mb-0"><?= e($simulation['name']) ?></h4>
      </div>
      <small class="text-muted">
        <?= e($scopeLabel($simulation)) ?> - <?= e(month_short((int)$simulation['month_start'])) ?>/<?= (int)$simulation['year'] ?>
        a <?= e(month_short((int)$simulation['month_end'])) ?>/<?= (int)$simulation['year'] ?>
      </small>
    </div>
    <button type="submit" form="simulationAdjustments" class="btn btn-primary">
      <i class="bi bi-save me-1"></i>Salvar ajustes
    </button>
  </div>

  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body py-2">
          <div class="text-muted small">Resultado real</div>
          <div class="fw-bold fs-6"><?= $formatSigned((float)$summary['base_total']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body py-2">
          <div class="text-muted small">Resultado simulado</div>
          <div class="fw-bold fs-6"><?= $formatSigned((float)$summary['simulated_total']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body py-2">
          <div class="text-muted small">Impacto</div>
          <div class="fw-bold fs-6"><?= $formatSigned((float)$summary['delta_total']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body py-2">
          <div class="text-muted small">Linhas impactadas</div>
          <div class="fw-bold fs-6"><?= (int)$summary['changed_rows'] ?></div>
        </div>
      </div>
    </div>
  </div>

  <?php if (empty($matrixRows)): ?>
  <div class="card shadow-sm">
    <div class="text-center text-muted py-5">Sem DRE confirmada para a base desta simulacao.</div>
  </div>
  <?php else: ?>
  <form id="simulationAdjustments" method="POST" action="<?= url('simulations/' . (int)$simulation['id'] . '/adjustments') ?>">
    <?= csrf_field() ?>
    <div class="card shadow-sm simulation-editor-card">
      <div class="table-responsive simulation-table-wrap">
        <table class="table table-sm align-middle mb-0 simulation-table">
          <thead>
            <tr>
              <th class="simulation-code-col">Codigo</th>
              <th class="simulation-desc-col">Descricao</th>
              <th class="text-end simulation-money-col">Real</th>
              <th class="simulation-mode-col">Modo</th>
              <th class="text-end simulation-input-col">Ajuste R$</th>
              <th class="text-end simulation-input-col">% Real</th>
              <th class="text-end simulation-money-col">Simulado</th>
              <th class="text-end simulation-money-col">Dif.</th>
              <th class="simulation-type-col">Tipo</th>
              <th class="simulation-note-col">Nota</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($matrixRows as $row): ?>
            <?php
              if (!empty($row['hide_duplicate'])) continue;
              $hash = (string)$row['simulation_hash'];
              $adjustment = $row['simulation_adjustment'] ?? null;
              $indent = (int)($row['indentation_level'] ?? 0);
              $hasChange = !empty($row['has_simulation_change']);
              $kind = !empty($row['is_section']) ? 'section' : (!empty($row['has_children']) ? 'group' : 'item');
            ?>
            <tr class="simulation-row simulation-row-<?= e($kind) ?><?= $hasChange ? ' is-changed' : '' ?>" data-simulation-row>
              <td class="simulation-code-col">
                <code><?= e($row['account_code']) ?></code>
                <input type="hidden" name="row_key[<?= e($hash) ?>]" value="<?= e($row['row_key']) ?>">
                <input type="hidden" name="account_code[<?= e($hash) ?>]" value="<?= e($row['account_code']) ?>">
                <input type="hidden" name="account_description[<?= e($hash) ?>]" value="<?= e($row['account_description']) ?>">
                <input type="hidden" name="indentation_level[<?= e($hash) ?>]" value="<?= $indent ?>">
              </td>
              <td class="simulation-desc-col">
                <div class="simulation-label" style="--level: <?= $indent ?>">
                  <?= !empty($row['has_children']) ? '<i class="bi bi-chevron-down text-muted"></i>' : '<span class="simulation-leaf"></span>' ?>
                  <span><?= e($row['account_description']) ?></span>
                  <?php if ($hasChange): ?>
                  <i class="bi bi-pencil-square text-primary ms-1"></i>
                  <?php endif; ?>
                </div>
              </td>
              <td class="text-end simulation-money-col"><?= $formatSigned((float)$row['acumulado']) ?></td>
              <td class="simulation-mode-col">
                <select name="adjustment_mode[<?= e($hash) ?>]" class="form-select form-select-sm simulation-mode">
                  <option value="amount" <?= $modeValue($adjustment) === 'amount' ? 'selected' : '' ?>>Somar</option>
                  <option value="percent" <?= $modeValue($adjustment) === 'percent' ? 'selected' : '' ?>>% Real</option>
                  <option value="override" <?= $modeValue($adjustment) === 'override' ? 'selected' : '' ?>>Final</option>
                </select>
              </td>
              <td class="simulation-input-col">
                <input type="text" name="adjustment_value[<?= e($hash) ?>]" class="form-control form-control-sm text-end" value="<?= e($moneyInput($adjustment)) ?>">
              </td>
              <td class="simulation-input-col">
                <input type="text" name="adjustment_percent[<?= e($hash) ?>]" class="form-control form-control-sm text-end" value="<?= e($percentInput($adjustment)) ?>">
              </td>
              <td class="text-end simulation-money-col"><?= $formatSigned((float)$row['simulated_acumulado']) ?></td>
              <td class="text-end simulation-money-col"><?= $formatSigned((float)$row['simulated_delta']) ?></td>
              <td class="simulation-type-col">
                <select name="classification[<?= e($hash) ?>]" class="form-select form-select-sm">
                  <option value="none" <?= $classificationValue($adjustment) === 'none' ? 'selected' : '' ?>>-</option>
                  <option value="revenue" <?= $classificationValue($adjustment) === 'revenue' ? 'selected' : '' ?>>Receita</option>
                  <option value="variable" <?= $classificationValue($adjustment) === 'variable' ? 'selected' : '' ?>>Variavel</option>
                  <option value="fixed" <?= $classificationValue($adjustment) === 'fixed' ? 'selected' : '' ?>>Fixa</option>
                  <option value="non_recurring" <?= $classificationValue($adjustment) === 'non_recurring' ? 'selected' : '' ?>>Nao recorr.</option>
                  <option value="non_operational" <?= $classificationValue($adjustment) === 'non_operational' ? 'selected' : '' ?>>Nao oper.</option>
                </select>
              </td>
              <td class="simulation-note-col">
                <input type="text" name="note[<?= e($hash) ?>]" class="form-control form-control-sm" value="<?= e((string)($adjustment['note'] ?? '')) ?>" placeholder="Nota">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>
  <?php endif; ?>
</div>

<?php $extraJs = <<<'JS'
<script>
(() => {
  const form = document.getElementById('simulationAdjustments');
  if (!form) return;

  form.addEventListener('submit', () => {
    form.querySelectorAll('[data-simulation-row]').forEach(row => {
      const mode = row.querySelector('[name^="adjustment_mode"]');
      const amount = row.querySelector('[name^="adjustment_value"]');
      const percent = row.querySelector('[name^="adjustment_percent"]');
      const classification = row.querySelector('[name^="classification"]');
      const note = row.querySelector('[name^="note"]');
      const hasContent = (amount?.value.trim() || percent?.value.trim() || note?.value.trim() || (classification?.value && classification.value !== 'none'));

      if (hasContent) return;

      row.querySelectorAll('input, select, textarea').forEach(input => {
        input.disabled = true;
      });
    });
  });
})();
</script>
JS; ?>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>
