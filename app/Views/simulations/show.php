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
              <th class="text-end simulation-money-col">Simulado</th>
              <th class="text-end simulation-money-col">Dif.</th>
              <th class="simulation-type-col">Tipo</th>
              <th class="simulation-note-col">Nota</th>
              <th class="text-end simulation-action-col"></th>
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
            <tr class="simulation-row simulation-row-<?= e($kind) ?><?= $hasChange ? ' is-changed' : '' ?>"
                data-simulation-row
                data-hash="<?= e($hash) ?>"
                data-code="<?= e($row['account_code']) ?>"
                data-description="<?= e($row['account_description']) ?>"
                data-real="<?= e(format_brl((float)$row['acumulado'])) ?>"
                data-simulated="<?= e(format_brl((float)$row['simulated_acumulado'])) ?>">
              <td class="simulation-code-col">
                <code><?= e($row['account_code']) ?></code>
                <input type="hidden" name="row_key[<?= e($hash) ?>]" value="<?= e($row['row_key']) ?>">
                <input type="hidden" name="account_code[<?= e($hash) ?>]" value="<?= e($row['account_code']) ?>">
                <input type="hidden" name="account_description[<?= e($hash) ?>]" value="<?= e($row['account_description']) ?>">
                <input type="hidden" name="indentation_level[<?= e($hash) ?>]" value="<?= $indent ?>">
                <input type="hidden" name="adjustment_mode[<?= e($hash) ?>]" value="<?= e($modeValue($adjustment)) ?>" data-field="mode">
                <input type="hidden" name="adjustment_value[<?= e($hash) ?>]" value="<?= e($moneyInput($adjustment)) ?>" data-field="amount">
                <input type="hidden" name="adjustment_percent[<?= e($hash) ?>]" value="<?= e($percentInput($adjustment)) ?>" data-field="percent">
                <input type="hidden" name="classification[<?= e($hash) ?>]" value="<?= e($classificationValue($adjustment)) ?>" data-field="classification">
                <input type="hidden" name="note[<?= e($hash) ?>]" value="<?= e((string)($adjustment['note'] ?? '')) ?>" data-field="note">
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
              <td class="text-end simulation-money-col"><?= $formatSigned((float)$row['simulated_acumulado']) ?></td>
              <td class="text-end simulation-money-col"><?= $formatSigned((float)$row['simulated_delta']) ?></td>
              <td class="simulation-type-col" data-display="classification">
                <?= e(match ($classificationValue($adjustment)) {
                    'revenue' => 'Receita',
                    'variable' => 'Variavel',
                    'fixed' => 'Fixa',
                    'non_recurring' => 'Nao recorr.',
                    'non_operational' => 'Nao oper.',
                    default => '-',
                }) ?>
              </td>
              <td class="simulation-note-col" data-display="note">
                <?php if (!empty($adjustment['note'])): ?>
                <span title="<?= e((string)$adjustment['note']) ?>"><?= e((string)$adjustment['note']) ?></span>
                <?php else: ?>
                <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-end simulation-action-col">
                <button type="button" class="btn btn-outline-secondary btn-sm simulation-edit-btn" title="Editar ajuste">
                  <i class="bi bi-pencil"></i>
                </button>
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

<div class="modal fade" id="simulationEditModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="simulationEditTitle">Editar ajuste</h5>
          <small class="text-muted" id="simulationEditSubtitle"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label fw-semibold">Real</label>
            <input type="text" class="form-control" id="simulationEditReal" readonly>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Simulado atual</label>
            <input type="text" class="form-control" id="simulationEditSimulated" readonly>
          </div>
          <div class="col-md-5">
            <label class="form-label fw-semibold">Modo</label>
            <select class="form-select" id="simulationEditMode">
              <option value="amount">Somar/subtrair</option>
              <option value="percent">% do real</option>
              <option value="override">Valor final</option>
            </select>
          </div>
          <div class="col-md-7" id="simulationEditAmountGroup">
            <label class="form-label fw-semibold">Valor R$</label>
            <input type="text" class="form-control text-end" id="simulationEditAmount" placeholder="Ex: -500.000,00">
          </div>
          <div class="col-md-7" id="simulationEditPercentGroup">
            <label class="form-label fw-semibold">Percentual</label>
            <input type="text" class="form-control text-end" id="simulationEditPercent" placeholder="Ex: 110">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Tipo</label>
            <select class="form-select" id="simulationEditClassification">
              <option value="none">-</option>
              <option value="revenue">Receita</option>
              <option value="variable">Variavel</option>
              <option value="fixed">Fixa</option>
              <option value="non_recurring">Nao recorrente</option>
              <option value="non_operational">Nao operacional</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Nota</label>
            <textarea class="form-control" id="simulationEditNote" rows="3" placeholder="Motivo do ajuste"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger me-auto" id="simulationClearAdjustment">
          <i class="bi bi-eraser me-1"></i>Limpar
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="simulationApplyAdjustment">
          <i class="bi bi-check-lg me-1"></i>Aplicar
        </button>
      </div>
    </div>
  </div>
