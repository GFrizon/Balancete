<?php $pageTitle = 'Nova Importação'; ?>
<?php require APP_ROOT . '/app/Views/layout/header.php'; ?>

<div class="container py-4" style="max-width:680px">
  <div class="d-flex align-items-center mb-4">
    <a href="<?= url('imports') ?>" class="btn btn-sm btn-outline-secondary me-3">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-cloud-upload me-2 text-primary"></i>Nova Importação</h4>
      <small class="text-muted">Upload do balancete mensal</small>
    </div>
  </div>

  <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div>
      <strong>Importante:</strong> O sistema utilizará <strong>exclusivamente a coluna Movimento</strong> do balancete.
      As colunas Débito e Crédito serão registradas apenas para auditoria.
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <form method="POST" action="<?= url('imports/create') ?>" enctype="multipart/form-data" id="uploadForm">
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label fw-semibold">Empresa <small class="text-muted">(opcional)</small></label>
          <select name="company_id" id="companySelect" class="form-select">
            <option value="">Detectar automaticamente pelo CNPJ do arquivo</option>
            <?php foreach ($companies as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Unidade de Negócio <small class="text-muted">(opcional)</small></label>
          <select name="unit_id" id="unitSelect" class="form-select">
            <option value="">Detectar automaticamente pelo cabeçalho do arquivo</option>
            <?php foreach ($units as $u): ?>
            <option value="<?= $u['id'] ?>" data-company="<?= $u['company_id'] ?>"><?= e($u['code']) ?> — <?= e($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label fw-semibold">Ano <small class="text-muted">(se não detectado)</small></label>
            <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" min="2000" max="2099">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Mês <small class="text-muted">(se não detectado)</small></label>
            <select name="month" class="form-select">
              <?php foreach (MONTHS_PT as $n => $name): ?>
              <option value="<?= $n ?>" <?= (int)date('n') === $n ? 'selected' : '' ?>><?= $name ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold">Arquivo do Balancete <span class="text-danger">*</span></label>
          <div class="upload-area" id="uploadArea">
            <input type="file" name="balancete" id="fileInput" class="d-none"
                   accept=".txt,.rtf,.doc" required>
            <div class="upload-area-inner" onclick="document.getElementById('fileInput').click()">
              <i class="bi bi-file-earmark-text fs-2 text-muted mb-2"></i>
              <div class="fw-semibold">Clique para selecionar ou arraste o arquivo aqui</div>
              <div class="text-muted small mt-1">Formatos aceitos: TXT, RTF, DOC — Máximo 20 MB</div>
              <div id="fileName" class="mt-2 text-primary fw-semibold d-none"></div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-lg flex-fill" id="submitBtn">
            <i class="bi bi-cloud-upload me-2"></i>Fazer Upload e Processar
          </button>
          <a href="<?= url('imports') ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php $extraJs = <<<JS
<script>
// Filtrar unidades por empresa
const companySelect = document.getElementById('companySelect');
const unitSelect    = document.getElementById('unitSelect');
const allOptions    = Array.from(unitSelect.querySelectorAll('option[data-company]'));

companySelect.addEventListener('change', function() {
  const companyId = this.value;
  unitSelect.innerHTML = '<option value="">Detectar automaticamente pelo cabeçalho do arquivo</option>';
  allOptions.forEach(opt => {
    if (!companyId || opt.dataset.company === companyId) {
      unitSelect.appendChild(opt.cloneNode(true));
    }
  });
});

// Preview do arquivo selecionado
document.getElementById('fileInput').addEventListener('change', function() {
  const fn = document.getElementById('fileName');
  if (this.files.length > 0) {
    fn.textContent = this.files[0].name;
    fn.classList.remove('d-none');
    document.querySelector('.upload-area-inner .bi').className = 'bi bi-file-earmark-check fs-2 text-success mb-2';
  }
});

// Drag & drop
const area = document.getElementById('uploadArea');
['dragover','dragenter'].forEach(e => area.addEventListener(e, ev => {
  ev.preventDefault(); area.classList.add('drag-over');
}));
['dragleave','drop'].forEach(e => area.addEventListener(e, ev => {
  area.classList.remove('drag-over');
}));
area.addEventListener('drop', function(ev) {
  ev.preventDefault();
  const fi = document.getElementById('fileInput');
  fi.files = ev.dataTransfer.files;
  fi.dispatchEvent(new Event('change'));
});

// Loading state no submit
document.getElementById('uploadForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';
  btn.disabled = true;
});
</script>
JS; ?>

<?php require APP_ROOT . '/app/Views/layout/footer.php'; ?>
