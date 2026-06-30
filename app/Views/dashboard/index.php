<?php $pageTitle = 'Dashboard'; ?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<div class="container-fluid py-4 px-4">

  <!-- Título -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h3 class="fw-bold mb-1" style="color: #1e293b;">Dashboard</h3>
      <p class="text-muted mb-0" style="font-size: .875rem;">Visão geral do sistema</p>
    </div>
    <a href="<?= url('imports/create') ?>" class="btn btn-primary btn-lg shadow-sm">
      <i class="bi bi-cloud-upload me-2"></i>Nova Importação
    </a>
  </div>

  <!-- Cards de resumo -->
  <div class="row g-3 mb-5">
    <div class="col-sm-6 col-xl-3">
      <div class="kpi-card card h-100">
        <div class="card-body">
          <div class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-cloud-arrow-up"></i></div>
          <div class="kpi-value"><?= $totalImports ?></div>
          <div class="kpi-label">Total de Importações</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="kpi-card card h-100">
        <div class="card-body">
          <div class="kpi-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
          <div class="kpi-value"><?= $totalConfirmed ?></div>
          <div class="kpi-label">Importações Confirmadas</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="kpi-card card h-100">
        <div class="card-body">
          <div class="kpi-icon bg-info-subtle text-info"><i class="bi bi-building"></i></div>
          <div class="kpi-value"><?= $totalUnits ?></div>
          <div class="kpi-label">Unidades Ativas</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="kpi-card card h-100">
        <div class="card-body">
          <div class="kpi-icon bg-warning-subtle text-warning"><i class="bi bi-calendar-check"></i></div>
          <div class="kpi-value">
            <?php if ($lastImport): ?>
              <?= month_short((int)$lastImport['month']) ?>/<?= $lastImport['year'] ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </div>
          <div class="kpi-label">
            Último período
            <?php if ($lastImport): ?>
              <br><small class="text-muted"><?= e($lastImport['unit_name']) ?></small>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Resumo financeiro do último mês -->
  <?php if ($lastMonthSummary): ?>
  <?php
    $receita = (float)$lastMonthSummary['receita'];
    $custo = abs((float)$lastMonthSummary['custo_despesa']);
    $resultado = (float)$lastMonthSummary['resultado'];
    $periodLabel = month_short((int)$lastMonthSummary['month']) . '/' . $lastMonthSummary['year'];
  ?>
  <div class="row g-3 mb-5">
    <div class="col-12 mb-2">
      <div class="d-flex align-items-center gap-2">
        <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #eff6ff; border-radius: 8px;">
          <i class="bi bi-graph-up text-primary"></i>
        </div>
        <div>
          <h5 class="fw-semibold mb-0" style="color: #1e293b;">Resumo Financeiro</h5>
          <small class="text-muted"><?= e($periodLabel) ?></small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="fin-card-v2 receita h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="fin-icon-v2 receita">
              <i class="bi bi-arrow-down-circle-fill"></i>
            </div>
            <div class="fin-badge receita">
              <i class="bi bi-arrow-up-right" style="font-size: .7rem;"></i>
            </div>
          </div>
          <div class="fin-label-v2">Receitas</div>
          <div class="fin-value-v2 receita"><?= format_brl($receita) ?></div>
          <div class="fin-sub-v2">Mês de <?= e($periodLabel) ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="fin-card-v2 custo h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="fin-icon-v2 custo">
              <i class="bi bi-arrow-up-circle-fill"></i>
            </div>
            <div class="fin-badge custo">
              <i class="bi bi-arrow-up-right" style="font-size: .7rem;"></i>
            </div>
          </div>
          <div class="fin-label-v2">Custos / Despesas</div>
          <div class="fin-value-v2 custo"><?= format_brl($custo) ?></div>
          <div class="fin-sub-v2">Mês de <?= e($periodLabel) ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="fin-card-v2 resultado h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="fin-icon-v2 resultado">
              <i class="bi bi-wallet2"></i>
            </div>
            <div class="fin-badge resultado">
              <i class="bi bi-<?= $resultado >= 0 ? 'arrow-up-right' : 'arrow-down-right' ?>" style="font-size: .7rem;"></i>
            </div>
          </div>
          <div class="fin-label-v2">Resultado Líquido</div>
          <div class="fin-value-v2 resultado"><?= format_brl($resultado) ?></div>
          <div class="fin-sub-v2"><?= $resultado >= 0 ? 'Lucro no período' : 'Prejuízo no período' ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Evolução mensal e comparativo por unidade -->
  <div class="row g-3 mb-5">
    <?php if (count($monthlySummary) > 1): ?>
    <div class="col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-2 mb-4">
            <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #eff6ff; border-radius: 8px;">
              <i class="bi bi-graph-up-arrow text-primary"></i>
            </div>
            <div>
              <h5 class="fw-semibold mb-0" style="color: #1e293b;">Evolução Mensal</h5>
              <small class="text-muted">Últimos 12 meses</small>
            </div>
          </div>
          <div style="height: 280px;">
            <canvas id="monthlyChart"></canvas>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($accountComparison['rows'])): ?>
    <?php
      $accTotals = $accountComparison['totals'] ?? [];
      $accPeriod = $accountComparison['period'] ?? [];
      $bestMargin = $accTotals['best_margin'] ?? null;
      $worstResult = $accTotals['worst_result'] ?? null;
    ?>
    <div class="col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body p-3">
          <div class="d-flex align-items-center gap-2 mb-3">
            <div class="d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: #dbeafe; border-radius: 7px;">
              <i class="bi bi-percent text-primary" style="font-size:.8rem;"></i>
            </div>
            <div>
              <h5 class="fw-semibold mb-0" style="color: #1e293b; font-size:.9rem;">% por Filial<?= e($accPeriod['group_name'] ?? '') ?></h5>
              <small class="text-muted"><?= e($accPeriod['label'] ?? '') ?> · % sobre Receita · hover = valor</small>
            </div>
          </div>
          <?php
            $pctRows = $accountComparison['rows'];
            $pctUnits = [];
            foreach ($pctRows as $u) {
              $rec = (float)$u['receita_acum'];
              $base = $rec > 0 ? $rec : 1;
              $pctUnits[] = [
                'code'      => $u['unit_code'],
                'name'      => $u['unit_name'],
                'unit_id'   => $u['unit_id'],
                'receita'   => $rec,
                'dev_pct'   => (float)$u['devolucoes_acum']           / $base * 100,
                'dev_val'   => (float)$u['devolucoes_acum'],
                'custo_pct' => (float)$u['custo_acum']                / $base * 100,
                'custo_val' => (float)$u['custo_acum'],
                'op_pct'    => (float)$u['desp_operacionais_acum']    / $base * 100,
                'op_val'    => (float)$u['desp_operacionais_acum'],
                'adm_pct'   => (float)$u['desp_administrativas_acum'] / $base * 100,
                'adm_val'   => (float)$u['desp_administrativas_acum'],
                'res_pct'   => (float)$u['resultado_acum']            / $base * 100,
                'res_val'   => (float)$u['resultado_acum'],
                'margin'    => (float)$u['margin'],
                'isBest'    => $bestMargin  && $u['unit_id'] === $bestMargin['unit_id'],
                'isWorst'   => $worstResult && $u['unit_id'] === $worstResult['unit_id'] && (float)$u['resultado_acum'] < 0,
              ];
            }
            $pctTotalRec  = array_sum(array_map(fn($u) => $u['receita'], $pctUnits));
            $pctTotalBase = $pctTotalRec > 0 ? $pctTotalRec : 1;
            $pctTotal = [
              'dev_pct'   => array_sum(array_map(fn($u) => $u['dev_val'],   $pctUnits)) / $pctTotalBase * 100,
              'custo_pct' => array_sum(array_map(fn($u) => $u['custo_val'], $pctUnits)) / $pctTotalBase * 100,
              'op_pct'    => array_sum(array_map(fn($u) => $u['op_val'],    $pctUnits)) / $pctTotalBase * 100,
              'adm_pct'   => array_sum(array_map(fn($u) => $u['adm_val'],   $pctUnits)) / $pctTotalBase * 100,
              'res_pct'   => (float)($accTotals['resultado'] ?? 0) / $pctTotalBase * 100,
            ];
            function pctBg(float $pct, string $type): string {
              if ($type === 'resultado') {
                if ($pct >= 10) return '#f0fdf4';
                if ($pct >= 5)  return '#fefce8';
                if ($pct >= 0)  return '#fff7ed';
                return '#fef2f2';
              }
              if ($pct >= 80) return '#fecaca';
              if ($pct >= 60) return '#fee2e2';
              if ($pct >= 40) return '#fff1f2';
              return 'transparent';
            }
            function pctColor(float $pct, string $type): string {
              if ($type === 'resultado') return $pct >= 5 ? '#16a34a' : ($pct >= 0 ? '#d97706' : '#dc2626');
              return $pct >= 60 ? '#dc2626' : ($pct >= 40 ? '#d97706' : '#475569');
            }
            $pctLines = [
              ['key'=>'dev',   'label'=>'Devoluções',     'type'=>'expense'],
              ['key'=>'custo', 'label'=>'CPV',            'type'=>'expense'],
              ['key'=>'op',    'label'=>'Desp. Op.',      'type'=>'expense'],
              ['key'=>'adm',   'label'=>'Desp. Adm.',     'type'=>'expense'],
              ['key'=>'res',   'label'=>'Resultado',      'type'=>'resultado'],
            ];
          ?>
          <div class="table-responsive">
            <table class="table table-sm mb-0" style="font-size:.75rem;">
              <thead>
                <tr>
                  <th class="border-0 py-1" style="color:#94a3b8; font-size:.65rem; text-transform:uppercase; min-width:75px;"></th>
                  <?php foreach ($pctUnits as $u): ?>
                  <th class="border-0 py-1 text-center" style="min-width:65px;">
                    <div class="fw-bold" style="color:#1e293b; font-size:.78rem;">
                      <?= e($u['code']) ?>
                      <?php if ($u['isBest']): ?><i class="bi bi-trophy-fill text-warning" style="font-size:.6rem;"></i><?php endif; ?>
                      <?php if ($u['isWorst']): ?><i class="bi bi-exclamation-triangle-fill text-danger" style="font-size:.6rem;"></i><?php endif; ?>
                    </div>
                    <div class="text-muted" style="font-size:.6rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:65px;"><?= e($u['name']) ?></div>
                  </th>
                  <?php endforeach; ?>
                  <th class="border-0 py-1 text-center" style="min-width:55px; color:#94a3b8; font-size:.65rem; text-transform:uppercase;">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr style="border-top:1px solid #f1f5f9;">
                  <td class="py-1 fw-semibold" style="color:#16a34a; font-size:.7rem;">Receita</td>
                  <?php foreach ($pctUnits as $u): ?>
                  <td class="py-1 text-center" style="background:#f0fdf4; font-size:.7rem; color:#16a34a;" title="<?= format_brl($u['receita']) ?>">
                    <span class="fw-semibold"><?= format_brl($u['receita']) ?></span>
                  </td>
                  <?php endforeach; ?>
                  <td class="py-1 text-center fw-bold" style="background:#dcfce7; font-size:.7rem; color:#16a34a;"><?= format_brl($pctTotalRec) ?></td>
                </tr>
                <?php foreach ($pctLines as $line): ?>
                <?php $totalPct = $pctTotal[$line['key'].'_pct']; ?>
                <tr style="border-top:1px solid #f8fafc;">
                  <td class="py-1" style="color:#475569; font-size:.7rem;"><?= $line['label'] ?></td>
                  <?php foreach ($pctUnits as $u): ?>
                  <?php
                    $pct = $u[$line['key'].'_pct'];
                    $val = $line['type'] === 'resultado' ? $u['res_val'] : $u[$line['key'].'_val'];
                  ?>
                  <td class="py-1 text-center" style="background:<?= pctBg($pct, $line['type']) ?>;" title="<?= format_brl($val) ?>">
                    <span class="fw-semibold" style="color:<?= pctColor($pct, $line['type']) ?>; font-size:.78rem;"><?= number_format($pct, 1, ',', '.') ?>%</span>
                  </td>
                  <?php endforeach; ?>
                  <td class="py-1 text-center fw-bold" style="background:<?= pctBg($totalPct, $line['type']) ?>;">
                    <span style="color:<?= pctColor($totalPct, $line['type']) ?>; font-size:.78rem;"><?= number_format($totalPct, 1, ',', '.') ?>%</span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr style="border-top:2px solid #e2e8f0;">
                  <td class="py-1 fw-bold" style="color:#1e293b; font-size:.7rem;">Margem</td>
                  <?php foreach ($pctUnits as $u): ?>
                  <?php
                    $m = $u['margin'];
                    $mbg = $m >= 10 ? '#f0fdf4' : ($m >= 5 ? '#fefce8' : ($m >= 0 ? '#fff7ed' : '#fef2f2'));
                    $mc  = $m >= 10 ? '#16a34a' : ($m >= 5 ? '#ca8a04' : ($m >= 0 ? '#d97706' : '#dc2626'));
                  ?>
                  <td class="py-1 text-center" style="background:<?= $mbg ?>;">
                    <span class="fw-bold" style="color:<?= $mc ?>; font-size:.82rem;"><?= number_format($m, 1, ',', '.') ?>%</span>
                  </td>
                  <?php endforeach; ?>
                  <?php
                    $tm = $pctTotal['res_pct'];
                    $tmbg = $tm >= 10 ? '#f0fdf4' : ($tm >= 5 ? '#fefce8' : ($tm >= 0 ? '#fff7ed' : '#fef2f2'));
                    $tmc  = $tm >= 10 ? '#16a34a' : ($tm >= 5 ? '#ca8a04' : ($tm >= 0 ? '#d97706' : '#dc2626'));
                  ?>
                  <td class="py-1 text-center" style="background:<?= $tmbg ?>;">
                    <span class="fw-bold" style="color:<?= $tmc ?>; font-size:.82rem;"><?= number_format($tm, 1, ',', '.') ?>%</span>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Ações rápidas -->
  <div class="row g-3 mb-5">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #fef3c7; border-radius: 8px;">
              <i class="bi bi-lightning text-warning"></i>
            </div>
            <h5 class="fw-semibold mb-0" style="color: #1e293b;">Ações Rápidas</h5>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url('dre') ?>" class="btn btn-outline-primary">
              <i class="bi bi-list-tree me-2"></i>Ver DRE
            </a>
            <a href="<?= url('imports/create') ?>" class="btn btn-outline-success">
              <i class="bi bi-cloud-upload me-2"></i>Importar Balancete
            </a>
            <a href="<?= url('imports') ?>" class="btn btn-outline-secondary">
              <i class="bi bi-list-ul me-2"></i>Listar Importações
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Últimas importações -->
  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-2">
          <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #e0e7ff; border-radius: 8px;">
            <i class="bi bi-clock-history text-primary"></i>
          </div>
          <h5 class="fw-semibold mb-0" style="color: #1e293b;">Últimas Importações</h5>
        </div>
        <a href="<?= url('imports') ?>" class="btn btn-sm btn-outline-primary">Ver todas</a>
      </div>
      <?php if (empty($recentImports)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        Nenhuma importação ainda.
        <a href="<?= url('imports/create') ?>">Fazer primeira importação</a>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" style="font-size: .875rem;">
          <thead style="border-bottom: 2px solid #e2e8f0;">
            <tr>
              <th class="border-0 text-muted fw-semibold" style="font-size: .75rem; text-transform: uppercase; letter-spacing: .05em;">Empresa / Unidade</th>
              <th class="border-0 text-muted fw-semibold" style="font-size: .75rem; text-transform: uppercase; letter-spacing: .05em;">Período</th>
              <th class="border-0 text-muted fw-semibold" style="font-size: .75rem; text-transform: uppercase; letter-spacing: .05em;">Arquivo</th>
              <th class="border-0 text-muted fw-semibold" style="font-size: .75rem; text-transform: uppercase; letter-spacing: .05em;">Status</th>
              <th class="border-0 text-muted fw-semibold" style="font-size: .75rem; text-transform: uppercase; letter-spacing: .05em;">Importado por</th>
              <th class="border-0 text-muted fw-semibold" style="font-size: .75rem; text-transform: uppercase; letter-spacing: .05em;">Data</th>
              <th class="border-0"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentImports as $imp): ?>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td class="py-3">
                <div class="fw-semibold" style="color: #1e293b;"><?= e($imp['company_name']) ?></div>
                <small class="text-muted"><?= e($imp['unit_code']) ?> — <?= e($imp['unit_name']) ?></small>
              </td>
              <td class="py-3"><?= month_short((int)$imp['month']) ?>/<?= $imp['year'] ?></td>
              <td class="py-3"><small class="text-muted"><?= e($imp['original_filename']) ?></small></td>
              <td class="py-3"><?php
                $badges = ['pending'=>'secondary','processing'=>'warning','confirmed'=>'success','error'=>'danger'];
                $badge = $badges[$imp['status']] ?? 'secondary';
              ?>
                <span class="badge bg-<?= $badge ?>"><?= e($imp['status']) ?></span>
              </td>
              <td class="py-3"><small><?= e($imp['imported_by_name']) ?></small></td>
              <td class="py-3"><small><?= date('d/m/Y H:i', strtotime($imp['imported_at'])) ?></small></td>
              <td class="py-3">
                <?php if ($imp['status'] === 'confirmed'): ?>
                <a href="<?= url('dre', ['year' => $imp['year'], 'month_start' => $imp['month'], 'month_end' => $imp['month']]) ?>"
                   class="btn btn-sm btn-outline-primary">DRE</a>
                <?php elseif ($imp['status'] === 'pending'): ?>
                <a href="<?= url('imports/' . $imp['id'] . '/preview') ?>"
                   class="btn btn-sm btn-outline-secondary">Preview</a>
                <?php endif; ?>
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