</div>

<?php $extraJs = <<<'JS'
<script>
(() => {
  const form = document.getElementById('simulationAdjustments');
  if (!form) return;
  const modalEl = document.getElementById('simulationEditModal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const title = document.getElementById('simulationEditTitle');
  const subtitle = document.getElementById('simulationEditSubtitle');
  const real = document.getElementById('simulationEditReal');
  const simulated = document.getElementById('simulationEditSimulated');
  const mode = document.getElementById('simulationEditMode');
  const amount = document.getElementById('simulationEditAmount');
  const percent = document.getElementById('simulationEditPercent');
  const amountGroup = document.getElementById('simulationEditAmountGroup');
  const percentGroup = document.getElementById('simulationEditPercentGroup');
  const classification = document.getElementById('simulationEditClassification');
  const note = document.getElementById('simulationEditNote');
  const apply = document.getElementById('simulationApplyAdjustment');
  const clear = document.getElementById('simulationClearAdjustment');
  let activeRow = null;

  const classificationLabels = {
    none: '-',
    revenue: 'Receita',
    variable: 'Variavel',
    fixed: 'Fixa',
    non_recurring: 'Nao recorr.',
    non_operational: 'Nao oper.',
  };

  function field(row, name) {
    return row.querySelector(`[data-field="${name}"]`);
  }

  function updateModeVisibility() {
    const isPercent = mode.value === 'percent';
    percentGroup.hidden = !isPercent;
    amountGroup.hidden = isPercent;
  }

  function openEditor(row) {
    activeRow = row;
    title.textContent = row.dataset.description || 'Editar ajuste';
    subtitle.textContent = row.dataset.code ? `Codigo ${row.dataset.code}` : '';
    real.value = row.dataset.real || '';
    simulated.value = row.dataset.simulated || '';
    mode.value = field(row, 'mode')?.value || 'amount';
    amount.value = field(row, 'amount')?.value || '';
    percent.value = field(row, 'percent')?.value || '';
    classification.value = field(row, 'classification')?.value || 'none';
    note.value = field(row, 'note')?.value || '';
    updateModeVisibility();
    modal.show();
  }

  function applyEditor() {
    if (!activeRow) return;
    const amountValue = amount.value.trim();
    const percentValue = percent.value.trim();
    const noteValue = note.value.trim();
    const hasContent = amountValue || percentValue || noteValue || classification.value !== 'none';

    field(activeRow, 'mode').value = mode.value;
    field(activeRow, 'amount').value = amountValue;
    field(activeRow, 'percent').value = percentValue;
    field(activeRow, 'classification').value = classification.value;
    field(activeRow, 'note').value = noteValue;
    activeRow.classList.toggle('is-changed', Boolean(hasContent));
    activeRow.querySelector('[data-display="classification"]').textContent = classificationLabels[classification.value] || '-';
    activeRow.querySelector('[data-display="note"]').textContent = noteValue || '-';
    modal.hide();
  }

  function clearEditor() {
    mode.value = 'amount';
    amount.value = '';
    percent.value = '';
    classification.value = 'none';
    note.value = '';
    applyEditor();
  }

  form.querySelectorAll('[data-simulation-row]').forEach(row => {
    row.addEventListener('dblclick', event => {
      if (event.target.closest('button, a, input, select, textarea')) return;
      openEditor(row);
    });
    row.querySelector('.simulation-edit-btn')?.addEventListener('click', () => openEditor(row));
  });

  mode.addEventListener('change', updateModeVisibility);
  apply.addEventListener('click', applyEditor);
  clear.addEventListener('click', clearEditor);

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
