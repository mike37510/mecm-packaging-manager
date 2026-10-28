function t(key, fallback) {
  var dict = window.PF_I18N || {};
  return (dict[key] != null && dict[key] !== '') ? dict[key] : (fallback || key);
}

document.addEventListener('DOMContentLoaded', () => {
    const boot = [
        initLoadingOverlay,
        initThemeToggle,
        initChangelogReader,
        initSoftwareListFilters,
        initDriverListFilters,
        initLogsFilters,
        initSoftwareListSync,
        initSoftwarePipeline,
        initStepTypeFields,
        initStepReorder,
        initDetectionUrlFields,
        initDriverForm,
        initProcedureWizard,
    ];
    boot.forEach((fn) => {
        try {
            fn();
        } catch (e) {
            console.error(fn.name, e);
        }
    });
});

/**
 * Un seul champ URL visible : fichier (URL fixe) ou GitHub Releases.
 */
function initDetectionUrlFields() {
    const selects = [
        document.getElementById('detection_method'),
        document.getElementById('pw_detection_method'),
    ].filter(Boolean);
    if (selects.length === 0) {
        return;
    }

    const looksGithub = (url) => /github\.com\//i.test(String(url || ''));

    const sync = (select) => {
        const method = select.value === 'github' ? 'github' : 'fixed_url';
        const root = select.closest('form') || select.closest('.pw-pane') || document;
        root.querySelectorAll('[data-url-for]').forEach((el) => {
            const forMode = el.getAttribute('data-url-for');
            el.hidden = forMode !== method;
        });
        if (method === 'github') {
            const gh = root.querySelector('#version_check_url, #pw_version_check_url');
            const dl = root.querySelector('#download_url, #pw_download_url');
            if (gh && dl && !String(gh.value || '').trim() && looksGithub(dl.value)) {
                gh.value = dl.value;
            }
        }
    };

    const adoptGithubUrl = (select, urlInput) => {
        if (!select || !urlInput || select.value === 'github') {
            return;
        }
        if (!looksGithub(urlInput.value)) {
            return;
        }
        select.value = 'github';
        sync(select);
    };

    selects.forEach((select) => {
        select.addEventListener('change', () => sync(select));
        const root = select.closest('form') || select.closest('.pw-pane') || document;
        const dl = root.querySelector('#download_url, #pw_download_url');
        const gh = root.querySelector('#version_check_url, #pw_version_check_url');
        dl?.addEventListener('input', () => adoptGithubUrl(select, dl));
        dl?.addEventListener('change', () => adoptGithubUrl(select, dl));
        gh?.addEventListener('input', () => adoptGithubUrl(select, gh));
        gh?.addEventListener('change', () => adoptGithubUrl(select, gh));
        adoptGithubUrl(select, dl);
        adoptGithubUrl(select, gh);
        sync(select);
    });
}

/**
 * Softwares list filters: apply on change / typing (no Filtrer button).
 */
function initSoftwareListFilters() {
    const form = document.getElementById('softwares-filters');
    if (!form) {
        return;
    }
    bindAutoFilterForm(form, '/softwares');
}

function initDriverListFilters() {
    const form = document.getElementById('drivers-filters');
    if (!form) {
        return;
    }
    bindAutoFilterForm(form, '/drivers');
}

function initDriverForm() {
    const form = document.getElementById('driver-form');
    if (!form) {
        return;
    }
    const mfr = document.getElementById('manufacturer');
    const arch = document.getElementById('architecture');
    const url = document.getElementById('download_url');
    const osWrap = document.getElementById('hp-os-wrap');
    const model = document.getElementById('model');
    if (!mfr || !url) {
        return;
    }

    const matrixX64 = form.dataset.hpMatrixX64 || '';
    const matrixX86 = form.dataset.hpMatrixX86 || '';
    const dellPlaceholder = 'https://www.dell.com/support/kbdoc/…';
    const isHpMatrix = (value) => /ftp\.hp\.com\/pub\/caps-softpaq\/cmit\/.+driverpack_matrix/i.test(String(value || ''));
    const isDellKb = (value) => /dell\.com\/support\/(kbdoc|article)\//i.test(String(value || ''));
    const hpMatrixForArch = () => (arch && arch.value === 'x86' ? matrixX86 : matrixX64);

    const apply = (fillUrl) => {
        const hp = mfr.value === 'hp';
        if (osWrap) {
            osWrap.hidden = !hp;
        }
        form.querySelectorAll('[data-mfr-help]').forEach((el) => {
            el.hidden = el.getAttribute('data-mfr-help') !== (hp ? 'hp' : 'dell');
        });
        url.placeholder = hp ? hpMatrixForArch() : dellPlaceholder;
        if (model) {
            model.required = hp;
        }
        if (!fillUrl) {
            return;
        }
        const current = String(url.value || '').trim();
        if (hp) {
            if (current === '' || isDellKb(current) || current === matrixX64 || current === matrixX86) {
                url.value = hpMatrixForArch();
            }
        } else if (isHpMatrix(current) && (current === matrixX64 || current === matrixX86)) {
            url.value = '';
        }
    };

    mfr.addEventListener('change', () => apply(true));
    arch?.addEventListener('change', () => {
        if (mfr.value !== 'hp') {
            return;
        }
        const current = String(url.value || '').trim();
        if (current === '' || current === matrixX64 || current === matrixX86) {
            url.value = hpMatrixForArch();
        }
    });
    apply(false);
}

/**
 * Logs page filters: apply on change / typing.
 */
function initLogsFilters() {
    const form = document.getElementById('logs-filters');
    if (!form) {
        return;
    }
    bindAutoFilterForm(form, '/logs');
}

/**
 * @param {HTMLFormElement} form
 * @param {string} basePath
 */
function bindAutoFilterForm(form, basePath) {
    let debounceTimer = null;
    const submitFilters = () => {
        const fd = new FormData(form);
        const params = new URLSearchParams();
        for (const [key, value] of fd.entries()) {
            const v = String(value).trim();
            if (v !== '') {
                params.set(key, v);
            }
        }
        const qs = params.toString();
        window.location.assign(basePath + (qs ? '?' + qs : ''));
    };

    form.querySelectorAll('select').forEach((el) => {
        el.addEventListener('change', () => {
            clearTimeout(debounceTimer);
            submitFilters();
        });
    });

    const search = form.querySelector('input[type="search"]');
    if (search) {
        search.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(submitFilters, 350);
        });
        search.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                submitFilters();
            }
        });
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        clearTimeout(debounceTimer);
        submitFilters();
    });
}

/**
 * Bulk “check for updates” on Logiciels / Pilotes lists.
 * Updates status dots without opening a fiche or running the pipeline.
 */
