<?php
$pageTitle = 'Balancete';
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

$trendIndicator = static function (float $current, float $previous): string {
    if (abs($current - $previous) < 0.005) {
        return '<span class="text-muted ms-1" title="Igual ao ano anterior"><i class="bi bi-dash"></i></span>';
    }

    $isHigher = $current > $previous;
    $icon = $isHigher ? 'bi-arrow-up-short' : 'bi-arrow-down-short';
    $color = $isHigher ? 'text-success' : 'text-danger';
    $label = $isHigher ? 'Maior que o ano anterior' : 'Menor que o ano anterior';

    return '<span class="' . $color . ' ms-1" title="' . $label . '"><i class="bi ' . $icon . '"></i></span>';
};

$comparisonTooltipAttrs = static function (string $label, float $current, float $previous, int $previousYear): string {
    $diff = $previous < 0 && $current >= 0
        ? $current - abs($previous)
        : $current - $previous;
    $percent = abs($previous) >= 0.005 ? ($diff / abs($previous)) * 100 : null;

    return ' data-tooltip-title="' . e($label) . '"'
        . ' data-tooltip-current="' . e(format_brl($current)) . '"'
        . ' data-tooltip-previous="' . e(format_brl($previous)) . '"'
        . ' data-tooltip-previous-label="' . e((string)$previousYear) . '"'
        . ' data-tooltip-diff="' . e(format_brl($diff)) . '"'
        . ' data-tooltip-percent="' . e($percent === null ? '-' : number_format($percent, 1, ',', '.') . '%') . '"';
};

$chartPayload = static function (array $row, array $months, int $currentYear, int $previousYear): string {
    $labels = [];
    $currentValues = [];
    $previousValues = [];

    foreach ($months as $month) {
        $labels[] = month_short((int)$month['month']);
        $currentValues[] = (float)($row['values'][$month['key']] ?? 0.0);
        $previousKey = sprintf('%04d-%02d', $previousYear, (int)$month['month']);
        $previousValues[] = (float)($row['previous_year_values'][$previousKey] ?? 0.0);
    }

    return e(json_encode([
        'code' => (string)($row['account_code'] ?? ''),
        'description' => (string)($row['account_description'] ?? ''),
        'currentYear' => $currentYear,
        'previousYear' => $previousYear,
        'labels' => $labels,
        'current' => $currentValues,
        'previous' => $previousValues,
        'currentTotal' => (float)($row['acumulado'] ?? 0.0),
        'previousTotal' => (float)($row['previous_year_acumulado'] ?? 0.0),
        'currentAverage' => (float)($row['media'] ?? 0.0),
        'previousAverage' => (float)($row['previous_year_media'] ?? 0.0),
        'unitComparison' => $row['unit_comparison'] ?? [],
    ], JSON_UNESCAPED_UNICODE));
};

$periodLabel = $fMonthStart === $fMonthEnd
    ? month_short((int)$fMonthStart) . '/' . $fYear
    : month_short((int)$fMonthStart) . '/' . $fYear . ' a ' . month_short((int)$fMonthEnd) . '/' . $fYear;
