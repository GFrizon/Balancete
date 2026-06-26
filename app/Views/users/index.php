<?php $pageTitle = 'Usuários'; ?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<div class="container py-4" style="max-width:900px">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Usuários</h4>
      <small class="text-muted">Gerenciamento de acesso ao sistema</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
      <i class="bi bi-plus-lg me-1"></i>Novo Usuário
    </button>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Perfil</th>
              <th>Status</th>
              <th>Desde</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td class="fw-semibold"><?= e($u['name']) ?></td>
              <td><?= e($u['email']) ?></td>
              <td>
                <span class="badge bg-<?= $u['role']==='admin'?'primary':'secondary' ?>">
                  <?= $u['role'] === 'admin' ? 'Administrador' : 'Usuário' ?>
                </span>
              </td>
              <td><?= $u['active'] ? '<span class="badge bg-success-subtle text-success border border-success">Ativo</span>' : '<span class="badge bg-danger-subtle text-danger border border-danger">Inativo</span>' ?></td>
              <td><small class="text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></small></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-secondary" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <?php if ($u['id'] != current_user_id()): ?>
                  <form method="POST" action="<?= url('users/delete') ?>"
                        onsubmit="return confirm('Excluir o usuário \'<?= e(addslashes($u['name'])) ?>\'?')" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Adicionar -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="<?= url('users/store') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Novo Usuário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">E-mail <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Senha <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" required minlength="10" autocomplete="new-password">
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold">Perfil</label>
            <select name="role" class="form-select">
              <option value="user">Usuário</option>
              <option value="admin">Administrador</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Criar Usuário</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="<?= url('users/update') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="editUserId">
        <div class="modal-header">
          <h5 class="modal-title">Editar Usuário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nome</label>
            <input type="text" name="name" id="editUserName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">E-mail</label>
            <input type="email" name="email" id="editUserEmail" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Nova senha <small class="text-muted">(deixe em branco para não alterar)</small></label>
            <input type="password" name="new_password" class="form-control" minlength="10" autocomplete="new-password">
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label fw-semibold">Perfil</label>
              <select name="role" id="editUserRole" class="form-select">
                <option value="user">Usuário</option>
                <option value="admin">Administrador</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Status</label>
              <select name="active" id="editUserActive" class="form-select">
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>
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
function editUser(u) {
  document.getElementById('editUserId').value    = u.id;
  document.getElementById('editUserName').value  = u.name;
  document.getElementById('editUserEmail').value = u.email;
  document.getElementById('editUserRole').value  = u.role;
  document.getElementById('editUserActive').value = u.active;
  new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>
JS; ?>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>