function initSoftwareListSync() {
    const syncAllBtn = document.getElementById('sync-all-btn');
    if (!syncAllBtn) {
        return;
    }

    const table = document.querySelector('[data-catalog-sync]')
        || document.getElementById('softwares-table')
        || document.getElementById('drivers-table');
    const urlPrefix = ((table && table.dataset.syncUrlPrefix) || '/softwares/').replace(/\/+$/, '');
    const idField = (table && table.dataset.syncIdField) || 'softwareId';
    const csrf = syncAllBtn.dataset.csrf || '';
    const summary = document.getElementById('sync-list-summary');
    const noneMsg = syncAllBtn.dataset.syncNone || t('sync_none', 'Aucun logiciel avec URL de téléchargement à synchroniser.');
    const finishedTpl = syncAllBtn.dataset.syncFinished
        || t('sync_finished', 'Synchro terminée : :updates mise(s) à jour, :ok à jour, :errors erreur(s) — sur :total logiciel(s).');
    const listMissing = syncAllBtn.dataset.listMissing || t('list_not_found', 'Liste logiciels introuvable.');
    const labelDefault = syncAllBtn.textContent.trim() || t('verify_updates', 'Vérifier les mises à jour');

    const setButtonBusy = (busy, label) => {
        syncAllBtn.disabled = !!busy;
        if (busy) {
            syncAllBtn.setAttribute('aria-busy', 'true');
            syncAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
                + escapeHtml(label || t('checking', 'Vérification…'));
        } else {
            syncAllBtn.removeAttribute('aria-busy');
            syncAllBtn.textContent = labelDefault;
        }
    };

    const showLoading = (message) => {
        setButtonBusy(true, message);
        if (window.AppLoading) {
            window.AppLoading.show(message);
        }
    };

    const hideLoading = () => {
        setButtonBusy(false);
        if (window.AppLoading) {
            window.AppLoading.hide();
        }
    };

    const applyRowResult = (row, data) => {
        const dot = row.querySelector('.sync-dot');
        if (!dot) {
            return;
        }

        const failed = data.ok === false || data.status === 'error';
        let cls = 'sync-dot sync-dot-muted';
        let title = t('never_synced', 'Jamais synchronisé');
        if (data.status === 'up_to_date') {
            cls = 'sync-dot sync-dot-ok';
            title = t('up_to_date', 'À jour');
        } else if (data.status === 'update_available') {
            cls = 'sync-dot sync-dot-ko';
            title = t('update_available', 'Mise à jour disponible');
        } else if (failed) {
            cls = 'sync-dot sync-dot-err';
            title = data.error || t('sync_error', 'Erreur de synchro');
        }

        dot.className = cls;
        dot.dataset.status = data.status || '';
        dot.title = title;
        dot.setAttribute('aria-label', title);

        const versionEl = row.querySelector('.software-version, .driver-version');
        const newVersion = data.current_version || data.remote_version || data.version;
        if (versionEl && newVersion) {
            versionEl.textContent = newVersion;
        }

        if (!failed && (data.status === 'up_to_date' || data.status === 'update_available')) {
            row.querySelectorAll('.software-overdue').forEach((el) => el.remove());
        }
    };

    const setRowBusy = (row, busy) => {
        if (!row) {
            return;
        }
        row.classList.toggle('sync-row-busy', !!busy);
        const dot = row.querySelector('.sync-dot');
        if (dot) {
            dot.classList.toggle('sync-dot-busy', !!busy);
        }
    };

    const syncOne = async (id, name) => {
        const body = new FormData();
        body.append('_csrf', csrf);
        body.append('redirect', 'list');

        const res = await fetch(urlPrefix + '/' + encodeURIComponent(id) + '/sync', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });

        let data;
        try {
            data = await res.json();
        } catch (e) {
            data = { ok: false, error: t('invalid_response', 'Réponse invalide pour :name').replace(':name', name || id), status: 'error' };
        }
        if (!res.ok && data.ok !== false) {
            data.ok = false;
            data.status = data.status || 'error';
            data.error = data.error || ('HTTP ' + res.status);
        }
        data.software_id = data.software_id || id;
        return data;
    };

    syncAllBtn.addEventListener('click', async (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (!table) {
            if (summary) {
                summary.hidden = false;
                summary.className = 'alert alert-warning py-2 small';
                summary.textContent = listMissing;
            }
            return;
        }

        const rows = [...table.querySelectorAll('tr[data-syncable="1"]')];
        if (!rows.length) {
            if (summary) {
                summary.hidden = false;
                summary.className = 'alert alert-warning py-2 small';
                summary.textContent = noneMsg;
            }
            return;
        }

        if (!csrf) {
            if (summary) {
                summary.hidden = false;
                summary.className = 'alert alert-danger py-2 small';
                summary.textContent = t('csrf_missing', 'Jeton CSRF manquant. Rechargez la page.');
            }
            return;
        }

        const total = rows.length;
        let updates = 0;
        let okCount = 0;
        let errors = 0;

        showLoading(t('checking_progress', 'Vérification des mises à jour (:current/:total)…')
            .replace(':current', '0')
            .replace(':total', String(total)));

        try {
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const id = row.dataset[idField];
                if (!id) {
                    errors++;
                    continue;
                }
                const name = row.querySelector('a')?.textContent?.trim() || id;
                const progress = t('sync_progress', 'Synchro : :name (:current/:total)…')
                    .replace(':name', name)
                    .replace(':current', String(i + 1))
                    .replace(':total', String(total));
                showLoading(progress);
                setRowBusy(row, true);

                try {
                    const data = await syncOne(id, name);
                    applyRowResult(row, data);
                    const isUpdate = data.update_available === true || data.status === 'update_available';
                    if (data.ok !== false && (isUpdate || data.status === 'up_to_date')) {
                        if (isUpdate) {
                            updates++;
                        } else {
                            okCount++;
                        }
                    } else {
                        errors++;
                    }
                } catch (e) {
                    errors++;
                    applyRowResult(row, { ok: false, status: 'error', error: t('network_error', 'Erreur réseau') });
                } finally {
                    setRowBusy(row, false);
                }
            }

            if (summary) {
                summary.hidden = false;
                summary.className = 'alert py-2 small ' + (errors ? 'alert-warning' : 'alert-success');
                summary.textContent = finishedTpl
                    .replace(':updates', String(updates))
                    .replace(':ok', String(okCount))
                    .replace(':errors', String(errors))
                    .replace(':total', String(total));
            }
        } finally {
            hideLoading();
        }
    });
}

/**
 * Detail pipeline overlay: sync → optional VirusTotal → ZIP → optional VM test.
 * Used for software and drivers (start URL comes from data-pipeline-start).
 */