$singleMonth = $fMonthStart === $fMonthEnd && count($months) === 1;
?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<div class="container-fluid py-3 px-3 dre-page">
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <form method="GET" action="<?= url() ?>" class="row g-2 align-items-end" id="dreFilters">
        <input type="hidden" name="route" value="dre">
        <div class="col-sm-6 col-md-3">
          <label class="form-label small mb-1 fw-semibold">Empresa</label>
          <select name="company_filter" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($companyOptions as $option): ?>
            <option value="<?= e($option['value']) ?>" <?= $fCompanyFilter === $option['value'] ? 'selected' : '' ?>>
              <?= $option['kind'] === 'group' ? 'Grupo: ' : '' ?><?= e($option['label']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6 col-md-2">
          <label class="form-label small mb-1 fw-semibold">Unidade</label>
          <select name="unit_id" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($units as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $fUnit == $u['id'] ? 'selected' : '' ?>><?= e($u['code']) ?> - <?= e($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-1">
          <label class="form-label small mb-1 fw-semibold">Ano</label>
          <select name="year" class="form-select form-select-sm">
            <?php foreach ($yearsAvailable as $y): ?>
            <option value="<?= $y ?>" <?= $fYear == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-1">
          <label class="form-label small mb-1 fw-semibold">Mês inicial</label>
          <select name="month_start" class="form-select form-select-sm">
            <?php foreach (MONTHS_PT as $n => $name): ?>
            <option value="<?= $n ?>" <?= $fMonthStart == $n ? 'selected' : '' ?>><?= substr($name, 0, 3) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-1">
          <label class="form-label small mb-1 fw-semibold">Mês final</label>
          <select name="month_end" class="form-select form-select-sm">
            <?php foreach (MONTHS_PT as $n => $name): ?>
            <option value="<?= $n ?>" <?= $fMonthEnd == $n ? 'selected' : '' ?>><?= substr($name, 0, 3) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6 col-md-1">
          <a href="<?= url('dre') ?>" class="btn btn-outline-secondary btn-sm" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>
        </div>
        <div class="col-sm-6 col-md-1">
          <a href="<?= url('dre/export', ['company_id'=>$fCompany,'group_id'=>$fGroup,'unit_id'=>$fUnit,'year'=>$fYear,'month_start'=>$fMonthStart,'month_end'=>$fMonthEnd]) ?>"
             class="btn btn-outline-success btn-sm w-100">
            <i class="bi bi-download me-1"></i>CSV
          </a>
        </div>
      </form>
    </div>
  </div>

  <?php if (empty($matrixRows)): ?>
  <div class="card shadow-sm">
    <div class="text-center py-5">
      <i class="bi bi-file-earmark-text fs-1 text-muted d-block mb-3"></i>
      <h5 class="text-muted">Nenhuma DRE confirmada encontrada</h5>
      <a href="<?= url('imports/create') ?>" class="btn btn-primary mt-2">
        <i class="bi bi-cloud-upload me-1"></i>Importar Balancete
      </a>
    </div>
  </div>
  <?php else: ?>

  <?php if (false): ?>
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <div class="card shadow-sm h-100 dre-summary-card">
        <div class="card-body py-2">
          <div class="text-muted small">Linhas do relatório</div>
          <div class="fw-bold fs-4"><?= count($matrixRows) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100 dre-summary-card">
        <div class="card-body py-2">
          <div class="text-muted small">Período</div>
          <div class="fw-bold fs-6 mt-1"><?= e($periodLabel) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100 dre-summary-card">
        <div class="card-body py-2">
          <div class="text-muted small">Total Acumulado</div>
          <div class="fw-bold fs-5 <?= $totalAcumulado < 0 ? 'text-danger' : ($totalAcumulado > 0 ? 'text-success' : '') ?>"><?= $formatSigned($totalAcumulado) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100 dre-summary-card">
        <div class="card-body py-2">
        </div>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <div class="card shadow-sm dre-report-card">
    <div class="card-header dre-report-toolbar">
      <div>
        <div class="fw-semibold"><i class="bi bi-file-earmark-text me-1"></i>Balancete</div>
        <div class="text-muted small">Estrutura lida direto do balancete importado</div>
      </div>
      <div class="dre-tools">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleZeros" title="Ocultar/mostrar linhas zeradas">
          <i class="bi bi-filter"></i><span class="d-none d-md-inline ms-1">Zeros</span>
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="expandAll" title="Expandir tudo">
          <i class="bi bi-arrows-expand"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="collapseAll" title="Recolher tudo">
          <i class="bi bi-arrows-collapse"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="clearMarks" title="Limpar linhas marcadas">
          <i class="bi bi-eraser"></i>
        </button>
        <input type="search" id="treeSearch" class="form-control form-control-sm" placeholder="Buscar conta...">
      </div>
    </div>

    <div class="dre-report-scroll">
      <table class="table table-sm align-middle mb-0 dre-report-table" id="balanceteTree">
        <thead>
          <tr>
            <th class="dre-sticky dre-code-col">Código</th>
            <th class="dre-sticky dre-desc-col">Descrição</th>
            <?php foreach ($months as $month): ?>
            <th class="text-end dre-money-col dre-month-col"><?= e($month['label']) ?></th>
            <?php endforeach; ?>
            <th class="text-end dre-money-col">Acumulado</th>
            <th class="text-end dre-money-col">Media</th>
            <th class="text-end dre-money-col">Acumulado <?= (int)$previousYear ?></th>
            <th class="text-end dre-money-col">Media <?= (int)$previousYear ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matrixRows as $row): ?>
          <?php
            if (!empty($row['hide_duplicate'])) continue;
            $indent = (int)$row['indentation_level'];
            $hasChildren = !empty($row['has_children']);
            $acumulado = (float)($row['acumulado'] ?? 0);
            $media = (float)($row['media'] ?? 0);
            $previousYearMedia = (float)($row['previous_year_media'] ?? 0);
            $previousYearAcumulado = (float)($row['previous_year_acumulado'] ?? 0);
            $mediaPercentual = (float)($row['media_percentual'] ?? 0);
            $acumuladoPercentual = (float)($row['acumulado_percentual'] ?? 0);
            $previousYearMediaPercentual = (float)($row['previous_year_media_percentual'] ?? 0);
            $previousYearAcumuladoPercentual = (float)($row['previous_year_acumulado_percentual'] ?? 0);
            $rowKind = !empty($row['is_section']) ? 'section' : ($hasChildren ? 'group' : 'item');
            $visualKind = $rowKind;
            if ($rowKind === 'group' && $indent >= 3) {
                $visualKind = 'account-group';
            } elseif ($rowKind === 'item' && !empty($row['is_analytical'])) {
                $visualKind = 'analytical';
            }
            $hasNonzero = !$hasChildren && abs($acumulado) >= 0.005;
          ?>
          <tr id="<?= e($row['row_uid']) ?>"
              class="dre-tree-row dre-row-<?= e($rowKind) ?><?= ($hasNonzero ? ' has-nonzero' : '') ?>"
              data-row-id="<?= e($row['row_uid']) ?>"
              data-parent-id="<?= e($row['parent_uid']) ?>"
              data-level="<?= $indent ?>"
              data-group="<?= $hasChildren ? '1' : '0' ?>"
              data-kind="<?= e($visualKind) ?>"
              data-search="<?= e(mb_strtolower($row['account_code'] . ' ' . $row['account_description'])) ?>">
            <td class="dre-sticky dre-code-col"><code><?= e($row['account_code']) ?></code></td>
            <td class="dre-sticky dre-desc-col">
              <div class="dre-label" style="--level: <?= $indent ?>">
                <?php if ($hasChildren): ?>
                <button type="button"
                        class="dre-toggle"
                        data-toggle-group="<?= e($row['row_uid']) ?>"
                        title="Abrir ou fechar grupo">
                  <i class="bi bi-chevron-down"></i>
                </button>
                <?php else: ?>
                <span class="dre-leaf"></span>
                <?php endif; ?>
                <span class="dre-label-text"><?= e($row['account_description']) ?></span>
                <?php if (abs($acumulado) >= 0.005 || abs($previousYearAcumulado) >= 0.005): ?>
                <button type="button"
                        class="dre-chart-trigger"
                        data-chart="<?= $chartPayload($row, $months, (int)$fYear, (int)$previousYear) ?>"
                        title="Ver evolução mensal">
                  <i class="bi bi-graph-up"></i>
                </button>
                <?php endif; ?>
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
              <div class="dre-percent"><?= number_format($percentual, 1, ',', '.') ?>%</div>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td class="text-end dre-money dre-tooltip-cell <?= $acumulado < 0 ? 'is-negative' : ($acumulado > 0 ? 'is-positive' : '') ?>" tabindex="0"<?= $comparisonTooltipAttrs('Acumulado', $acumulado, $previousYearAcumulado, (int)$previousYear) ?>>
              <div><?= $formatSigned($acumulado) ?><?= $trendIndicator($acumulado, $previousYearAcumulado) ?></div>
              <?php if (abs($acumuladoPercentual) >= 0.01): ?>
              <div class="dre-percent"><?= number_format($acumuladoPercentual, 1, ',', '.') ?>%</div>
              <?php endif; ?>
            </td>
            <td class="text-end dre-money dre-tooltip-cell <?= $media < 0 ? 'is-negative' : ($media > 0 ? 'is-positive' : '') ?>" tabindex="0"<?= $comparisonTooltipAttrs('Media', $media, $previousYearMedia, (int)$previousYear) ?>>
              <div><?= $formatSigned($media) ?><?= $trendIndicator($media, $previousYearMedia) ?></div>
              <?php if (abs($mediaPercentual) >= 0.01): ?>
              <div class="dre-percent"><?= number_format($mediaPercentual, 1, ',', '.') ?>%</div>
              <?php endif; ?>
            </td>
            <td class="text-end dre-money <?= $previousYearAcumulado < 0 ? 'is-negative' : ($previousYearAcumulado > 0 ? 'is-positive' : '') ?>">
              <div><?= $formatSigned($previousYearAcumulado) ?></div>
              <?php if (abs($previousYearAcumuladoPercentual) >= 0.01): ?>
              <div class="dre-percent"><?= number_format($previousYearAcumuladoPercentual, 1, ',', '.') ?>%</div>
              <?php endif; ?>
            </td>
            <td class="text-end dre-money <?= $previousYearMedia < 0 ? 'is-negative' : ($previousYearMedia > 0 ? 'is-positive' : '') ?>">
              <div><?= $formatSigned($previousYearMedia) ?></div>
              <?php if (abs($previousYearMediaPercentual) >= 0.01): ?>
              <div class="dre-percent"><?= number_format($previousYearMediaPercentual, 1, ',', '.') ?>%</div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="dre-chart-backdrop" id="dreChartBackdrop" hidden></div>
  <aside class="dre-chart-panel" id="dreChartPanel" aria-hidden="true" aria-label="Evolução mensal da conta">
    <div class="dre-chart-panel-header">
      <div>
        <div class="dre-chart-kicker">Evolução mensal</div>
        <h5 class="dre-chart-title" id="dreChartTitle">Conta</h5>
        <div class="dre-chart-subtitle" id="dreChartSubtitle"></div>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="dreChartClose" title="Fechar">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="dre-chart-summary">
      <div>
        <span>Acumulado atual</span>
        <strong id="dreChartCurrentTotal">-</strong>
      </div>
      <div>
        <span>Acumulado anterior</span>
        <strong id="dreChartPreviousTotal">-</strong>
      </div>
      <div>
        <span>Diferença</span>
        <strong id="dreChartDiff">-</strong>
      </div>
      <div>
        <span>Variação</span>
        <strong id="dreChartChange">-</strong>
      </div>
    </div>
    <div class="dre-chart-canvas-wrap">
      <canvas id="dreLineChart" width="720" height="360"></canvas>
    </div>
    <div class="dre-chart-legend">
      <span><i style="background:#2563eb"></i><b id="dreChartCurrentLegend">Atual</b></span>
      <span><i style="background:#94a3b8"></i><b id="dreChartPreviousLegend">Anterior</b></span>
    </div>
    <div class="dre-chart-units" id="dreChartUnitsSection" data-period="<?= e($periodLabel) ?>" hidden>
      <div class="dre-chart-units-header">
        <span>Comparativo por unidade</span>
        <small id="dreChartUnitsPeriod"><?= e($periodLabel) ?></small>
      </div>
      <div class="dre-chart-units-list" id="dreChartUnits"></div>
    </div>
  </aside>
  <?php endif; ?>
</div>

<?php $extraJs = <<<'JS'
<script>
(() => {
  const filterForm = document.getElementById('dreFilters');
  filterForm?.querySelectorAll('select').forEach(select => {
    select.addEventListener('change', () => filterForm.requestSubmit());
  });
})();

(() => {
  const table = document.getElementById('balanceteTree');
  if (!table) return;

  const search = document.getElementById('treeSearch');
  const rows = Array.from(table.querySelectorAll('tbody tr'));
  const collapsed = new Set();
  const marked = new Set();
  const rowById = new Map(rows.map(row => [row.dataset.rowId, row]));
  const scrollWrap = table.closest('.dre-report-scroll');
  const tooltip = document.createElement('div');
  let tooltipTimer = 0;
  let activeTooltipCell = null;
  const chartPanel = document.getElementById('dreChartPanel');
  const chartBackdrop = document.getElementById('dreChartBackdrop');
  const chartCanvas = document.getElementById('dreLineChart');
  const chartClose = document.getElementById('dreChartClose');
  const chartUnitsSection = document.getElementById('dreChartUnitsSection');
  const chartUnits = document.getElementById('dreChartUnits');
  const chartUnitsPeriod = document.getElementById('dreChartUnitsPeriod');
  let activeChartPayload = null;

  tooltip.className = 'dre-value-tooltip';
  document.body.appendChild(tooltip);

  function updateScrollState() {
    if (!scrollWrap) return;
    scrollWrap.classList.toggle('is-scrolled-x', scrollWrap.scrollLeft > 1);
    scrollWrap.classList.toggle('is-scrolled-y', scrollWrap.scrollTop > 1);
  }

  scrollWrap?.addEventListener('scroll', updateScrollState, { passive: true });
  window.addEventListener('resize', updateScrollState);
  if (window.ResizeObserver && scrollWrap) {
    new ResizeObserver(updateScrollState).observe(scrollWrap);
  }

  function ancestorsOf(row) {
    const ancestors = [];
    let parentId = row.dataset.parentId;
    while (parentId && rowById.has(parentId)) {
      const parent = rowById.get(parentId);
      ancestors.push(parent);
      parentId = parent.dataset.parentId;
    }
    return ancestors;
  }

  function descendantsOf(row) {
    return rows.filter(candidate => ancestorsOf(candidate).some(parent => parent === row));
  }

  function hiddenByGroup(row) {
    return ancestorsOf(row).some(parent => collapsed.has(parent.dataset.rowId));
  }

  function matchesSearch(row, query) {
    if (!query) return true;
    if (row.dataset.search.includes(query)) return true;
    return descendantsOf(row).some(child => child.dataset.search.includes(query));
  }

  function render() {
    const query = search ? search.value.trim().toLowerCase() : '';
    rows.forEach(row => {
      const visible = matchesSearch(row, query) && (query || !hiddenByGroup(row));
      row.hidden = !visible;

      if (row.dataset.group === '1') {
        const isCollapsed = collapsed.has(row.dataset.rowId);
        row.classList.toggle('is-collapsed', isCollapsed);
      }

      row.classList.toggle('is-marked', marked.has(row.dataset.rowId));
    });
  }

  function tooltipHtml(cell) {
    return `
      <div class="dre-value-tooltip-title">${cell.dataset.tooltipTitle || ''}</div>
      <div class="dre-value-tooltip-row"><span>Atual</span><strong>${cell.dataset.tooltipCurrent || '-'}</strong></div>
      <div class="dre-value-tooltip-row"><span>${cell.dataset.tooltipPreviousLabel || 'Anterior'}</span><strong>${cell.dataset.tooltipPrevious || '-'}</strong></div>
      <div class="dre-value-tooltip-divider"></div>
      <div class="dre-value-tooltip-row"><span>Diferença</span><strong>${cell.dataset.tooltipDiff || '-'}</strong></div>
      <div class="dre-value-tooltip-row"><span>Variação</span><strong>${cell.dataset.tooltipPercent || '-'}</strong></div>
    `;
  }

  function placeTooltip(cell) {
    const rect = cell.getBoundingClientRect();
    tooltip.style.left = '0px';
    tooltip.style.top = '0px';
    tooltip.classList.add('is-visible');

    const tipRect = tooltip.getBoundingClientRect();
    const margin = 10;
    const left = Math.min(
      window.innerWidth - tipRect.width - margin,
      Math.max(margin, rect.left + rect.width / 2 - tipRect.width / 2)
    );
    const top = rect.top >= tipRect.height + margin
      ? rect.top - tipRect.height - 8
      : rect.bottom + 8;

    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${Math.min(window.innerHeight - tipRect.height - margin, Math.max(margin, top))}px`;
  }

  function showTooltip(cell) {
    activeTooltipCell = cell;
    tooltip.innerHTML = tooltipHtml(cell);
    placeTooltip(cell);
  }

  function scheduleTooltip(cell) {
    window.clearTimeout(tooltipTimer);
    tooltipTimer = window.setTimeout(() => showTooltip(cell), 350);
  }

  function hideTooltip() {
    window.clearTimeout(tooltipTimer);
    activeTooltipCell = null;
    tooltip.classList.remove('is-visible');
  }
  const brl = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  function formatMoney(value) {
    const number = Number(value || 0);
    if (Math.abs(number) < 0.005) return '-';
    const formatted = brl.format(Math.abs(number));
    return number < 0 ? `(${formatted})` : formatted;
  }

  function formatChange(current, previous) {
    const currentValue = Number(current || 0);
    const previousValue = Number(previous || 0);
    const diff = previousValue < 0 && currentValue >= 0
      ? currentValue - Math.abs(previousValue)
      : currentValue - previousValue;
    const percent = Math.abs(previousValue) >= 0.005 ? (diff / Math.abs(previousValue)) * 100 : null;
    return {
      diff,
      amount: formatMoney(diff),
      percent: percent === null ? '-' : `${percent.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`
    };
  }

  function drawLineChart(payload) {
    if (!chartCanvas) return;

    const ctx = chartCanvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const cssWidth = chartCanvas.clientWidth || 720;
    const cssHeight = chartCanvas.clientHeight || 280;
    chartCanvas.width = Math.round(cssWidth * dpr);
    chartCanvas.height = Math.round(cssHeight * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssWidth, cssHeight);

    const labels = payload.labels || [];
    const current = (payload.current || []).map(Number);
    const previous = (payload.previous || []).map(Number);
    const values = [...current, ...previous, 0].filter(Number.isFinite);
    const rawMax = Math.max(...values, 1);
    const rawMin = Math.min(...values, 0);
    const span = Math.max(1, rawMax - rawMin);
    const max = rawMax + span * 0.12;
    const min = rawMin - span * 0.12;
    const padding = { top: 18, right: 16, bottom: 34, left: 66 };
    const plotWidth = cssWidth - padding.left - padding.right;
    const plotHeight = cssHeight - padding.top - padding.bottom;
    const range = Math.max(1, max - min);
    const xFor = index => labels.length <= 1 ? padding.left + plotWidth / 2 : padding.left + (index / (labels.length - 1)) * plotWidth;
    const yFor = value => padding.top + ((max - value) / range) * plotHeight;
    const clampY = value => Math.max(padding.top, Math.min(padding.top + plotHeight, yFor(value)));
    const zeroY = clampY(0);

    function formatAxis(value) {
      const abs = Math.abs(value);
      const sign = value < 0 ? '-' : '';
      if (abs >= 1000000) {
        return `${sign}${(abs / 1000000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mi`;
      }
      if (abs >= 1000) {
        return `${sign}${(abs / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 0 })} mil`;
      }
      return `${sign}${abs.toLocaleString('pt-BR', { maximumFractionDigits: 0 })}`;
    }

    function smoothPath(points) {
      if (!points.length) return;
      ctx.beginPath();
      ctx.moveTo(points[0].x, points[0].y);
      for (let i = 1; i < points.length; i++) {
        const prev = points[i - 1];
        const currentPoint = points[i];
        const midX = (prev.x + currentPoint.x) / 2;
        ctx.bezierCurveTo(midX, prev.y, midX, currentPoint.y, currentPoint.x, currentPoint.y);
      }
    }

    function pointsFor(series) {
      return series.map((value, index) => ({ x: xFor(index), y: yFor(value), value }));
    }

    ctx.font = '11px system-ui, -apple-system, Segoe UI, sans-serif';
    ctx.lineWidth = 1;
    ctx.textBaseline = 'middle';

    const ticks = 4;
    for (let i = 0; i <= ticks; i++) {
      const y = padding.top + (plotHeight / ticks) * i;
      const value = max - (range / ticks) * i;
      ctx.strokeStyle = i === ticks ? '#d8e2ee' : '#edf2f7';
      ctx.beginPath();
      ctx.moveTo(padding.left, y);
      ctx.lineTo(cssWidth - padding.right, y);
      ctx.stroke();
      ctx.fillStyle = '#8aa0ba';
      ctx.textAlign = 'right';
      ctx.fillText(formatAxis(value), padding.left - 10, y);
    }

    ctx.strokeStyle = '#bac8d8';
    ctx.setLineDash([2, 4]);
    ctx.beginPath();
    ctx.moveTo(padding.left, zeroY);
    ctx.lineTo(cssWidth - padding.right, zeroY);
    ctx.stroke();
    ctx.setLineDash([]);

    labels.forEach((label, index) => {
      ctx.fillStyle = '#6f8199';
      ctx.textAlign = 'center';
      ctx.fillText(label, xFor(index), cssHeight - 15);
    });

    const previousPoints = pointsFor(previous);
    const currentPoints = pointsFor(current);

    if (currentPoints.length) {
      ctx.save();
      smoothPath(currentPoints);
      ctx.lineTo(currentPoints[currentPoints.length - 1].x, zeroY);
      ctx.lineTo(currentPoints[0].x, zeroY);
      ctx.closePath();
      const areaGradient = ctx.createLinearGradient(0, padding.top, 0, padding.top + plotHeight);
      areaGradient.addColorStop(0, 'rgba(37, 99, 235, .14)');
      areaGradient.addColorStop(1, 'rgba(37, 99, 235, 0)');
      ctx.fillStyle = areaGradient;
      ctx.fill();
      ctx.restore();
    }

    function line(points, color, width, dash = []) {
      if (!points.length) return;
      ctx.save();
      ctx.setLineDash([]);
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = color;
      ctx.lineWidth = width;
      ctx.setLineDash(dash);
      smoothPath(points);
      ctx.stroke();
      ctx.restore();
    }

    line(previousPoints, '#9aacbf', 2, [5, 6]);

    const currentGradient = ctx.createLinearGradient(padding.left, 0, cssWidth - padding.right, 0);
    currentGradient.addColorStop(0, '#2f80ed');
    currentGradient.addColorStop(1, '#1d4ed8');
    line(currentPoints, currentGradient, 2.6);

    function points(points, color, radius) {
      ctx.save();
      points.forEach(point => {
        ctx.fillStyle = '#fff';
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(point.x, point.y, radius, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
      });
      ctx.restore();
    }

    points(previousPoints, '#9aacbf', 3.2);
    points(currentPoints, '#2563eb', 3.6);
  }

  function renderUnitComparison(payload) {
    if (!chartUnitsSection || !chartUnits) return;

    const units = (payload.unitComparison || [])
      .map(unit => ({
        code: String(unit.code || '').trim(),
        name: String(unit.name || '').trim(),
        label: String(unit.label || `${unit.code || ''} ${unit.name || ''}`).trim(),
        total: Number(unit.total || 0)
      }))
      .filter(unit => Math.abs(unit.total) >= 0.005);

    chartUnits.replaceChildren();
    if (chartUnitsPeriod) {
      chartUnitsPeriod.textContent = chartUnitsSection.dataset.period || '';
    }

    if (!units.length) {
      chartUnitsSection.hidden = true;
      return;
    }

    const max = Math.max(...units.map(unit => Math.abs(unit.total)), 1);
    const totalAbs = units.reduce((sum, unit) => sum + Math.abs(unit.total), 0);
    units.slice(0, 12).forEach(unit => {
      const percent = totalAbs > 0 ? (Math.abs(unit.total) / totalAbs) * 100 : 0;
      const row = document.createElement('div');
      row.className = 'dre-chart-unit-row';

      const label = document.createElement('div');
      label.className = 'dre-chart-unit-label';

      const labelMain = document.createElement('span');
      labelMain.textContent = unit.code || 'Unidade';

      const labelSub = document.createElement('small');
      labelSub.textContent = unit.name || unit.label || 'Unidade';

      label.append(labelMain, labelSub);

      const plot = document.createElement('div');
      plot.className = 'dre-chart-unit-plot';

      const track = document.createElement('div');
      track.className = 'dre-chart-unit-track';

      const bar = document.createElement('i');
      bar.className = unit.total < 0 ? 'is-negative' : 'is-positive';
      bar.style.width = `${Math.max(3, (Math.abs(unit.total) / max) * 100)}%`;

      const value = document.createElement('strong');
      value.textContent = formatMoney(unit.total);
      value.className = unit.total < 0 ? 'is-negative' : (unit.total > 0 ? 'is-positive' : '');

      track.append(bar);
      plot.append(track, value);

      const share = document.createElement('em');
      share.textContent = percent > 0
        ? `${percent.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`
        : '-';

      row.append(label, plot, share);
      chartUnits.append(row);
    });

    chartUnitsSection.hidden = false;
  }

  function openChart(payload) {
    if (!chartPanel || !chartBackdrop) return;
    activeChartPayload = payload;
    const change = formatChange(payload.currentTotal, payload.previousTotal);
    document.getElementById('dreChartTitle').textContent = `${payload.code} ${payload.description}`;
    document.getElementById('dreChartSubtitle').textContent = `${payload.currentYear} x ${payload.previousYear}`;
    document.getElementById('dreChartCurrentTotal').textContent = formatMoney(payload.currentTotal);
    document.getElementById('dreChartPreviousTotal').textContent = formatMoney(payload.previousTotal);
    document.getElementById('dreChartDiff').textContent = change.amount;
    document.getElementById('dreChartChange').textContent = change.percent;
    document.getElementById('dreChartCurrentLegend').textContent = String(payload.currentYear);
    document.getElementById('dreChartPreviousLegend').textContent = String(payload.previousYear);
    chartBackdrop.hidden = false;
    chartBackdrop.classList.add('is-visible');
    chartPanel.classList.add('is-open');
    chartPanel.setAttribute('aria-hidden', 'false');
    renderUnitComparison(payload);
    window.requestAnimationFrame(() => drawLineChart(payload));
  }

  function closeChart() {
    activeChartPayload = null;
    chartPanel?.classList.remove('is-open');
    chartPanel?.setAttribute('aria-hidden', 'true');
    chartBackdrop?.classList.remove('is-visible');
    window.setTimeout(() => {
      if (!chartPanel?.classList.contains('is-open') && chartBackdrop) {
        chartBackdrop.hidden = true;
      }
    }, 160);
  }


  // Colapso padrão: nivels >= 3 começam fechados
  rows.filter(r => r.dataset.group === '1' && parseInt(r.dataset.level, 10) >= 3)
    .forEach(r => collapsed.add(r.dataset.rowId));

  // Toggle hide-zeros
  const toggleZerosBtn = document.getElementById('toggleZeros');
  let zerosHidden = false;
  toggleZerosBtn?.addEventListener('click', () => {
    zerosHidden = !zerosHidden;
    table.classList.toggle('hide-zeros', zerosHidden);
    const icon = toggleZerosBtn.querySelector('i');
    if (icon) icon.className = zerosHidden ? 'bi bi-eye' : 'bi bi-eye-slash';
    toggleZerosBtn.classList.toggle('active', zerosHidden);
  });


  table.querySelectorAll('[data-toggle-group]').forEach(button => {
    button.addEventListener('click', event => {
      event.stopPropagation();
      toggleGroup(button.dataset.toggleGroup);
    });
  });

  function toggleGroup(rowId) {
    if (!rowId) return;
    collapsed.has(rowId) ? collapsed.delete(rowId) : collapsed.add(rowId);
    render();
  }

  rows.forEach(row => {
    row.addEventListener('click', event => {
      if (event.target.closest('a, button, input, select')) return;
      if (row.dataset.group === '1' && event.target.closest('.dre-code-col, .dre-desc-col')) {
        toggleGroup(row.dataset.rowId);
        return;
      }
      if (marked.has(row.dataset.rowId)) {
        marked.delete(row.dataset.rowId);
        row.classList.remove('is-marked');
      } else {
        marked.add(row.dataset.rowId);
        row.classList.add('is-marked');
      }
    });
  });

  table.querySelectorAll('.dre-chart-trigger').forEach(button => {
    button.addEventListener('click', event => {
      event.stopPropagation();
      try {
        openChart(JSON.parse(button.dataset.chart || '{}'));
      } catch (error) {
        console.error('Nao foi possivel abrir o grafico da conta.', error);
      }
    });
  });

  document.getElementById('clearMarks')?.addEventListener('click', () => {
    marked.clear();
    rows.forEach(row => row.classList.remove('is-marked'));
  });

  document.getElementById('expandAll')?.addEventListener('click', () => {
    collapsed.clear();
    render();
  });

  document.getElementById('collapseAll')?.addEventListener('click', () => {
    rows.filter(row => row.dataset.group === '1')
      .forEach(row => collapsed.add(row.dataset.rowId));
    render();
  });

  chartClose?.addEventListener('click', closeChart);
  chartBackdrop?.addEventListener('click', closeChart);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      closeChart();
    }
  });
  search?.addEventListener('input', render);
  table.querySelectorAll('.dre-tooltip-cell').forEach(cell => {
    cell.addEventListener('mouseenter', () => scheduleTooltip(cell));
    cell.addEventListener('mouseleave', hideTooltip);
    cell.addEventListener('focus', () => showTooltip(cell));
    cell.addEventListener('blur', hideTooltip);
    cell.addEventListener('click', event => {
      event.stopPropagation();
      showTooltip(cell);
    });
  });
  document.addEventListener('click', event => {
    if (!event.target.closest('.dre-tooltip-cell')) {
      hideTooltip();
    }
  });
  window.addEventListener('scroll', () => activeTooltipCell ? placeTooltip(activeTooltipCell) : null, true);
  window.addEventListener('resize', () => {
    hideTooltip();
    if (activeChartPayload) {
      drawLineChart(activeChartPayload);
    }
  });
  render();
  updateScrollState();
})();
</script>
JS; ?>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>

