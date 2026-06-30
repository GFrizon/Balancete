<?php
$pageTitle = 'Balancete';
$mainClass = 'app-main app-main-dre';
$hideFooter = true;

$formatSigned = static function (float $value): string {
    if (abs($value) < 0.005) {
        return '<span class="dre-zero">-</span>';
    }

    $formatted = format_brl(abs($value));
    return $value < 0 ? '(' . $formatted . ')' : $formatted;
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
      <form method="GET" action="<?= url() ?>" class="row g-2 align-items-end">
        <input type="hidden" name="route" value="dre">
        <div class="col-sm-6 col-md-2">
          <label class="form-label small mb-1 fw-semibold">Empresa</label>
          <select name="company_id" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($companies as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $fCompany == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6 col-md-2">
          <label class="form-label small mb-1 fw-semibold">Grupo</label>
          <select name="group_id" class="form-select form-select-sm">
            <option value="">Todos</option>
            <?php foreach ($groups as $g): ?>
            <option value="<?= (int)$g['id'] ?>" <?= $fGroup == $g['id'] ? 'selected' : '' ?>>
              <?= e($g['name']) ?> (<?= (int)$g['units_count'] ?>)
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
        <div class="col-sm-6 col-md-2 d-flex gap-1">
          <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search me-1"></i>Filtrar</button>
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
        <button type="button" class="btn btn-outline-secondary btn-sm" id="collapseAll" title="Recolher grupos">
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
            <th class="text-end dre-money-col">Media</th>
            <th class="text-end dre-money-col">Acumulado</th>
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
            <td class="text-end dre-money <?= $media < 0 ? 'is-negative' : ($media > 0 ? 'is-positive' : '') ?>">
              <?= $formatSigned($media) ?>
            </td>
            <td class="text-end dre-money <?= $acumulado < 0 ? 'is-negative' : ($acumulado > 0 ? 'is-positive' : '') ?>">
              <?= $formatSigned($acumulado) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php $extraJs = <<<'JS'
<script>
(() => {
  const table = document.getElementById('balanceteTree');
  if (!table) return;

  const search = document.getElementById('treeSearch');
  const rows = Array.from(table.querySelectorAll('tbody tr'));
  const collapsed = new Set();
  const marked = new Set();
  const rowById = new Map(rows.map(row => [row.dataset.rowId, row]));
  const scrollWrap = table.closest('.dre-report-scroll');

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
      const rowId = button.dataset.toggleGroup;
      collapsed.has(rowId) ? collapsed.delete(rowId) : collapsed.add(rowId);
      render();
    });
  });

  rows.forEach(row => {
    row.addEventListener('click', event => {
      if (event.target.closest('a, button, input, select')) return;
      if (marked.has(row.dataset.rowId)) {
        marked.delete(row.dataset.rowId);
        row.classList.remove('is-marked');
      } else {
        marked.add(row.dataset.rowId);
        row.classList.add('is-marked');
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
    rows.filter(row => row.dataset.group === '1' && row.dataset.level !== '0')
      .forEach(row => collapsed.add(row.dataset.rowId));
    render();
  });

  search?.addEventListener('input', render);
  render();
  updateScrollState();
})();
</script>
JS; ?>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>