function initSoftwarePipeline() {
    const overlay = document.getElementById('pipeline-overlay');
    const panel = document.getElementById('pipeline-panel');
    const buttons = Array.from(document.querySelectorAll('.pipeline-run-btn'));
    if (!overlay || !panel || buttons.length === 0) {
        return;
    }

    const overall = document.getElementById('pipeline-overall');
    const spinner = document.getElementById('pipeline-spinner');
    const actions = document.getElementById('pipeline-actions');
    const closeBtn = document.getElementById('pipeline-close-btn');
    const logWrap = document.getElementById('pipeline-log-wrap');
    const logEl = document.getElementById('pipeline-log');
    const confirmBox = document.getElementById('pipeline-confirm-box');
    const confirmOkBtn = document.getElementById('pipeline-confirm-ok');
    const confirmAbortBtn = document.getElementById('pipeline-confirm-abort');
    const vtBox = document.getElementById('pipeline-vt-box');
    const vtOkBtn = document.getElementById('pipeline-vt-ok');
    const vtAbortBtn = document.getElementById('pipeline-vt-abort');
    const vtSummary = document.getElementById('pipeline-vt-summary');
    const vtHint = document.getElementById('pipeline-vt-hint');
    const vtEngines = document.getElementById('pipeline-vt-engines');
    const vtLink = document.getElementById('pipeline-vt-link');

    let pendingReloadUrl = null;
    let running = false;
    let activeJobId = null;
    let confirmBusy = false;

    const setStep = (name, state, message) => {
        const li = panel.querySelector('[data-step="' + name + '"]');
        if (!li) {
            return;
        }
        const badge = li.querySelector('.step-badge');
        const msg = li.querySelector('.step-msg');
        const map = {
            pending: ['text-bg-secondary', t('pending', '—')],
            running: ['text-bg-warning text-dark', t('running', 'En cours')],
            awaiting: ['text-bg-info text-dark', t('awaiting', 'À valider')],
            ok: ['text-bg-success', t('ok', 'OK')],
            fail: ['text-bg-danger', t('fail', 'Échec')],
            skip: ['text-bg-secondary', t('skip', 'Ignoré')],
        };
        const [cls, label] = map[state] || map.pending;
        if (badge) {
            badge.className = 'badge step-badge ' + cls;
            badge.textContent = label;
        }
        if (msg) {
            msg.textContent = message || '';
            msg.className = 'small step-msg ' + (state === 'fail' ? 'text-danger' : 'text-muted');
        }
    };

    const showConfirmBox = (show) => {
        if (!confirmBox) {
            return;
        }
        confirmBox.hidden = !show;
        if (show && spinner) {
            spinner.hidden = true;
        }
        if (show && vtBox) {
            vtBox.hidden = true;
        }
    };

    const showVtBox = (show, review) => {
        if (!vtBox) {
            return;
        }
        vtBox.hidden = !show;
        if (show && spinner) {
            spinner.hidden = true;
        }
        if (show && confirmBox) {
            confirmBox.hidden = true;
        }
        if (!show || !review) {
            return;
        }
        if (vtSummary) {
            const split = t('vt_split', 'URL : :url malveillant — fichier : :file malveillant.')
                .replace(':url', String(review.url_malicious ?? 0))
                .replace(':file', String(review.file_malicious ?? 0));
            vtSummary.textContent = (review.summary || '') + (review.summary ? ' — ' : '') + split;
        }
        if (vtHint) {
            const hint = review.hint === 'false_positive'
                ? t('vt_hint_false_positive', 'Lecture probable : faux positif.')
                : (review.hint === 'malware'
                    ? t('vt_hint_malware', 'Lecture probable : menace réelle.')
                    : t('vt_hint_unclear', 'Lecture incertaine : ouvrez le rapport.'));
            vtHint.textContent = hint;
        }
        if (vtEngines) {
            const list = Array.isArray(review.engines)
                ? review.engines.map((row) => {
                    const name = row && row.engine ? String(row.engine) : '';
                    const result = row && row.result ? String(row.result) : '';
                    return result ? (name + ' (' + result + ')') : name;
                }).filter(Boolean).join(', ')
                : '';
            vtEngines.textContent = list
                ? t('vt_engines', 'Moteurs : :list').replace(':list', list)
                : '';
        }
        if (vtLink) {
            if (review.permalink) {
                vtLink.href = review.permalink;
                vtLink.hidden = false;
            } else {
                vtLink.hidden = true;
            }
        }
    };

    const appendLog = (text) => {
        if (!logWrap || !logEl || !text) {
            return;
        }
        logWrap.hidden = false;
        logEl.textContent = (logEl.textContent ? logEl.textContent + '\n\n' : '') + text;
    };

    const openOverlay = () => {
        overlay.hidden = false;
        document.body.classList.add('app-loading-active');
        if (actions) {
            actions.hidden = false;
        }
        if (closeBtn) {
            closeBtn.hidden = true;
            closeBtn.textContent = t('close', 'Fermer');
            closeBtn.className = 'btn btn-primary';
        }
        if (spinner) {
            spinner.hidden = false;
        }
    };

    const finishOverlay = (failed) => {
        running = false;
        if (spinner) {
            spinner.hidden = true;
        }
        if (actions) {
            actions.hidden = false;
        }
        if (closeBtn) {
            closeBtn.hidden = false;
            closeBtn.textContent = t('close', 'Fermer');
            closeBtn.className = 'btn btn-primary';
            closeBtn.focus();
        }
        if (overall) {
            overall.className = 'badge ' + (failed ? 'text-bg-danger' : 'text-bg-success');
            overall.textContent = failed ? t('finished_error', 'Terminé avec erreur') : t('finished_ok', 'Terminé avec succès');
        }
        buttons.forEach((b) => { b.disabled = false; });
    };

    const closeOverlay = () => {
        // Always allow dismissing the overlay so the UI is never trapped
        running = false;
        overlay.hidden = true;
        document.body.classList.remove('app-loading-active');
        buttons.forEach((b) => { b.disabled = false; });
        if (pendingReloadUrl) {
            const url = pendingReloadUrl;
            pendingReloadUrl = null;
            window.location.assign(url);
        }
    };

    if (closeBtn) {
        closeBtn.addEventListener('click', closeOverlay);
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !overlay.hidden) {
            closeOverlay();
        }
    });

    const sleep = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

    const postJson = async (url, formFields) => {
        const body = new FormData();
        Object.entries(formFields).forEach(([k, v]) => body.append(k, String(v)));
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });
        const raw = await res.text();
        let data = {};
        try {
            data = raw ? JSON.parse(raw) : {};
        } catch (e) {
            data = {
                ok: false,
                error: t('non_json_response', 'Réponse non-JSON (HTTP :status). :body')
                    .replace(':status', String(res.status))
                    .replace(':body', raw ? raw.replace(/<[^>]+>/g, ' ').trim().slice(0, 180) : t('empty_body', 'vide')),
            };
        }
        if (!res.ok) {
            data.ok = false;
            data.error = data.error || data.message || ('HTTP ' + res.status);
        }
        return data;
    };

    const getJson = async (url) => {
        const res = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            cache: 'no-store',
        });
        const raw = await res.text();
        let data = {};
        try {
            data = raw ? JSON.parse(raw) : {};
        } catch (e) {
            data = {
                ok: false,
                error: t('non_json_response', 'Réponse non-JSON (HTTP :status). :body')
                    .replace(':status', String(res.status))
                    .replace(':body', raw ? raw.replace(/<[^>]+>/g, ' ').trim().slice(0, 180) : t('empty_body', 'vide')),
            };
        }
        if (!res.ok) {
            data.ok = false;
            data.error = data.error || data.message || ('HTTP ' + res.status);
        }
        return data;
    };

    const applyJobSteps = (steps) => {
        if (!steps || typeof steps !== 'object') {
            return;
        }
        ['sync', 'vt', 'zip', 'vm'].forEach((name) => {
            const step = steps[name];
            if (!step) {
                return;
            }
            setStep(name, step.state || 'pending', step.message || '');
        });
    };

    const runPipeline = async (btn) => {
        if (running) {
            return;
        }

        const entityId = btn.dataset.softwareId || btn.dataset.driverId
            || overlay.dataset.softwareId || overlay.dataset.driverId;
        const csrf = btn.dataset.csrf || overlay.dataset.csrf || '';
        const vmEnabled = (btn.dataset.vmEnabled || overlay.dataset.vmEnabled) === '1';
        const vtEnabled = (btn.dataset.vtEnabled || overlay.dataset.vtEnabled) === '1';
        const startUrl = overlay.dataset.pipelineStart
            || (entityId ? ('/softwares/' + encodeURIComponent(entityId) + '/pipeline') : '');
        const reloadUrl = overlay.dataset.pipelineReload
            || (entityId ? ('/softwares/' + encodeURIComponent(entityId) + '?tab=sync') : '');

        if (!csrf) {
            window.alert(t('csrf_missing_alert', 'Jeton CSRF manquant — rechargez la page.'));
            return;
        }
        if (!startUrl) {
            window.alert(t('pipeline_start_failed', 'Démarrage pipeline impossible'));
            return;
        }

        running = true;
        pendingReloadUrl = null;
        activeJobId = null;
        confirmBusy = false;
        showConfirmBox(false);
        showVtBox(false);
        openOverlay();

        if (logEl) {
            logEl.textContent = '';
        }
        if (logWrap) {
            logWrap.hidden = true;
        }
        setStep('sync', 'pending', t('waiting', 'En attente…'));
        setStep('vt', vtEnabled ? 'pending' : 'skip', vtEnabled ? t('waiting', 'En attente…') : t('vt_disabled', 'VirusTotal désactivé'));
        setStep('zip', 'pending', t('waiting', 'En attente…'));
        setStep('vm', vmEnabled ? 'pending' : 'skip', vmEnabled ? t('waiting', 'En attente…') : t('vm_not_configured', 'VM non configurée'));
        if (overall) {
            overall.className = 'badge text-bg-warning text-dark';
            overall.textContent = t('running_background', 'En cours (arrière-plan)');
        }
        if (closeBtn) {
            // Allow dismissing even while the job runs (job continues server-side)
            closeBtn.hidden = false;
            closeBtn.textContent = t('hide', 'Masquer');
            closeBtn.className = 'btn btn-outline-secondary';
        }
        buttons.forEach((b) => { b.disabled = true; });

        let failed = false;
        let shouldReload = false;
        let lastLog = '';

        try {
            const start = await postJson(startUrl, {
                _csrf: csrf,
                vm: vmEnabled ? '1' : '0',
                vt: vtEnabled ? '1' : '0',
            });
            if (!start.ok || !start.job_id) {
                failed = true;
                setStep('sync', 'fail', start.error || t('pipeline_start_failed', 'Démarrage pipeline impossible'));
                if (vtEnabled) {
                    setStep('vt', 'skip', t('cancelled', 'Annulé'));
                }
                setStep('zip', 'skip', t('cancelled', 'Annulé'));
                if (vmEnabled) {
                    setStep('vm', 'skip', t('cancelled', 'Annulé'));
                }
                appendLog(start.error || JSON.stringify(start));
                finishOverlay(true);
                return;
            }

            activeJobId = start.job_id;
            const statusUrl = start.status_url
                || (startUrl.replace(/\/$/, '') + '/' + encodeURIComponent(start.job_id));
            const confirmUrl = statusUrl + '/vm-confirm';
            const vtConfirmUrl = statusUrl + '/vt-confirm';

            const sendVmConfirm = async (action) => {
                if (confirmBusy || !activeJobId) {
                    return;
                }
                confirmBusy = true;
                if (confirmOkBtn) confirmOkBtn.disabled = true;
                if (confirmAbortBtn) confirmAbortBtn.disabled = true;
                showConfirmBox(false);
                if (spinner) spinner.hidden = false;
                if (overall) {
                    overall.className = 'badge text-bg-warning text-dark';
                    overall.textContent = action === 'abort' ? t('cancelling', 'Annulation…') : t('uninstalling', 'Uninstall…');
                }
                try {
                    const res = await postJson(confirmUrl, { _csrf: csrf, action: action });
                    if (!res.ok) {
                        appendLog(res.error || t('vm_confirm_failed', 'Confirmation VM impossible'));
                        failed = true;
                        finishOverlay(true);
                    }
                } catch (err) {
                    appendLog(String(err && err.message ? err.message : err));
                    failed = true;
                    finishOverlay(true);
                } finally {
                    confirmBusy = false;
                    if (confirmOkBtn) confirmOkBtn.disabled = false;
                    if (confirmAbortBtn) confirmAbortBtn.disabled = false;
                }
            };

            if (confirmOkBtn) {
                confirmOkBtn.onclick = () => { void sendVmConfirm('continue'); };
            }
            if (confirmAbortBtn) {
                confirmAbortBtn.onclick = () => { void sendVmConfirm('abort'); };
            }

            const sendVtConfirm = async (action) => {
                if (confirmBusy || !activeJobId) {
                    return;
                }
                confirmBusy = true;
                if (vtOkBtn) vtOkBtn.disabled = true;
                if (vtAbortBtn) vtAbortBtn.disabled = true;
                showVtBox(false);
                if (spinner) spinner.hidden = false;
                if (overall) {
                    overall.className = 'badge text-bg-warning text-dark';
                    overall.textContent = action === 'abort'
                        ? t('cancelling', 'Annulation…')
                        : t('running_background', 'En cours (arrière-plan)');
                }
                try {
                    const res = await postJson(vtConfirmUrl, { _csrf: csrf, action: action });
                    if (!res.ok) {
                        appendLog(res.error || t('vt_confirm_failed', 'Décision VirusTotal impossible'));
                        failed = true;
                        finishOverlay(true);
                    }
                } catch (err) {
                    appendLog(String(err && err.message ? err.message : err));
                    failed = true;
                    finishOverlay(true);
                } finally {
                    confirmBusy = false;
                    if (vtOkBtn) vtOkBtn.disabled = false;
                    if (vtAbortBtn) vtAbortBtn.disabled = false;
                }
            };

            if (vtOkBtn) {
                vtOkBtn.onclick = () => { void sendVtConfirm('continue'); };
            }
            if (vtAbortBtn) {
                vtAbortBtn.onclick = () => { void sendVtConfirm('abort'); };
            }

            const startedAt = Date.now();
            const maxMs = 60 * 60 * 1000; // 1h idle (hors pause confirmation)
            let completed = false;
            let idleAnchor = startedAt;
            while (Date.now() - idleAnchor < maxMs) {
                await sleep(1000);
                const status = await getJson(statusUrl);
                if (!status.ok) {
                    failed = true;
                    appendLog(status.error || t('pipeline_status_unavailable', 'Statut pipeline indisponible'));
                    break;
                }

                applyJobSteps(status.steps);

                if (status.log && status.log !== lastLog) {
                    if (logEl) {
                        logEl.textContent = status.log;
                        logEl.scrollTop = logEl.scrollHeight;
                    }
                    if (logWrap) {
                        logWrap.hidden = false;
                        if (!logWrap.open) {
                            logWrap.open = true;
                        }
                    }
                    lastLog = status.log;
                }

                if (status.export_id) {
                    shouldReload = true;
                }

                if (status.awaiting_user) {
                    idleAnchor = Date.now();
                    if (status.awaiting_kind === 'vt') {
                        showConfirmBox(false);
                        showVtBox(true, status.vt_review || {});
                        if (overall) {
                            overall.className = 'badge text-bg-info text-dark';
                            overall.textContent = t('awaiting_vt', 'À valider (VirusTotal)');
                        }
                    } else {
                        showVtBox(false);
                        showConfirmBox(true);
                        if (overall) {
                            overall.className = 'badge text-bg-info text-dark';
                            overall.textContent = t('awaiting_vm', 'À valider sur la VM');
                        }
                    }
                    continue;
                }

                showConfirmBox(false);
                showVtBox(false);

                if (status.done) {
                    failed = !!status.failed;
                    shouldReload = shouldReload || !failed || !!status.export_id;
                    completed = true;
                    break;
                }

                if (spinner) spinner.hidden = false;
                if (overall) {
                    overall.className = 'badge text-bg-warning text-dark';
                    overall.textContent = t('running_status', 'En cours — :status').replace(':status', status.status || 'running');
                }
            }

            if (!completed && !failed) {
                failed = true;
                appendLog(t('pipeline_timeout', 'Timeout d’attente du worker pipeline (UI). Vérifiez storage/jobs/.'));
            }
        } catch (e) {
            failed = true;
            appendLog(String(e && e.message ? e.message : e));
        }

        showConfirmBox(false);
        showVtBox(false);
        finishOverlay(failed);
        if (shouldReload) {
            pendingReloadUrl = reloadUrl || null;
        }
        activeJobId = null;
    };

    buttons.forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            runPipeline(btn);
        });
    });
}

