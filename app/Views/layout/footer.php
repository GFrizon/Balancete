</main>

<?php if (empty($hideFooter)): ?>
  <footer class="app-footer">
    <div class="container-fluid text-center">
      <small class="text-muted">
        <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> &mdash; <?= date('Y') ?>
      </small>
    </div>
  </footer>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<?php if (isset($extraJs)): ?>
<?= $extraJs ?>
<?php endif; ?>
</body>
</html>
