<?php $pageTitle = 'Grupos'; ?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<div class="container py-4" style="max-width:1000px">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i>Grupos</h4>
      <small class="text-muted">Agrupe unidades que o fiscal analisa em conjunto</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
      <i class="bi bi-plus-lg me-1"></i>Novo Grupo
    </button>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Nome</th>
              <th>Unidades</th>
              <th>Status</th>
              <th>Desde</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($groups as $group): ?>
            <?php $selected = $groupItems[(int)$group['id']] ?? []; ?>
            <tr>
              <td class="fw-semibold"><?= e($group['name']) ?></td>
              <td><?= (int)$group['units_count'] ?></td>
              <td><?= $group['active'] ? '<span class="badge bg-success-subtle text-success border border-success">Ativo</span>' : '<span class="badge bg-danger-subtle text-danger border border-danger">Inativo</span>' ?></td>
              <td><small class="text-muted"><?= date('d/m/Y', strtotime($group['created_at'])) ?></small></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-secondary"
                          onclick="editGroup(<?= e(json_encode([
                              'id' => (int)$group['id'],
                              'name' => $group['name'],
                              'active' => (int)$group['active'],
                              'unit_ids' => $selected,
                          ], JSON_UNESCAPED_UNICODE)) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" action="<?= url('groups/delete') ?>"
                        onsubmit="return confirm('Excluir o grupo <?= e(addslashes($group['name'])) ?>?')" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$group['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($groups)): ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">Nenhum grupo cadastrado.</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$renderUnitChecks = static function (array $units, string $prefix): void {
    foreach ($units as $unit):
?>
  <label class="list-group-item d-flex align-items-center gap-2">
    <input class="form-check-input m-0 <?= e($prefix) ?>-unit-check" type="checkbox" name="unit_ids[]" value="<?= (int)$unit['id'] ?>">
    <span>
      <span class="fw-semibold"><?= e($unit['code']) ?> - <?= e($unit['name']) ?></span>
      <small class="text-muted d-block"><?= e($unit['company_name']) ?></small>
    </span>
  </label>
<?php
    endforeach;
};
?>

<div class="modal fade" id="addGroupModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="<?= url('groups/store') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Novo Grupo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <label class="form-label fw-semibold">Unidades <span class="text-danger">*</span></label>
          <div class="list-group group-unit-list">
            <?php $renderUnitChecks($units, 'add'); ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Criar Grupo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editGroupModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="<?= url('groups/update') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="editGroupId">
        <div class="modal-header">
          <h5 class="modal-title">Editar Grupo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Nome</label>
              <input type="text" name="name" id="editGroupName" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Status</label>
              <select name="active" id="editGroupActive" class="form-select">
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>
          </div>
          <label class="form-label fw-semibold">Unidades</label>
          <div class="list-group group-unit-list">
            <?php $renderUnitChecks($units, 'edit'); ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php $extraJs = <<<'JS'
<script>
function editGroup(group) {
  document.getElementById('editGroupId').value = group.id;
  document.getElementById('editGroupName').value = group.name;
  document.getElementById('editGroupActive').value = group.active;

  const selected = new Set((group.unit_ids || []).map(Number));
  document.querySelectorAll('.edit-unit-check').forEach(input => {
    input.checked = selected.has(Number(input.value));
  });

  new bootstrap.Modal(document.getElementById('editGroupModal')).show();
}
</script>
JS; ?>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>