/**
 * Footer changelog player — lit /api/changelog et rend le Markdown.
 */
function initChangelogReader() {
    const modal = document.getElementById('changelog-modal');
    const nav = document.getElementById('changelog-nav');
    const reader = document.getElementById('changelog-reader');
    if (!modal || !nav || !reader) {
        return;
    }

    let loaded = false;
    let releases = [];
    let appVersion = '';

    const renderMarkdown = (md) => {
        if (typeof marked !== 'undefined' && marked.parse) {
            return marked.parse(md, { breaks: true });
        }
        return '<pre class="mb-0">' + escapeHtml(md) + '</pre>';
    };

    const showRelease = (id) => {
        const release = releases.find((r) => r.id === id) || releases[0];
        if (!release) {
            return;
        }
        nav.querySelectorAll('.changelog-nav-item').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.id === release.id);
        });
        reader.innerHTML = '<article class="changelog-md">' + renderMarkdown(release.markdown) + '</article>';
    };

    const buildNav = () => {
        if (!releases.length) {
            nav.innerHTML = '<div class="text-muted small p-3">' + escapeHtml(t('changelog_empty', 'Aucune version dans CHANGELOG.md.')) + '</div>';
            reader.innerHTML = '';
            return;
        }
        nav.innerHTML = releases.map((r) => (
            '<button type="button" class="changelog-nav-item" data-id="' + escapeHtml(r.id) + '">'
            + '<span class="ver">' + escapeHtml(r.version) + '</span>'
            + (r.date ? '<span class="date">' + escapeHtml(r.date) + '</span>' : '')
            + '</button>'
        )).join('');

        nav.querySelectorAll('.changelog-nav-item').forEach((btn) => {
            btn.addEventListener('click', () => showRelease(btn.dataset.id));
        });

        const preferred = releases.find((r) => r.version === appVersion)
            || releases.find((r) => r.version !== 'Unreleased')
            || releases[0];
        showRelease(preferred.id);
    };

    modal.addEventListener('show.bs.modal', async () => {
        if (loaded) {
            return;
        }
        nav.innerHTML = '<div class="changelog-nav-loading text-muted small p-3">' + escapeHtml(t('changelog_loading', 'Chargement…')) + '</div>';
        try {
            const res = await fetch('/api/changelog', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await res.json();
            if (!payload.ok) {
                throw new Error(t('changelog_unavailable', 'Changelog indisponible'));
            }
            appVersion = payload.data.version || '';
            releases = payload.data.releases || [];
            loaded = true;
            buildNav();
        } catch (e) {
            nav.innerHTML = '<div class="text-danger small p-3">' + escapeHtml(t('changelog_load_error', 'Impossible de charger le changelog.')) + '</div>';
            reader.innerHTML = '';
        }
    });
}

