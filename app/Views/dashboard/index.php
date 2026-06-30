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
            <div class="d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: #ecfdf5; border-radius: 7px;">
              <i class="bi bi-buildings text-success"></i>
            </div>
            <div>
              <h5 class="fw-semibold mb-0" style="color: #1e293b;">Comparativo por Filial</h5>
              <small class="text-muted"><?= e($accPeriod['label'] ?? '') ?></small>
            </div>
          </div>
          <div class="d-flex align-items-center justify-content-between gap-3 mb-3 flex-wrap" style="font-size: .82rem;">
            <div class="text-muted">Receita Total <span class="fw-semibold" style="color: #1e293b;"><?= format_brl((float)($accTotals['receita'] ?? 0)) ?></span></div>
            <div class="text-muted">Resultado <span class="fw-semibold <?= (float)($accTotals['resultado'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>"><?= format_brl((float)($accTotals['resultado'] ?? 0)) ?></span></div>
            <div class="text-muted">Positivas <span class="fw-semibold" style="color: #1e293b;"><?= (int)($accTotals['positive_units'] ?? 0) ?>/<?= (int)($accTotals['units'] ?? 0) ?></span></div>
          </div>
          <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-sm table-hover mb-0 align-middle" style="font-size: .75rem;">
              <thead style="position: sticky; top: 0; background: white; z-index: 10; border-bottom: 2px solid #e2e8f0;">
                <tr>
                  <th class="border-0 text-muted fw-semibold py-2" style="font-size: .7rem; text-transform: uppercase; letter-spacing: .03em;">Filial</th>
                  <th class="border-0 text-end text-muted fw-semibold py-2" style="font-size: .7rem; text-transform: uppercase; letter-spacing: .03em;">Receita</th>
                  <th class="border-0 text-end text-muted fw-semibold py-2" style="font-size: .7rem; text-transform: uppercase; letter-spacing: .03em;">Custo</th>
                  <th class="border-0 text-end text-muted fw-semibold py-2" style="font-size: .7rem; text-transform: uppercase; letter-spacing: .03em;">Desp. Op.</th>
                  <th class="border-0 text-end text-muted fw-semibold py-2" style="font-size: .7rem; text-transform: uppercase; letter-spacing: .03em;">Resultado</th>
                  <th class="border-0 text-end text-muted fw-semibold py-2" style="font-size: .7rem; text-transform: uppercase; letter-spacing: .03em;">Margem</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($accountComparison['rows'] as $unit): ?>
                <?php
                  $receita = (float)$unit['receita_acum'];
                  $receitaMedia = (float)$unit['receita_media'];
                  $custo = (float)$unit['custo_acum'];
                  $custoMedia = (float)$unit['custo_media'];
                  $despOp = (float)$unit['desp_operacionais_acum'];
                  $despOpMedia = (float)$unit['desp_operacionais_media'];
                  $resultado = (float)$unit['resultado_acum'];
                  $resultadoMedia = (float)$unit['resultado_media'];
                  $margin = (float)$unit['margin'];
                  $isBest = $bestMargin && $unit['unit_id'] === $bestMargin['unit_id'];
                  $isWorst = $worstResult && $unit['unit_id'] === $worstResult['unit_id'];
                ?>
                <tr style="border-bottom: 1px solid #f1f5f9; <?= $isBest ? 'background: #f0fdf4;' : ($isWorst ? 'background: #fef2f2;' : '') ?>">
                  <td class="py-2 ps-0">
                    <div class="fw-semibold" style="color: #1e293b; font-size: .8rem;">
                      <?= e($unit['unit_code']) ?>
                      <?php if ($isBest): ?><i class="bi bi-trophy-fill text-warning ms-1" title="Melhor margem"></i><?php endif; ?>
                      <?php if ($isWorst && $resultado < 0): ?><i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Pior resultado"></i><?php endif; ?>
                    </div>
                    <small class="text-muted" style="font-size: .68rem;"><?= e($unit['unit_name']) ?></small>
                  </td>
                  <td class="py-2 text-end">
                    <div class="fw-semibold" style="color: #1e293b;"><?= format_brl($receita) ?></div>
                    <small class="text-muted" style="font-size: .68rem;">Méd: <?= format_brl($receitaMedia) ?></small>
                  </td>
                  <td class="py-2 text-end">
                    <div class="fw-semibold text-danger"><?= format_brl($custo) ?></div>
                    <small class="text-muted" style="font-size: .68rem;">Méd: <?= format_brl($custoMedia) ?></small>
                  </td>
                  <td class="py-2 text-end">
                    <div class="fw-semibold text-danger"><?= format_brl($despOp) ?></div>
                    <small class="text-muted" style="font-size: .68rem;">Méd: <?= format_brl($despOpMedia) ?></small>
                  </td>
                  <td class="py-2 text-end">
                    <div class="fw-semibold <?= $resultado < 0 ? 'text-danger' : 'text-success' ?>"><?= format_brl($resultado) ?></div>
                    <small class="text-muted" style="font-size: .68rem;">Méd: <?= format_brl($resultadoMedia) ?></small>
                  </td>
                  <td class="py-2 pe-0 text-end">
                    <span class="badge <?= $margin >= 10 ? 'bg-success' : ($margin >= 5 ? 'bg-warning' : 'bg-danger') ?>" style="font-size: .7rem;">
                      <?= number_format($margin, 1, ',', '.') ?>%
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-2 pt-2 border-top" style="font-size: .72rem;">
            <div class="d-flex justify-content-between text-muted">
              <span><i class="bi bi-info-circle me-1"></i>Acumulado e média mensal do ano atual</span>
              <span><?= (int)($accountComparison['months_count'] ?? 0) ?> meses</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Detalhamento completo das contas por filial -->
  <?php if (!empty($accountComparison['rows'])): ?>
  <div class="row g-3 mb-5">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center gap-2">
              <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #dbeafe; border-radius: 8px;">
                <i class="bi bi-table text-primary"></i>
              </div>
              <div>
                <h5 class="fw-semibold mb-0" style="color: #1e293b;">Detalhamento de Contas por Filial</h5>
                <small class="text-muted"><?= e($accPeriod['label'] ?? '') ?> - Acumulado e Média Mensal</small>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle" style="font-size: .78rem;">
              <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                <tr>
                  <th class="border-0 fw-semibold py-3" style="color: #475569;">Filial</th>
                  <th class="border-0 text-end fw-semibold py-3" style="color: #475569;">Receita Líquida</th>
                  <th class="border-0 text-end fw-semibold py-3" style="color: #475569;">Devoluções</th>
                  <th class="border-0 text-end fw-semibold py-3" style="color: #475569;">Custo Produtos</th>
                  <th class="border-0 text-end fw-semibold py-3" style="color: #475569;">Desp. Operacionais</th>
                  <th class="border-0 text-end fw-semibold py-3" style="color: #475569;">Desp. Administrativas</th>
                  <th class="border-0 text-end fw-semibold py-3" style="color: #475569;">Resultado Líquido</th>
                  <th class="border-0 text-center fw-semibold py-3" style="color: #475569;">Margem</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($accountComparison['rows'] as $unit): ?>
                <?php
                  $receita = (float)$unit['receita_acum'];
                  $receitaMedia = (float)$unit['receita_media'];
                  $devolucoes = (float)$unit['devolucoes_acum'];
                  $devolucoesMedia = (float)$unit['devolucoes_media'];
                  $custo = (float)$unit['custo_acum'];
                  $custoMedia = (float)$unit['custo_media'];
                  $despOp = (float)$unit['desp_operacionais_acum'];
                  $despOpMedia = (float)$unit['desp_operacionais_media'];
                  $despAdm = (float)$unit['desp_administrativas_acum'];
                  $despAdmMedia = (float)$unit['desp_administrativas_media'];
                  $resultado = (float)$unit['resultado_acum'];
                  $resultadoMedia = (float)$unit['resultado_media'];
                  $margin = (float)$unit['margin'];
                  $isBest = $bestMargin && $unit['unit_id'] === $bestMargin['unit_id'];
                  $isWorst = $worstResult && $unit['unit_id'] === $worstResult['unit_id'];
                ?>
                <tr style="border-bottom: 1px solid #f1f5f9; <?= $isBest ? 'background: #f0fdf4;' : ($isWorst && $resultado < 0 ? 'background: #fef2f2;' : '') ?>">
                  <td class="py-3">
                    <div class="d-flex align-items-center gap-2">
                      <div>
                        <div class="fw-semibold" style="color: #1e293b;">
                          <?= e($unit['unit_code']) ?>
                          <?php if ($isBest): ?><i class="bi bi-trophy-fill text-warning ms-1" title="Melhor margem"></i><?php endif; ?>
                          <?php if ($isWorst && $resultado < 0): ?><i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Pior resultado"></i><?php endif; ?>
                        </div>
                        <small class="text-muted" style="font-size: .7rem;"><?= e($unit['unit_name']) ?></small>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 text-end">
                    <div class="fw-semibold text-success"><?= format_brl($receita) ?></div>
                    <small class="text-muted" style="font-size: .7rem;">Méd: <?= format_brl($receitaMedia) ?></small>
                  </td>
                  <td class="py-3 text-end">
                    <div class="fw-semibold text-warning"><?= format_brl($devolucoes) ?></div>
                    <small class="text-muted" style="font-size: .7rem;">Méd: <?= format_brl($devolucoesMedia) ?></small>
                  </td>
                  <td class="py-3 text-end">
                    <div class="fw-semibold text-danger"><?= format_brl($custo) ?></div>
                    <small class="text-muted" style="font-size: .7rem;">Méd: <?= format_brl($custoMedia) ?></small>
                  </td>
                  <td class="py-3 text-end">
                    <div class="fw-semibold text-danger"><?= format_brl($despOp) ?></div>
                    <small class="text-muted" style="font-size: .7rem;">Méd: <?= format_brl($despOpMedia) ?></small>
                  </td>
                  <td class="py-3 text-end">
                    <div class="fw-semibold text-danger"><?= format_brl($despAdm) ?></div>
                    <small class="text-muted" style="font-size: .7rem;">Méd: <?= format_brl($despAdmMedia) ?></small>
                  </td>
                  <td class="py-3 text-end">
                    <div class="fw-bold <?= $resultado < 0 ? 'text-danger' : 'text-success' ?>"><?= format_brl($resultado) ?></div>
                    <small class="text-muted" style="font-size: .7rem;">Méd: <?= format_brl($resultadoMedia) ?></small>
                  </td>
                  <td class="py-3 text-center">
                    <span class="badge <?= $margin >= 10 ? 'bg-success' : ($margin >= 5 ? 'bg-warning text-dark' : 'bg-danger') ?>" style="font-size: .75rem; padding: .4rem .6rem;">
                      <?= number_format($margin, 1, ',', '.') ?>%
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot style="background: #f8fafc; border-top: 2px solid #e2e8f0;">
                <tr>
                  <td class="py-3 fw-bold" style="color: #1e293b;">TOTAL GERAL</td>
                  <td class="py-3 text-end fw-bold text-success">
                    <?= format_brl(array_sum(array_map(fn($u) => (float)$u['receita_acum'], $accountComparison['rows']))) ?>
                  </td>
                  <td class="py-3 text-end fw-bold text-warning">
                    <?= format_brl(array_sum(array_map(fn($u) => (float)$u['devolucoes_acum'], $accountComparison['rows']))) ?>
                  </td>
                  <td class="py-3 text-end fw-bold text-danger">
                    <?= format_brl(array_sum(array_map(fn($u) => (float)$u['custo_acum'], $accountComparison['rows']))) ?>
                  </td>
                  <td class="py-3 text-end fw-bold text-danger">
                    <?= format_brl(array_sum(array_map(fn($u) => (float)$u['desp_operacionais_acum'], $accountComparison['rows']))) ?>
                  </td>
                  <td class="py-3 text-end fw-bold text-danger">
                    <?= format_brl(array_sum(array_map(fn($u) => (float)$u['desp_administrativas_acum'], $accountComparison['rows']))) ?>
                  </td>
                  <td class="py-3 text-end fw-bold <?= (float)($accTotals['resultado'] ?? 0) < 0 ? 'text-danger' : 'text-success' ?>">
                    <?= format_brl((float)($accTotals['resultado'] ?? 0)) ?>
                  </td>
                  <td class="py-3 text-center">
                    <?php
                      $totalMargin = (float)($accTotals['receita'] ?? 0) > 0 
                        ? ((float)($accTotals['resultado'] ?? 0) / (float)($accTotals['receita'] ?? 0)) * 100 
                        : 0.0;
                    ?>
                    <span class="badge <?= $totalMargin >= 10 ? 'bg-success' : ($totalMargin >= 5 ? 'bg-warning text-dark' : 'bg-danger') ?>" style="font-size: .75rem; padding: .4rem .6rem;">
                      <?= number_format($totalMargin, 1, ',', '.') ?>%
                    </span>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

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
