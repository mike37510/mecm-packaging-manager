(function () {
  'use strict';

  var listEl = document.getElementById('bios-editor-list');
  var loadingEl = document.getElementById('bios-editor-loading');
  var errorEl = document.getElementById('bios-editor-error');
  var searchInput = document.getElementById('bios-editor-search');
  var exportBtn = document.getElementById('bios-editor-save');
  var statsEl = document.getElementById('bios-editor-stats');
  var helpModalEl = document.getElementById('bios-option-help-modal');
  var helpTitleEl = document.getElementById('bios-option-help-title');
  var helpShortEl = document.getElementById('bios-option-help-short');
  var helpDetailEl = document.getElementById('bios-option-help-detail');
  var helpValuesEl = document.getElementById('bios-option-help-values');
  if (!listEl || !loadingEl || !errorEl || !searchInput || !exportBtn || !statsEl) {
    return;
  }

  var i18n = window.BIOS_EDITOR_I18N || {};
  var DEFAULT_DESC = i18n.defaultDesc || 'Dell CCTK option.';
  var LABEL_VALUE = i18n.valueLabel || 'Value:';
  var PLACEHOLDER_VALUE = i18n.valuePlaceholder || 'Value (optional)';
  var STATS_TMPL = i18n.stats || '{checked} selected | {visible} options';
  var ERR_MISSING = i18n.errMissing || 'Options data missing.';
  var CFG_SECTION_TITLE = i18n.cfgSectionTitle || 'Configurable options';
  var CFG_SECTION_LEAD = i18n.cfgSectionLead || 'Select options to include in the exported .ini.';
  var HELP_BTN = i18n.helpBtn || 'More info';
  var HELP_VALUES = i18n.helpValues || 'Possible values:';

  var HELP = window.CCTK_HELP || {};
  var options = [];
  var helpModal = null;

  function getHelp(name) {
    var h = HELP[name];
    if (h && h.short) return h;
    return { short: DEFAULT_DESC, detail: DEFAULT_DESC, values: [] };
  }

  function getValue(opt, selectedIndex) {
    if (!opt.values || opt.values.length === 0) return '';
    var idx = selectedIndex >= 0 && selectedIndex < opt.values.length ? selectedIndex : opt.defaultIndex;
    return opt.values[idx] || '';
  }

  function escapeHtml(s) {
    if (!s) return '';
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  function detailToHtml(detail) {
    return escapeHtml(detail || '')
      .split(/\n\n+/)
      .map(function (p) { return '<p>' + p.replace(/\n/g, '<br>') + '</p>'; })
      .join('');
  }

  function openHelp(name, values) {
    var h = getHelp(name);
    if (!helpModalEl || !helpTitleEl || !helpShortEl || !helpDetailEl || !helpValuesEl) return;
    helpTitleEl.textContent = name;
    helpShortEl.textContent = h.short || '';
    helpDetailEl.innerHTML = detailToHtml(h.detail || '');
    var vals = (h.values && h.values.length) ? h.values : (values || []);
    if (vals.length && !(vals.length === 1 && vals[0] === '')) {
      helpValuesEl.hidden = false;
      helpValuesEl.innerHTML = '<strong>' + escapeHtml(HELP_VALUES) + '</strong> ' +
        vals.map(function (v) { return '<code>' + escapeHtml(String(v)) + '</code>'; }).join(' ');
    } else {
      helpValuesEl.hidden = true;
      helpValuesEl.innerHTML = '';
    }
    if (window.bootstrap && window.bootstrap.Modal) {
      if (!helpModal) helpModal = window.bootstrap.Modal.getOrCreateInstance(helpModalEl);
      helpModal.show();
    }
  }

  function makeSection(className, title, lead) {
    var section = document.createElement('section');
    section.className = 'bios-option-section ' + className;
    section.innerHTML =
      '<header class="bios-option-section-head">' +
        '<h2 class="bios-option-section-title">' + escapeHtml(title) + '</h2>' +
        '<p class="bios-option-section-lead">' + escapeHtml(lead) + '</p>' +
      '</header>' +
      '<div class="bios-option-section-body"></div>';
    return { el: section, body: section.querySelector('.bios-option-section-body') };
  }

  function renderCfgCard(opt, index) {
    var card = document.createElement('div');
    card.className = 'bios-option';
    card.dataset.index = String(index);
    card.dataset.name = opt.name.toLowerCase();

    var hasChoices = opt.values && opt.values.length > 1;
    var currentVal = getValue(opt, opt.defaultIndex);
    var help = getHelp(opt.name);

    card.innerHTML =
      '<div class="bios-option-header">' +
        '<label class="bios-option-check"><input type="checkbox" class="form-check-input" data-index="' + index + '" /></label>' +
        '<span class="bios-option-name">' + escapeHtml(opt.name) + '</span>' +
        '<button type="button" class="btn btn-sm btn-outline-secondary bios-option-info" title="' + escapeHtml(HELP_BTN) + '" aria-label="' + escapeHtml(HELP_BTN) + '">i</button>' +
      '</div>' +
      '<p class="bios-option-desc">' + escapeHtml(help.short) + '</p>' +
      '<div class="bios-option-value">' +
        '<label class="form-label mb-0">' + escapeHtml(LABEL_VALUE) + '</label>' +
        (hasChoices
          ? '<select class="form-select form-select-sm" data-index="' + index + '">' +
              opt.values.map(function (v, i) {
                return '<option value="' + i + '"' + (i === opt.defaultIndex ? ' selected' : '') + '>' + escapeHtml(v) + '</option>';
              }).join('') +
            '</select>'
          : '<input class="form-control form-control-sm" type="text" data-index="' + index + '" value="' + escapeHtml(currentVal) + '" placeholder="' + escapeHtml(PLACEHOLDER_VALUE) + '" />') +
      '</div>';

    var cb = card.querySelector('input[type="checkbox"]');
    var valueCtrl = card.querySelector('select, input[type="text"]');
    var infoBtn = card.querySelector('.bios-option-info');
    cb.addEventListener('change', function () { options[index].checked = cb.checked; updateStats(); });
    valueCtrl.addEventListener('change', function () {
      if (valueCtrl.tagName === 'SELECT') options[index].selectedIndex = parseInt(valueCtrl.value, 10);
      else options[index].customValue = valueCtrl.value;
    });
    valueCtrl.addEventListener('input', function () {
      if (valueCtrl.tagName === 'INPUT') options[index].customValue = valueCtrl.value;
    });
    infoBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      openHelp(opt.name, opt.values || []);
    });

    options[index].checked = false;
    options[index].selectedIndex = opt.defaultIndex;
    options[index].customValue = hasChoices ? undefined : currentVal;
    return card;
  }

  function render() {
    listEl.innerHTML = '';
    var cfgSection = makeSection('bios-option-section-cfg', CFG_SECTION_TITLE, CFG_SECTION_LEAD);
    options.forEach(function (opt, index) {
      cfgSection.body.appendChild(renderCfgCard(opt, index));
    });
    listEl.appendChild(cfgSection.el);
    loadingEl.hidden = true;
    updateStats();
  }

  function filterList() {
    var q = searchInput.value.trim().toLowerCase();
    listEl.querySelectorAll('.bios-option').forEach(function (card) {
      card.hidden = !!(q && card.dataset.name.indexOf(q) === -1);
    });
    listEl.querySelectorAll('.bios-option-section').forEach(function (section) {
      section.hidden = !section.querySelector('.bios-option:not([hidden])');
    });
    updateStats();
  }

  function updateStats() {
    var total = options.length;
    var visible = listEl.querySelectorAll('.bios-option:not([hidden])').length;
    var checked = options.filter(function (o) { return o.checked; }).length;
    statsEl.textContent = STATS_TMPL
      .replace('{checked}', String(checked))
      .replace('{visible}', total === visible ? String(total) : (visible + ' / ' + total));
  }

  function buildIniContent() {
    var lines = [';cctk]', '; Generated by MECM Packaging Manager'];
    var selected = 0;
    options.forEach(function (opt) {
      if (!opt.checked || !opt.name) return;
      var hasChoices = opt.values && opt.values.length > 1;
      var value;
      if (hasChoices) {
        var sel = typeof opt.selectedIndex === 'number' ? opt.selectedIndex : opt.defaultIndex;
        if (sel < 0 || sel >= opt.values.length) sel = opt.defaultIndex || 0;
        value = opt.values[sel];
      } else {
        value = (opt.customValue !== undefined ? opt.customValue : getValue(opt, 0));
      }
      if (value === undefined || value === null) return;
      value = String(value).trim();
      if (value === '') return;
      lines.push(opt.name + '=' + value);
      selected++;
    });
    return { content: lines.join('\r\n') + '\r\n', selected: selected };
  }

  function showSaveFeedback(ok, message) {
    var el = document.getElementById('bios-editor-save-feedback');
    if (!el) { window.alert(message); return; }
    el.hidden = false;
    el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
    el.textContent = message;
  }

  function saveCfgVersion() {
    var saveUrl = exportBtn.getAttribute('data-save-url');
    var csrf = exportBtn.getAttribute('data-csrf');
    var needSel = exportBtn.getAttribute('data-need-selection') || 'Select at least one option.';
    var savingLabel = exportBtn.getAttribute('data-saving') || 'Saving…';
    if (!saveUrl || !csrf) return;

    var built = buildIniContent();
    if (built.selected < 1) {
      showSaveFeedback(false, needSel);
      return;
    }

    var noteEl = document.getElementById('bios-editor-note');
    var note = noteEl ? noteEl.value.trim() : '';
    var prevLabel = exportBtn.textContent;
    exportBtn.disabled = true;
    exportBtn.textContent = savingLabel;

    // Base64: évite que certains filtres HTTP vident le POST quand la valeur
    // commence par une section INI "[cctk]" (crochets).
    var body = new URLSearchParams();
    body.set('_csrf', csrf);
    body.set('content_b64', btoa(unescape(encodeURIComponent(built.content))));
    body.set('note', note);

    fetch(saveUrl, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (res) {
      return res.text().then(function (text) {
        var data = null;
        try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
        return { status: res.status, data: data, text: text };
      });
    }).then(function (pack) {
      var data = pack.data || {};
      if (data.ok) {
        showSaveFeedback(true, data.message || 'OK');
        window.location.href = data.redirect || (i18n.redirect || '/bios/dell/cfg-editor');
        return;
      }
      var err = data.error || (pack.text && pack.text.trim()) || ('HTTP ' + pack.status);
      if (err.length > 300) err = err.slice(0, 300) + '…';
      showSaveFeedback(false, err);
      exportBtn.disabled = false;
      exportBtn.textContent = prevLabel;
    }).catch(function (err) {
      showSaveFeedback(false, (err && err.message) ? err.message : 'Network error');
      exportBtn.disabled = false;
      exportBtn.textContent = prevLabel;
    });
  }

  searchInput.addEventListener('input', filterList);
  if (exportBtn.getAttribute('data-save-url')) {
    exportBtn.addEventListener('click', saveCfgVersion);
  }

  if (typeof window.CCTK_OPTIONS !== 'undefined' && window.CCTK_OPTIONS.length) {
    options = window.CCTK_OPTIONS;
    render();
  } else {
    loadingEl.hidden = true;
    errorEl.hidden = false;
    errorEl.textContent = ERR_MISSING;
  }
})();