/**
 * Light / dark theme toggle (persisted in localStorage).
 */
function initThemeToggle() {
    const root = document.documentElement;
    const button = document.getElementById('theme-toggle');

    const apply = (theme) => {
        root.setAttribute('data-bs-theme', theme);
        try {
            localStorage.setItem('pf-theme', theme);
        } catch (e) {}
        if (button) {
            button.setAttribute('aria-label', theme === 'dark' ? t('theme_to_light', 'Passer en mode clair') : t('theme_to_dark', 'Passer en mode sombre'));
            button.title = theme === 'dark' ? t('theme_mode_light', 'Mode clair') : t('theme_mode_dark', 'Mode sombre');
        }
    };

    const current = root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    apply(current);

    if (!button) {
        return;
    }

    button.addEventListener('click', () => {
        const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        apply(next);
    });
}

/**
 * Global loading overlay for long-running actions (sync, ZIP, forms).
 */
function initLoadingOverlay() {
    const overlay = document.getElementById('app-loading');
    const textEl = document.getElementById('app-loading-text');
    if (!overlay || !textEl) {
        return;
    }

    let hideTimer = null;

    const show = (message) => {
        if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = null;
        }
        textEl.textContent = message || t('processing', 'Traitement en cours…');
        overlay.hidden = false;
        overlay.setAttribute('aria-busy', 'true');
        document.body.classList.add('app-loading-active');
    };

    const hide = () => {
        overlay.hidden = true;
        overlay.setAttribute('aria-busy', 'false');
        document.body.classList.remove('app-loading-active');
    };

    window.AppLoading = { show, hide };

    /**
     * Synchro tab URL for a software, derived from an export link or current page.
     */
    function softwareSyncUrl(fromHref) {
        const source = fromHref || window.location.pathname;
        const match = source.match(/\/softwares\/(\d+)/);
        if (match) {
            return '/softwares/' + match[1] + '?tab=sync';
        }
        return window.location.pathname + (window.location.search || '');
    }

    // Forms marked with data-loading
    document.querySelectorAll('form[data-loading]').forEach((form) => {
        form.addEventListener('submit', () => {
            const msg = form.getAttribute('data-loading') || t('processing', 'Traitement en cours…');
            show(msg);
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
                btn.disabled = true;
            });
        });
    });

    // Links / buttons marked with data-loading
    document.querySelectorAll('a[data-loading], button[data-loading]').forEach((el) => {
        el.addEventListener('click', async (e) => {
            if (el.hasAttribute('disabled') || el.classList.contains('disabled')) {
                return;
            }

            const msg = el.getAttribute('data-loading') || t('processing', 'Traitement en cours…');
            const href = el.getAttribute('href') || '';
            const isDownload = el.hasAttribute('data-loading-download')
                || href.includes('export-zip');

            if (isDownload && el.tagName === 'A' && href) {
                e.preventDefault();
                show(msg);
                const isGeneration = href.includes('export-zip');
                let downloaded = false;
                try {
                    const res = await fetch(href, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) {
                        window.location.href = isGeneration
                            ? softwareSyncUrl(href)
                            : href;
                        return;
                    }
                    const type = res.headers.get('Content-Type') || '';
                    if (!type.includes('zip') && !type.includes('octet-stream')) {
                        // Server redirected to an HTML flash page (error)
                        window.location.href = isGeneration
                            ? softwareSyncUrl(href)
                            : href;
                        return;
                    }
                    const blob = await res.blob();
                    const disposition = res.headers.get('Content-Disposition') || '';
                    let filename = t('default_zip_name', 'package.zip');
                    const match = disposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                    if (match) {
                        filename = decodeURIComponent(match[1].replace(/"/g, '').trim());
                    }
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                    downloaded = true;
                } catch (err) {
                    console.error(err);
                    window.location.href = isGeneration ? softwareSyncUrl(href) : href;
                    return;
                } finally {
                    hide();
                }

                // After generating a new ZIP, refresh Synchro tab to show history/status
                if (downloaded && isGeneration) {
                    window.location.href = softwareSyncUrl(href);
                }
                return;
            }

            show(msg);
        });
    });

    // Hide overlay if user comes back via bfcache
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            hide();
        }
    });
}

