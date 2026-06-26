/* ============================================================
   BALANCETE DRE — JavaScript Global
   ============================================================ */

(function () {
  'use strict';

  // -------------------------------------------------------
  // Auto-fechar alertas após 5 segundos
  // -------------------------------------------------------
  document.querySelectorAll('.flash-container .alert').forEach(function (el) {
    setTimeout(function () {
      var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      if (bsAlert) bsAlert.close();
    }, 5000);
  });

  // -------------------------------------------------------
  // Confirmação em formulários destrutivos
  // -------------------------------------------------------
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!confirm(form.dataset.confirm || 'Confirma esta ação?')) {
        e.preventDefault();
      }
    });
  });

  // -------------------------------------------------------
  // Formatação de inputs monetários (opcional)
  // -------------------------------------------------------
  document.querySelectorAll('input.money-input').forEach(function (input) {
    input.addEventListener('blur', function () {
      var v = parseFloat(this.value.replace(',', '.'));
      if (!isNaN(v)) {
        this.value = v.toFixed(2).replace('.', ',');
      }
    });
  });

  // -------------------------------------------------------
  // Tooltip Bootstrap (inicializar todos)
  // -------------------------------------------------------
  var tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  tooltipEls.forEach(function (el) {
    new bootstrap.Tooltip(el);
  });

  // -------------------------------------------------------
  // Popover Bootstrap (inicializar todos)
  // -------------------------------------------------------
  var popoverEls = document.querySelectorAll('[data-bs-toggle="popover"]');
  popoverEls.forEach(function (el) {
    new bootstrap.Popover(el);
  });

  // -------------------------------------------------------
  // Select "todos/nenhum" para checkboxes em tabelas
  // -------------------------------------------------------
  var selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.row-check').forEach(function (cb) {
        cb.checked = selectAll.checked;
      });
    });
  }

  // -------------------------------------------------------
  // Utilitários globais expostos no window
  // -------------------------------------------------------
  window.App = {

    /**
     * Formata número para BRL.
     * @param {number} v
     * @param {number} decimals
     * @returns {string}
     */
    formatBrl: function (v, decimals) {
      decimals = decimals !== undefined ? decimals : 2;
      return v.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      });
    },

    /**
     * Formata percentual.
     * @param {number} v
     * @returns {string}
     */
    formatPct: function (v) {
      return v.toLocaleString('pt-BR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
      }) + '%';
    },

    /**
     * Escapa HTML.
     * @param {string} s
     * @returns {string}
     */
    esc: function (s) {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    },

    /**
     * Debounce simples.
     * @param {Function} fn
     * @param {number} delay
     * @returns {Function}
     */
    debounce: function (fn, delay) {
      var timer;
      return function () {
        clearTimeout(timer);
        timer = setTimeout(fn.apply.bind(fn, this, arguments), delay);
      };
    },
  };

  // -------------------------------------------------------
  // Busca na DRE com debounce (reforço além do inline)
  // -------------------------------------------------------
  var dreSearch = document.getElementById('dreSearch');
  if (dreSearch) {
    dreSearch.addEventListener('input', window.App.debounce(function () {
      var q = this.value.toLowerCase();
      document.querySelectorAll('#dreBody tr').forEach(function (tr) {
        var label = tr.querySelector('.dre-label-cell');
        if (!label) return;
        var match = label.textContent.toLowerCase().indexOf(q) !== -1;
        tr.classList.toggle('dre-filtered', !match);
      });
    }, 200));
  }

})();
