<?php
$pageTitle = 'Simulacao';
$mainClass = 'app-main app-main-dre';
$hideFooter = true;

$formatSigned = static function (float $value): string {
    if (abs($value) < 0.005) {
        return '<span class="dre-zero">-</span>';
    }

    $formatted = format_brl(abs($value));
    return $value < 0
        ? '<span class="dre-value-negative">(' . $formatted . ')</span>'
        : '<span class="dre-value-positive">' . $formatted . '</span>';
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
$classificationLabel = static function (string $classification): string {
    return match ($classification) {
        'revenue' => 'Receita',
        'variable' => 'Variavel',
        'fixed' => 'Fixa',
        'non_recurring' => 'Nao recorr.',
        'non_operational' => 'Nao oper.',
        default => '-',
    };
};
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

<div class="container-fluid py-3 px-3 dre-page simulation-dre-page">
  <form id="simulationAdjustments" method="POST" action="<?= url('simulations/' . (int)$simulation['id'] . '/adjustments') ?>">
    <?= csrf_field() ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body py-2">
        <div class="d-flex align-items-center justify-content-between gap-3">
          <div class="min-w-0">
            <div class="d-flex align-items-center gap-2">
              <a href="<?= url('simulations') ?>" class="btn btn-outline-secondary btn-sm" title="Voltar">
                <i class="bi bi-arrow-left"></i>
              </a>
              <div>
                <div class="fw-bold fs-5 lh-sm"><?= e($simulation['name']) ?></div>
                <small class="text-muted">
                  <?= e($scopeLabel($simulation)) ?> -
                  <?= e(month_short((int)$simulation['month_start'])) ?>/<?= (int)$simulation['year'] ?>
                  a <?= e(month_short((int)$simulation['month_end'])) ?>/<?= (int)$simulation['year'] ?>
                </small>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
            <div class="simulation-summary-pill">
              <span>Real</span>
              <strong><?= $formatSigned((float)$summary['base_total']) ?></strong>
            </div>
            <div class="simulation-summary-pill">
              <span>Simulado</span>
              <strong><?= $formatSigned((float)$summary['simulated_total']) ?></strong>
            </div>
            <div class="simulation-summary-pill">
              <span>Impacto</span>
              <strong><?= $formatSigned((float)$summary['delta_total']) ?></strong>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-save me-1"></i>Salvar
            </button>
          </div>
        </div>
      </div>
    </div>

    <?php if (empty($matrixRows)): ?>
    <div class="card shadow-sm">
      <div class="text-center text-muted py-5">Sem DRE confirmada para a base desta simulacao.</div>
    </div>
    <?php else: ?>
    <div class="card shadow-sm dre-report-card">
      <div class="dre-report-toolbar px-3 py-2 border-bottom">
        <div>
          <div class="fw-bold"><i class="bi bi-file-earmark-spreadsheet me-1"></i>DRE simulada</div>
          <div class="text-muted small">Mesma base da DRE real; ajustes ficam somente nesta simulacao</div>
        </div>
        <div class="text-muted small">
          <i class="bi bi-pencil-square me-1"></i>Clique no lapis ou de duplo clique na linha
        </div>
      </div>

      <div class="dre-report-scroll">
        <table class="table table-sm align-middle mb-0 dre-report-table simulation-dre-table" id="simulationDreTable">
          <thead>
            <tr>
              <th class="dre-sticky dre-code-col">Codigo</th>
              <th class="dre-sticky dre-desc-col">Descricao</th>
              <?php foreach ($months as $month): ?>
              <th class="text-end dre-money-col dre-month-col"><?= e($month['label']) ?></th>
              <?php endforeach; ?>
              <th class="text-end dre-money-col">Acumulado</th>
              <th class="text-end dre-money-col">Acumulado <?= (int)$simulation['year'] - 1 ?></th>
              <th class="text-end dre-money-col">Media</th>
              <th class="text-end dre-money-col">Media <?= (int)$simulation['year'] - 1 ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($matrixRows as $row): ?>
            <?php
              if (!empty($row['hide_duplicate'])) continue;
              $hash = (string)$row['simulation_hash'];
              $adjustment = $row['simulation_adjustment'] ?? null;
              $indent = (int)($row['indentation_level'] ?? 0);
              $hasChildren = !empty($row['has_children']);
              $hasChange = !empty($row['has_simulation_change']);
              $rowKind = !empty($row['is_section']) ? 'section' : ($hasChildren ? 'group' : 'item');
              $visualKind = $rowKind;
              if ($rowKind === 'group' && $indent >= 3) {
                  $visualKind = 'account-group';
              } elseif ($rowKind === 'item' && !empty($row['is_analytical'])) {
                  $visualKind = 'analytical';
              }
              $classification = $classificationValue($adjustment);
              $note = (string)($adjustment['note'] ?? '');
            ?>
            <tr class="dre-tree-row dre-row-<?= e($rowKind) ?> simulation-dre-row<?= $hasChange ? ' is-changed' : '' ?>"
                data-simulation-row
                data-kind="<?= e($visualKind) ?>"
                data-level="<?= $indent ?>"
                data-group="<?= $hasChildren ? '1' : '0' ?>"
                data-hash="<?= e($hash) ?>"
                data-code="<?= e($row['account_code']) ?>"
                data-description="<?= e($row['account_description']) ?>"
                data-real="<?= e(format_brl((float)$row['acumulado'])) ?>"
                data-simulated="<?= e(format_brl((float)$row['simulated_acumulado'])) ?>">
              <td class="dre-sticky dre-code-col">
                <code><?= e($row['account_code']) ?></code>
                <input type="hidden" name="row_key[<?= e($hash) ?>]" value="<?= e($row['row_key']) ?>">
                <input type="hidden" name="account_code[<?= e($hash) ?>]" value="<?= e($row['account_code']) ?>">
                <input type="hidden" name="account_description[<?= e($hash) ?>]" value="<?= e($row['account_description']) ?>">
                <input type="hidden" name="indentation_level[<?= e($hash) ?>]" value="<?= $indent ?>">
                <input type="hidden" name="adjustment_mode[<?= e($hash) ?>]" value="<?= e($modeValue($adjustment)) ?>" data-field="mode">
                <input type="hidden" name="adjustment_value[<?= e($hash) ?>]" value="<?= e($moneyInput($adjustment)) ?>" data-field="amount">
                <input type="hidden" name="adjustment_percent[<?= e($hash) ?>]" value="<?= e($percentInput($adjustment)) ?>" data-field="percent">
                <input type="hidden" name="classification[<?= e($hash) ?>]" value="<?= e($classification) ?>" data-field="classification">
                <input type="hidden" name="note[<?= e($hash) ?>]" value="<?= e($note) ?>" data-field="note">
              </td>
              <td class="dre-sticky dre-desc-col">
                <div class="dre-label" style="--level: <?= $indent ?>">
                  <?php if ($hasChildren): ?>
                  <i class="bi bi-chevron-down text-muted"></i>
                  <?php else: ?>
                  <span class="dre-leaf"></span>
                  <?php endif; ?>
                  <span class="dre-label-text"><?= e($row['account_description']) ?></span>
                  <button type="button" class="simulation-edit-btn" title="Editar ajuste">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <span class="simulation-row-tags">
                    <span data-display="classification"><?= e($classificationLabel($classification)) ?></span>
                    <span data-display="note" title="<?= e($note) ?>"><?= $note !== '' ? '<i class="bi bi-chat-left-text"></i>' : '' ?></span>
                  </span>
                </div>
              </td>
              <?php foreach ($months as $month): ?>
              <?php
                $monthValue = (float)($row['values'][$month['key']] ?? 0.0);
                $percentual = (float)($row['percentuais'][$month['key']] ?? 0.0);
              ?>
              <td class="text-end dre-money dre-month-col <?= $monthValue < 0 ? 'is-negative' : ($monthValue > 0 ? 'is-positive' : '') ?>">
                <div><?= $formatSigned($monthValue) ?></div>
                <?php if (abs($percentual) >= 0.01): ?>
                <div class="dre-percent"><?= number_format($percentual, 2, ',', '.') ?>%</div>
                <?php endif; ?>
              </td>
              <?php endforeach; ?>
              <?php
                $simulatedAcumulado = (float)($row['simulated_acumulado'] ?? $row['acumulado']);
                $simulatedDelta = (float)($row['simulated_delta'] ?? 0.0);
                $simulatedMedia = (float)($row['simulated_media'] ?? $row['media']);
              ?>
              <td class="text-end dre-money <?= $simulatedAcumulado < 0 ? 'is-negative' : ($simulatedAcumulado > 0 ? 'is-positive' : '') ?>">
                <div><?= $formatSigned($simulatedAcumulado) ?></div>
                <?php if (abs($simulatedDelta) >= 0.005): ?>
                <div class="dre-percent simulation-impact"><?= $formatSigned($simulatedDelta) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end dre-money <?= ((float)($row['previous_year_acumulado'] ?? 0)) < 0 ? 'is-negative' : (((float)($row['previous_year_acumulado'] ?? 0)) > 0 ? 'is-positive' : '') ?>">
                <div><?= $formatSigned((float)($row['previous_year_acumulado'] ?? 0)) ?></div>
                <?php if (abs((float)($row['previous_year_acumulado_percentual'] ?? 0)) >= 0.01): ?>
                <div class="dre-percent"><?= number_format((float)$row['previous_year_acumulado_percentual'], 2, ',', '.') ?>%</div>
                <?php endif; ?>
              </td>
              <td class="text-end dre-money <?= $simulatedMedia < 0 ? 'is-negative' : ($simulatedMedia > 0 ? 'is-positive' : '') ?>">
                <div><?= $formatSigned($simulatedMedia) ?></div>
              </td>
              <td class="text-end dre-money <?= ((float)($row['previous_year_media'] ?? 0)) < 0 ? 'is-negative' : (((float)($row['previous_year_media'] ?? 0)) > 0 ? 'is-positive' : '') ?>">
                <div><?= $formatSigned((float)($row['previous_year_media'] ?? 0)) ?></div>
                <?php if (abs((float)($row['previous_year_media_percentual'] ?? 0)) >= 0.01): ?>
                <div class="dre-percent"><?= number_format((float)$row['previous_year_media_percentual'], 2, ',', '.') ?>%</div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="dre-report-scroll-spacer"></div>
      </div>
      <div class="dre-column-scrollbar" aria-label="Rolar colunas financeiras" tabindex="0">
        <div class="dre-column-scrollbar-spacer"></div>
      </div>
    </div>
    <?php endif; ?>
  </form>
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
            <label class="form-label fw-semibold">Valor simulado</label>
            <input type="text" class="form-control text-end" id="simulationEditFinal" inputmode="decimal" placeholder="Ex: 0,00">
          </div>
          <div class="col-12">
            <div class="btn-group btn-group-sm" role="group" aria-label="Ajustes rapidos">
              <button type="button" class="btn btn-outline-secondary" id="simulationUseReal">Usar real</button>
              <button type="button" class="btn btn-outline-secondary" id="simulationZeroValue">Zerar</button>
            </div>
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
  const table = document.getElementById('simulationDreTable');
  if (!form || !table) return;

  const scrollWrap = table.closest('.dre-report-scroll');
  const scrollSpacer = scrollWrap?.querySelector('.dre-report-scroll-spacer');
  const columnScrollbar = document.querySelector('.dre-column-scrollbar');
  const columnScrollbarSpacer = columnScrollbar?.querySelector('.dre-column-scrollbar-spacer');
  const modalEl = document.getElementById('simulationEditModal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const title = document.getElementById('simulationEditTitle');
  const subtitle = document.getElementById('simulationEditSubtitle');
  const real = document.getElementById('simulationEditReal');
  const finalValue = document.getElementById('simulationEditFinal');
  const useReal = document.getElementById('simulationUseReal');
  const zeroValue = document.getElementById('simulationZeroValue');
  const classification = document.getElementById('simulationEditClassification');
  const note = document.getElementById('simulationEditNote');
  const apply = document.getElementById('simulationApplyAdjustment');
  const clear = document.getElementById('simulationClearAdjustment');
  let activeRow = null;
  let syncingColumnScroll = false;

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

  function normalizeMoney(value) {
    value = String(value || '').trim().replace(/[R$\s]/g, '');
    if (!value) return '';
    if (value.startsWith('(') && value.endsWith(')')) {
      value = '-' + value.slice(1, -1);
    }
    value = value.replace(/\./g, '').replace(',', '.');
    const number = Number(value);
    return Number.isFinite(number) ? number.toFixed(2) : '';
  }

  function parseMoney(value) {
    const normalized = normalizeMoney(value);
    return normalized === '' ? null : Number(normalized);
  }

  function formatMoney(number) {
    if (!Number.isFinite(number)) return '';
    return number.toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function normalizeMoneyInput(input) {
    const parsed = parseMoney(input.value);
    if (parsed !== null) {
      input.value = formatMoney(parsed);
    }
  }

  function updateScrollState() {
    if (!scrollWrap) return;
    scrollWrap.classList.toggle('is-scrolled-x', scrollWrap.scrollLeft > 1);
    scrollWrap.classList.toggle('is-scrolled-y', scrollWrap.scrollTop > 1);
  }

  function updateColumnScrollbar() {
    if (!scrollWrap || !scrollSpacer || !columnScrollbar || !columnScrollbarSpacer) return;
    const fixedWidth = parseFloat(getComputedStyle(columnScrollbar).marginLeft) || 0;
    const firstMoneyColumn = table.querySelector('thead th:not(.dre-sticky)');
    const columnWidth = firstMoneyColumn?.getBoundingClientRect().width || 116;
    const financialWidth = Math.max(0, table.scrollWidth - fixedWidth);
    const alignRange = Math.max(0, financialWidth - columnWidth);

    scrollSpacer.style.width = `${scrollWrap.clientWidth + alignRange}px`;
    columnScrollbarSpacer.style.width = `${columnScrollbar.clientWidth + alignRange}px`;
    columnScrollbar.classList.toggle('is-hidden', alignRange <= 1);

    if (!syncingColumnScroll) {
      syncingColumnScroll = true;
      columnScrollbar.scrollLeft = scrollWrap.scrollLeft;
      syncingColumnScroll = false;
    }
  }

  function openEditor(row) {
    activeRow = row;
    title.textContent = row.dataset.description || 'Editar ajuste';
    subtitle.textContent = row.dataset.code ? `Codigo ${row.dataset.code}` : '';
    real.value = row.dataset.real || '';
    finalValue.value = row.dataset.simulated || row.dataset.real || '';
    classification.value = field(row, 'classification')?.value || 'none';
    note.value = field(row, 'note')?.value || '';
    modal.show();
    setTimeout(() => {
      finalValue.focus();
      finalValue.select();
    }, 160);
  }

  function applyEditor() {
    if (!activeRow) return;
    normalizeMoneyInput(finalValue);
    const finalValueText = finalValue.value.trim();
    const noteValue = note.value.trim();
    const hasValueChange = normalizeMoney(finalValueText) !== normalizeMoney(activeRow.dataset.real || '');
    const hasContent = hasValueChange || noteValue || classification.value !== 'none';

    field(activeRow, 'mode').value = hasValueChange ? 'override' : 'amount';
    field(activeRow, 'amount').value = hasValueChange ? finalValueText : '';
    field(activeRow, 'percent').value = '';
    field(activeRow, 'classification').value = classification.value;
    field(activeRow, 'note').value = noteValue;
    activeRow.classList.toggle('is-changed', Boolean(hasContent));
    activeRow.querySelector('[data-display="classification"]').textContent = classificationLabels[classification.value] || '-';
    activeRow.querySelector('[data-display="note"]').innerHTML = noteValue ? '<i class="bi bi-chat-left-text"></i>' : '';
    activeRow.querySelector('[data-display="note"]').title = noteValue;
    modal.hide();
  }

  function clearEditor() {
    finalValue.value = '';
    classification.value = 'none';
    note.value = '';
    applyEditor();
  }

  table.querySelectorAll('[data-simulation-row]').forEach(row => {
    row.addEventListener('click', event => {
      if (event.target.closest('button, a, input, select, textarea')) return;
      openEditor(row);
    });
    row.querySelector('.simulation-edit-btn')?.addEventListener('click', event => {
      event.stopPropagation();
      openEditor(row);
    });
  });

  scrollWrap?.addEventListener('scroll', () => {
    updateScrollState();
    if (!columnScrollbar || syncingColumnScroll) return;
    syncingColumnScroll = true;
    columnScrollbar.scrollLeft = scrollWrap.scrollLeft;
    syncingColumnScroll = false;
  }, { passive: true });
  columnScrollbar?.addEventListener('scroll', () => {
    if (!scrollWrap || syncingColumnScroll) return;
    syncingColumnScroll = true;
    scrollWrap.scrollLeft = columnScrollbar.scrollLeft;
    syncingColumnScroll = false;
    updateScrollState();
  }, { passive: true });
  window.addEventListener('resize', () => {
    updateScrollState();
    updateColumnScrollbar();
  });
  if (window.ResizeObserver && scrollWrap) {
    const resizeObserver = new ResizeObserver(() => {
      updateScrollState();
      updateColumnScrollbar();
    });
    resizeObserver.observe(scrollWrap);
    resizeObserver.observe(table);
  }

  useReal.addEventListener('click', () => {
    finalValue.value = real.value;
    finalValue.focus();
  });
  zeroValue.addEventListener('click', () => {
    finalValue.value = '0,00';
    finalValue.focus();
  });
  finalValue.addEventListener('blur', () => normalizeMoneyInput(finalValue));
  finalValue.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
      event.preventDefault();
      applyEditor();
    }
  });
  apply.addEventListener('click', applyEditor);
  clear.addEventListener('click', clearEditor);
  updateColumnScrollbar();

  form.addEventListener('submit', () => {
    form.querySelectorAll('[data-simulation-row]').forEach(row => {
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