/**
 * Dynamically render form fields when selecting a procedure step type.
 */
function initStepTypeFields() {
    const typeSelect = document.getElementById('step_type');
    const fieldsContainer = document.getElementById('step-fields');
    if (!typeSelect || !fieldsContainer) {
        return;
    }

    const render = async () => {
        const type = typeSelect.value;
        if (!type) {
            fieldsContainer.innerHTML = '';
            return;
        }

        fieldsContainer.innerHTML = '<div class="d-flex align-items-center gap-2 text-muted small py-2"><div class="spinner-border spinner-border-sm" role="status"></div><span>' + escapeHtml(t('step_form_loading', 'Chargement du formulaire…')) + '</span></div>';
        try {
            const res = await fetch('/api/step-schema/' + encodeURIComponent(type), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            if (!data.ok) {
                fieldsContainer.innerHTML = '<p class="text-danger small">' + escapeHtml(t('step_schema_unavailable', 'Schéma indisponible.')) + '</p>';
                return;
            }
            fieldsContainer.innerHTML = data.fields.map(fieldHtml).join('');
        } catch (e) {
            fieldsContainer.innerHTML = '<p class="text-danger small">' + escapeHtml(t('step_load_error', 'Erreur de chargement.')) + '</p>';
        }
    };

    typeSelect.addEventListener('change', render);
    if (typeSelect.value) {
        render();
    }
}

function fieldHtml(field) {
    const id = 'field_' + field.name;
    const required = field.required ? 'required' : '';
    const label = `<label class="form-label" for="${id}">${escapeHtml(field.label)}${field.required ? ' *' : ''}</label>`;

    if (field.type === 'textarea') {
        return `<div class="mb-3">${label}<textarea class="form-control font-monospace" id="${id}" name="${field.name}" rows="4" ${required}></textarea></div>`;
    }
    if (field.type === 'checkbox') {
        return `<div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="${id}" name="${field.name}" value="1"><label class="form-check-label" for="${id}">${escapeHtml(field.label)}</label></div>`;
    }
    if (field.type === 'select') {
        const opts = (field.options || []).map(o => `<option value="${escapeHtml(o)}">${escapeHtml(o)}</option>`).join('');
        return `<div class="mb-3">${label}<select class="form-select" id="${id}" name="${field.name}" ${required}><option value="">—</option>${opts}</select></div>`;
    }
    const inputType = field.type === 'number' ? 'number' : 'text';
    return `<div class="mb-3">${label}<input class="form-control" type="${inputType}" id="${id}" name="${field.name}" ${required}></div>`;
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Drag-and-drop reorder for procedure steps.
 */
function initStepReorder() {
    const list = document.getElementById('procedure-steps');
    if (!list) {
        return;
    }

    let dragEl = null;

    list.querySelectorAll('.step-item').forEach((item) => {
        item.setAttribute('draggable', 'true');

        item.addEventListener('dragstart', (e) => {
            dragEl = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            dragEl = null;
            persistOrder(list);
        });

        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            const after = getDragAfterElement(list, e.clientY);
            if (after == null) {
                list.appendChild(dragEl);
            } else {
                list.insertBefore(dragEl, after);
            }
        });
    });
}

function getDragAfterElement(container, y) {
    const elements = [...container.querySelectorAll('.step-item:not(.dragging)')];
    return elements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset, element: child };
        }
        return closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
}

async function persistOrder(list) {
    const softwareId = list.dataset.softwareId;
    const csrf = list.dataset.csrf;
    const order = [...list.querySelectorAll('.step-item')].map((el) => el.dataset.stepId);

    const body = new FormData();
    body.append('_csrf', csrf);
    body.append('order', JSON.stringify(order));

    try {
        await fetch('/softwares/' + softwareId + '/procedure/reorder', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });
    } catch (e) {
        console.error('Reorder failed', e);
    }
}

/**
 * Guided procedure wizard (empty procedure or rerun).
 */