<?php if (count($monthlySummary) > 1 || !empty($annualComparison)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
<?php if (count($monthlySummary) > 1): ?>
  const ctx = document.getElementById('monthlyChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode(array_map(fn($m) => month_short((int)$m['month']) . '/' . substr($m['year'], -2), $monthlySummary)) ?>,
      datasets: [
        {
          label: 'Receitas',
          data: <?= json_encode(array_map(fn($m) => (float)$m['receita'], $monthlySummary)) ?>,
          borderColor: '#34d399',
          backgroundColor: 'rgba(52, 211, 153, 0.08)',
          fill: true,
          tension: 0.4,
          pointRadius: 3,
          pointBackgroundColor: '#fff',
          pointBorderWidth: 2
        },
        {
          label: 'Custos/Despesas',
          data: <?= json_encode(array_map(fn($m) => abs((float)$m['custo_despesa']), $monthlySummary)) ?>,
          borderColor: '#f87171',
          backgroundColor: 'rgba(248, 113, 113, 0.08)',
          fill: true,
          tension: 0.4,
          pointRadius: 3,
          pointBackgroundColor: '#fff',
          pointBorderWidth: 2
        },
        {
          label: 'Resultado',
          data: <?= json_encode(array_map(fn($m) => (float)$m['resultado'], $monthlySummary)) ?>,
          borderColor: '#60a5fa',
          backgroundColor: 'rgba(96, 165, 250, 0.08)',
          fill: true,
          tension: 0.4,
          pointRadius: 3,
          pointBackgroundColor: '#fff',
          pointBorderWidth: 2
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'top',
          labels: {
            usePointStyle: true,
            boxWidth: 8,
            font: { size: 12 }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { size: 11 }, color: '#94a3b8' }
        },
        y: {
          beginAtZero: true,
          grid: { color: '#f1f5f9' },
          ticks: {
            font: { size: 11 },
            color: '#94a3b8',
            callback: function(value) {
              return value.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            }
          }
        }
      }
    }
  });
<?php endif; ?>

</script>
<?php endif; ?>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>
