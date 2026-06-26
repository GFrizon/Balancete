<?php $pageTitle = 'Meu Perfil'; ?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<div class="container py-4" style="max-width:560px">
  <h4 class="fw-bold mb-4"><i class="bi bi-person-circle me-2 text-primary"></i>Meu Perfil</h4>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <form method="POST" action="<?= url('users/profile') ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label fw-semibold">Nome</label>
          <input type="text" name="name" class="form-control" required value="<?= e($user['name']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">E-mail</label>
          <input type="email" name="email" class="form-control" required value="<?= e($user['email']) ?>">
        </div>
        <hr>
        <p class="text-muted small mb-3">Deixe os campos de senha em branco para não alterar.</p>
        <div class="mb-3">
          <label class="form-label fw-semibold">Senha atual</label>
          <input type="password" name="old_password" class="form-control" placeholder="Necessario para trocar a senha" autocomplete="current-password">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Nova senha</label>
          <input type="password" name="new_password" class="form-control" minlength="10" placeholder="Minimo 10 caracteres, com letras e numeros" autocomplete="new-password">
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Confirmar nova senha</label>
          <input type="password" name="confirm_password" class="form-control" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-save me-1"></i>Salvar Alterações
        </button>
      </form>
    </div>
  </div>
</div>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>