function initProcedureWizard() {
    const root = document.getElementById('procedure-wizard');
    if (!root) {
        return;
    }

    const openBtns = [
        document.getElementById('pw-open-btn'),
        document.getElementById('pw-open-btn-empty'),
    ].filter(Boolean);
    const panes = [...root.querySelectorAll('.pw-pane')];
    const progressItems = [...root.querySelectorAll('#pw-progress [data-pw-step]')];
    const errBox = document.getElementById('pw-error');
    const btnPrev = document.getElementById('pw-prev');
    const btnNext = document.getElementById('pw-next');
    const btnFinish = document.getElementById('pw-finish');
    const btnCancel = document.getElementById('pw-cancel');
    const btnCloseX = document.getElementById('pw-close-x');
    const installerTypeEl = document.getElementById('pw_installer_type');
    const total = panes.length;
    let index = 0;

    const isMsiType = (type) => type === 'msi' || type === 'msix';
    let probedOnce = root.dataset.hasInstaller === '1';
    let probing = false;
    const afterProbe = document.getElementById('pw-after-probe');

    const showAfterProbe = (visible) => {
        if (afterProbe) {
            afterProbe.hidden = !visible;
        }
    };
    showAfterProbe(probedOnce);

    const val = (id) => {
        const el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    };

    const setVal = (id, value) => {
        const el = document.getElementById(id);
        if (el && value != null) {
            el.value = String(value);
        }
    };

    const sourceUrl = () => val('pw_detection_method') === 'github'
        ? (val('pw_version_check_url') || val('pw_download_url'))
        : (val('pw_download_url') || val('pw_version_check_url'));

    const commandPresets = (type, installerName) => {
        const msi = isMsiType(type);
        const file = installerName || (msi ? 'setup.msi' : 'setup.exe');
        if (msi) {
            return {
                install_command: file,
                install_silent_args: '/qn /norestart',
                install_working_dir: '.',
                install_timeout_sec: '600',
                uninstall_command: 'msiexec.exe',
                uninstall_silent_args: '/x {PRODUCT_CODE} /qn /norestart',
                uninstall_timeout_sec: '600',
            };
        }
        let silent = '/S';
        let uninstallCmd = 'unins000.exe';
        let uninstallSilent = '/VERYSILENT /NORESTART';
        if (type === 'inno') {
            silent = '/VERYSILENT /NORESTART /SUPPRESSMSGBOXES /SP-';
        } else if (type === 'nsis') {
            silent = '/S';
            uninstallCmd = 'uninstall.exe';
            uninstallSilent = '/S';
        }
        return {
            install_command: file,
            install_silent_args: silent,
            install_working_dir: '.',
            install_timeout_sec: '900',
            uninstall_command: uninstallCmd,
            uninstall_silent_args: uninstallSilent,
            uninstall_timeout_sec: '600',
        };
    };

    const applyCommandPresets = (type, installerName) => {
        const presets = commandPresets(type || 'exe', installerName || '');
        setVal('pw_install_command', presets.install_command);
        setVal('pw_install_silent', presets.install_silent_args);
        setVal('pw_install_workdir', presets.install_working_dir);
        setVal('pw_install_timeout', presets.install_timeout_sec);
        setVal('pw_uninstall_command', presets.uninstall_command);
        setVal('pw_uninstall_silent', presets.uninstall_silent_args);
        setVal('pw_uninstall_timeout', presets.uninstall_timeout_sec);
        const fileEl = document.getElementById('pw-strategy-file');
        if (fileEl && installerName) {
            fileEl.textContent = installerName;
        }
        const cmd = document.getElementById('pw_install_command');
        if (cmd) {
            cmd.placeholder = isMsiType(type) ? 'app-setup.msi' : 'setup.exe';
        }
    };

    const syncStrategyUI = () => {
        const type = val('pw_installer_type') || 'exe';
        const msi = isMsiType(type);
        const label = document.getElementById('pw-strategy-label');
        const help = document.getElementById('pw-strategy-help');
        const typeCode = document.getElementById('pw-strategy-type');
        if (label) {
            label.textContent = msi ? (root.dataset.strategyMsi || 'MSI') : (root.dataset.strategyExe || 'EXE');
        }
        if (help) {
            help.textContent = msi
                ? (root.dataset.strategyMsiHelp || '')
                : (root.dataset.strategyExeHelp || '');
        }
        if (typeCode) {
            typeCode.textContent = type.toUpperCase();
        }
        root.querySelectorAll('.pw-msi-only').forEach((el) => {
            el.hidden = !msi;
        });
    };

    const applySuggestions = (s) => {
        if (!s || typeof s !== 'object') return;
        if (s.installer_type) {
            setVal('pw_installer_type', s.installer_type);
        }
        if (s.architecture) {
            setVal('pw_architecture', s.architecture);
        }
        if (s.detection_method) {
            setVal('pw_detection_method', s.detection_method);
        }
        if (s.download_url != null) {
            setVal('pw_download_url', s.download_url);
        }
        if (s.version_check_url != null) {
            setVal('pw_version_check_url', s.version_check_url);
        }
        if (s.procedure_name) {
            setVal('pw_procedure_name', s.procedure_name);
        }
        const fileName = s.installer_name || s.install_command || '';
        applyCommandPresets(s.installer_type || val('pw_installer_type'), fileName);
        if (s.install_command) setVal('pw_install_command', s.install_command);
        if (s.install_silent_args != null) setVal('pw_install_silent', s.install_silent_args);
        if (s.uninstall_command) setVal('pw_uninstall_command', s.uninstall_command);
        if (s.uninstall_silent_args != null) setVal('pw_uninstall_silent', s.uninstall_silent_args);
        if (s.product_code != null) setVal('pw_product_code', s.product_code);
        if (s.success_paths != null) setVal('pw_success_paths', s.success_paths);
        if (s.witness_path) {
            const w = document.getElementById('pw_witness');
            if (w) w.textContent = s.witness_path;
        }
        syncStrategyUI();
        document.getElementById('pw_detection_method')?.dispatchEvent(new Event('change'));
    };

    const showError = (msg) => {
        if (!errBox) return;
        if (!msg) {
            errBox.hidden = true;
            errBox.textContent = '';
            return;
        }
        errBox.hidden = false;
        errBox.textContent = msg;
    };

    const probeInstaller = async ({ silent } = {}) => {
        const softwareId = root.dataset.softwareId;
        const csrf = root.dataset.csrf;
        const btnProbe = document.getElementById('pw-probe-btn');
        const statusEl = document.getElementById('pw-probe-status');
        const okEl = document.getElementById('pw-probe-ok');

        if (!softwareId || !csrf) {
            showError(t('csrf_missing', 'Jeton CSRF manquant. Rechargez la page.'));
            return false;
        }
        if (!val('pw_download_url') && !val('pw_version_check_url')) {
            if (!silent) {
                showError(t('wizard_probe_need_url', 'Indiquez une URL de téléchargement ou une URL GitHub Releases.'));
            }
            return false;
        }
        if (probing) return false;
        probing = true;

        if (okEl) {
            okEl.hidden = true;
            okEl.textContent = '';
        }
        showError('');
        const oldLabel = btnProbe ? btnProbe.textContent : '';
        if (btnProbe) {
            btnProbe.disabled = true;
            btnProbe.textContent = t('wizard_probe_running', 'Téléchargement en cours…');
        }
        if (statusEl) {
            statusEl.textContent = t('wizard_probe_running', 'Téléchargement en cours…');
        }
        if (btnNext) btnNext.disabled = true;

        const body = new FormData();
        body.append('_csrf', csrf);
        body.append('installer_type', val('pw_installer_type'));
        body.append('architecture', val('pw_architecture'));
        body.append('detection_method', val('pw_detection_method'));
        body.append('download_url', val('pw_download_url'));
        body.append('version_check_url', val('pw_version_check_url'));

        try {
            const res = await fetch('/softwares/' + encodeURIComponent(softwareId) + '/procedure/wizard/probe', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                const msg = (data.errors && data.errors.join(' ')) || data.message || t('wizard_probe_failed', 'Récupération impossible');
                showError(msg);
                if (statusEl) statusEl.textContent = t('wizard_probe_failed', 'Récupération impossible');
                return false;
            }
            probedOnce = true;
            applySuggestions(data.suggestions || {});
            showAfterProbe(true);
            if (okEl) {
                okEl.hidden = false;
                okEl.textContent = data.message || t('wizard_probe_ok', 'Installateur récupéré.');
            }
            if (statusEl) {
                statusEl.textContent = '';
            }
            return true;
        } catch (e) {
            showError(t('network_error', 'Erreur réseau'));
            if (statusEl) statusEl.textContent = t('network_error', 'Erreur réseau');
            return false;
        } finally {
            probing = false;
            if (btnProbe) {
                btnProbe.disabled = false;
                btnProbe.textContent = oldLabel;
            }
            if (btnNext) btnNext.disabled = false;
        }
    };

    const go = (i) => {
        index = Math.max(0, Math.min(total - 1, i));
        panes.forEach((p) => {
            p.hidden = Number(p.dataset.pane) !== index;
        });
        progressItems.forEach((li) => {
            const step = Number(li.dataset.pwStep);
            li.classList.toggle('active', step === index);
            li.classList.toggle('done', step < index);
        });
        if (btnPrev) btnPrev.hidden = index === 0;
        if (btnNext) btnNext.hidden = index === total - 1;
        if (btnFinish) btnFinish.hidden = index !== total - 1;
        showError('');
        if (index === total - 1) {
            fillReview();
        }
        if (index === 1) {
            syncStrategyUI();
        }
    };

    const fillReview = () => {
        const set = (key, text) => {
            const el = root.querySelector('[data-review="' + key + '"]');
            if (el) el.textContent = text || '—';
        };
        const pkgParts = [
            val('pw_installer_type').toUpperCase(),
            val('pw_architecture'),
            val('pw_detection_method'),
        ];
        const reviewUrl = sourceUrl();
        if (reviewUrl) {
            pkgParts.push(reviewUrl);
        }
        set('packaging', pkgParts.join(' · '));
        set('procedure_name', val('pw_procedure_name'));
        const silent = val('pw_install_silent');
        set('install', val('pw_install_command') + (silent ? ' ' + silent : ''));
        const uSilent = val('pw_uninstall_silent');
        const pc = val('pw_product_code');
        set(
            'uninstall',
            val('pw_uninstall_command')
                + (uSilent ? ' ' + uSilent : '')
                + (pc ? ' · ' + pc : '')
        );
        const detect = [val('pw_success_paths'), val('pw_success_gone'), val('pw_pre_kill')]
            .filter(Boolean)
            .join(' | ');
        set('detect', detect || '—');
    };

    const validate = () => {
        if (index === 0) {
            if (!sourceUrl() && !probedOnce) {
                showError(t('wizard_probe_need_url', 'Indiquez une URL de téléchargement ou une URL GitHub Releases.'));
                const focusId = val('pw_detection_method') === 'github' ? 'pw_version_check_url' : 'pw_download_url';
                document.getElementById(focusId)?.focus();
                return false;
            }
        }
        if (index === 2 && !val('pw_install_command')) {
            showError(t('wizard_need_install', 'Indiquez la commande d’installation avant de continuer.'));
            document.getElementById('pw_install_command')?.focus();
            return false;
        }
        if (index === 3 && !val('pw_uninstall_command')) {
            showError(t('wizard_need_uninstall', 'Indiquez la commande de désinstallation avant de continuer.'));
            document.getElementById('pw_uninstall_command')?.focus();
            return false;
        }
        return true;
    };

    const open = () => {
        index = 0;
        probedOnce = root.dataset.hasInstaller === '1';
        showAfterProbe(probedOnce);
        syncStrategyUI();
        go(0);
        root.hidden = false;
        document.body.style.overflow = 'hidden';
        document.getElementById('pw_detection_method')?.dispatchEvent(new Event('change'));
        if (!probedOnce && sourceUrl()) {
            probeInstaller({ silent: false });
        }
    };

    const close = () => {
        root.hidden = true;
        document.body.style.overflow = '';
        showError('');
    };

    openBtns.forEach((btn) => btn.addEventListener('click', open));
    installerTypeEl?.addEventListener('change', () => {
        const type = val('pw_installer_type') || 'exe';
        const currentCmd = val('pw_install_command');
        const keepName = currentCmd && !/\.exe$/i.test(currentCmd) && !/\.msi$/i.test(currentCmd)
            ? ''
            : currentCmd;
        const fileHint = keepName
            || (document.getElementById('pw-strategy-file')?.textContent || '').trim()
            || (isMsiType(type) ? 'setup.msi' : 'setup.exe');
        const baseName = fileHint === '—' ? '' : fileHint.replace(/\.(exe|msi|msix)$/i, '');
        let nextName = fileHint;
        if (isMsiType(type) && !/\.msi/i.test(fileHint)) {
            nextName = (baseName || 'setup') + '.msi';
        } else if (!isMsiType(type) && /\.msi/i.test(fileHint)) {
            nextName = (baseName || 'setup') + '.exe';
        }
        applyCommandPresets(type, nextName === '—' ? '' : nextName);
        syncStrategyUI();
    });
    const resetProbeIfUrlChanged = () => {
        if (probing) {
            return;
        }
        probedOnce = root.dataset.hasInstaller === '1';
        showAfterProbe(probedOnce);
        const okEl = document.getElementById('pw-probe-ok');
        if (okEl) {
            okEl.hidden = true;
        }
    };
    ['pw_download_url', 'pw_version_check_url'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', resetProbeIfUrlChanged);
    });
    document.getElementById('pw-probe-btn')?.addEventListener('click', () => {
        probeInstaller({ silent: false });
    });
    document.getElementById('pw-skip-probe')?.addEventListener('click', () => {
        probedOnce = true;
        showAfterProbe(true);
        showError('');
        const statusEl = document.getElementById('pw-probe-status');
        if (statusEl) {
            statusEl.textContent = t('wizard_skip_probe_hint', 'Suite manuelle : indiquez le type, puis continuez.');
        }
        syncStrategyUI();
    });
    btnCancel?.addEventListener('click', close);
    btnCloseX?.addEventListener('click', close);
    btnPrev?.addEventListener('click', () => go(index - 1));
    btnNext?.addEventListener('click', async () => {
        if (!validate()) return;
        if (index === 0 && !probedOnce) {
            const ok = await probeInstaller({ silent: false });
            if (!ok) {
                return;
            }
        }
        go(index + 1);
    });

    root.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });

    btnFinish?.addEventListener('click', async () => {
        if (!validate()) return;

        const replaceEl = document.getElementById('pw_replace_core');
        const replace = replaceEl
            ? (replaceEl.type === 'checkbox' ? replaceEl.checked : true)
            : true;
        if (root.dataset.hasSteps === '1' && replace) {
            if (!window.confirm(t('wizard_replace_confirm', 'Remplacer les étapes install / uninstall existantes ?'))) {
                return;
            }
        }

        const softwareId = root.dataset.softwareId;
        const csrf = root.dataset.csrf;
        if (!softwareId || !csrf) {
            showError(t('csrf_missing', 'Jeton CSRF manquant. Rechargez la page.'));
            return;
        }

        btnFinish.disabled = true;
        const oldLabel = btnFinish.textContent;
        btnFinish.textContent = t('wizard_saving', 'Création de la procédure…');

        const body = new FormData();
        body.append('_csrf', csrf);
        body.append('procedure_name', val('pw_procedure_name'));
        body.append('installer_type', val('pw_installer_type'));
        body.append('architecture', val('pw_architecture'));
        body.append('detection_method', val('pw_detection_method'));
        body.append('download_url', val('pw_download_url'));
        body.append('version_check_url', val('pw_version_check_url'));
        const iconInput = document.getElementById('pw_icon');
        if (iconInput?.files?.[0]) {
            body.append('icon', iconInput.files[0]);
        }
        body.append('install_command', val('pw_install_command'));
        body.append('install_silent_args', val('pw_install_silent'));
        body.append('install_working_dir', val('pw_install_workdir') || '.');
        body.append('install_timeout_sec', val('pw_install_timeout') || '600');
        body.append('uninstall_command', val('pw_uninstall_command'));
        body.append('uninstall_silent_args', val('pw_uninstall_silent'));
        body.append('product_code', val('pw_product_code'));
        body.append('uninstall_timeout_sec', val('pw_uninstall_timeout') || '600');
        body.append('success_paths', val('pw_success_paths'));
        body.append('success_gone_paths', val('pw_success_gone'));
        body.append('pre_kill', val('pw_pre_kill'));
        body.append('notes', val('pw_notes'));
        body.append('replace_core', replace ? '1' : '0');

        try {
            const res = await fetch('/softwares/' + encodeURIComponent(softwareId) + '/procedure/wizard', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                showError((data.errors && data.errors.join(' ')) || data.message || t('wizard_save_failed', 'Échec de l’assistant'));
                btnFinish.disabled = false;
                btnFinish.textContent = oldLabel;
                return;
            }
            window.location.href = '/softwares/' + encodeURIComponent(softwareId) + '?tab=procedure';
        } catch (e) {
            showError(t('network_error', 'Erreur réseau'));
            btnFinish.disabled = false;
            btnFinish.textContent = oldLabel;
        }
    });

    const params = new URLSearchParams(window.location.search);
    if (params.get('wizard') === '1') {
        open();
        params.delete('wizard');
        const qs = params.toString();
        window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
    }
}
