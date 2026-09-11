(function ($) {
    'use strict';

    var state = {
        sessionDir: null,
        items: [],       // aktuelle Item-Liste aus dem Manifest
        selections: {},  // id -> {checked, mode:'full'|'selective', files:[], tables:[], target_path:''}
        restoreSessionId: null,
        pollTimer: null,
    };

    $(function () {
        var $app = $('#mlbkp-restore-app');
        if (!$app.length) return;
        renderStep1($app);
    });

    // ── Step 1: Sicherung wählen ──────────────────────────────────────────

    function renderStep1($app) {
        $app.html(
            '<div class="mlbkp-restore-card">' +
            '<h2>1. Sicherung wählen</h2>' +
            '<div id="mlbkp-sessions-list">Lade Sicherungen …</div>' +
            '</div>'
        );

        $.post(mlbkpRestoreData.ajaxUrl, {
            action: 'mlbkp_restore_list_sessions',
            nonce: mlbkpRestoreData.nonce,
        }).done(function (res) {
            if (!res.success) {
                $('#mlbkp-sessions-list').html('<p class="mlb-error">❌ ' + escapeHtml(res.data.message || 'Fehler.') + '</p>');
                return;
            }
            if (!res.data.sessions.length) {
                $('#mlbkp-sessions-list').html('<p>Keine Sicherungen gefunden.</p>');
                return;
            }

            var html = '<table class="widefat"><thead><tr><th>Datum</th><th></th></tr></thead><tbody>';
            res.data.sessions.forEach(function (s) {
                html += '<tr><td>' + formatSessionName(s.name) + '</td>' +
                    '<td><button type="button" class="button mlbkp-pick-session" data-dir="' + escapeAttr(s.path) + '">Auswählen</button></td></tr>';
            });
            html += '</tbody></table>';
            $('#mlbkp-sessions-list').html(html);
        }).fail(function () {
            $('#mlbkp-sessions-list').html('<p class="mlb-error">❌ Verbindung fehlgeschlagen.</p>');
        });

        $app.off('click', '.mlbkp-pick-session').on('click', '.mlbkp-pick-session', function () {
            state.sessionDir = $(this).data('dir');
            loadSession($app);
        });
    }

    function formatSessionName(name) {
        // Ordner heißen "session-2026-09-11_11-45-03" (oder ggf. ohne Präfix) —
        // Datum/Zeit wird unabhängig von einem Präfix gesucht.
        var m = name.match(/(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})/);
        if (!m) return name;
        return m[3] + '.' + m[2] + '.' + m[1] + ' ' + m[4] + ':' + m[5];
    }

    // ── Step 2: Items wählen ──────────────────────────────────────────────

    function loadSession($app) {
        $app.html('<div class="mlbkp-restore-card"><p>Lade Inhalt der Sicherung …</p></div>');

        $.post(mlbkpRestoreData.ajaxUrl, {
            action: 'mlbkp_restore_load_session',
            nonce: mlbkpRestoreData.nonce,
            session_dir: state.sessionDir,
        }).done(function (res) {
            if (!res.success) {
                $app.html('<p class="mlb-error">❌ ' + escapeHtml(res.data.message || 'Fehler.') + '</p>');
                return;
            }
            state.items = res.data.manifest.items;
            state.selections = {};
            state.items.forEach(function (it) {
                state.selections[it.id] = {
                    checked: false,
                    mode: 'full',
                    files: [],
                    tables: [],
                    target_path: it.target_path_guess || '',
                };
            });
            renderStep2($app);
        }).fail(function () {
            $app.html('<p class="mlb-error">❌ Verbindung fehlgeschlagen.</p>');
        });
    }

    function renderStep2($app) {
        var html = '<div class="mlbkp-restore-card"><h2>2. Was wiederherstellen?</h2>';
        html += '<table class="widefat mlbkp-restore-items"><thead><tr>' +
            '<th></th><th>Element</th><th>Zielpfad</th><th>Auswahl</th></tr></thead><tbody>';

        state.items.forEach(function (it) {
            var isDb = it.type === 'database';
            html += '<tr data-item-id="' + it.id + '">';
            html += '<td><input type="checkbox" class="mlbkp-item-check" data-id="' + it.id + '"></td>';
            html += '<td>' + escapeHtml(it.label) + (it.size ? ' <span class="description">(' + formatBytes(it.size) + ')</span>' : '') + '</td>';

            if (isDb) {
                html += '<td>—</td>';
            } else {
                var reliable = it.target_path_reliable;
                html += '<td><input type="text" class="mlbkp-target-path" data-id="' + it.id + '" value="' +
                    escapeAttr(it.target_path_guess) + '" style="width:100%;' + (reliable ? '' : 'border-color:#d63638;') + '">' +
                    (reliable ? '' : '<p class="description" style="color:#d63638;">⚠ Kein Manifest — Pfad geschätzt, bitte prüfen!</p>') + '</td>';
            }

            html += '<td>' +
                '<button type="button" class="button-link mlbkp-toggle-select" data-id="' + it.id + '" data-kind="' + (isDb ? 'tables' : 'files') + '">' +
                (isDb ? 'Nur bestimmte Tabellen …' : 'Nur bestimmte Dateien …') +
                '</button>' +
                '<div class="mlbkp-sub-select" id="mlbkp-sub-' + it.id + '" style="display:none;margin-top:6px;"></div>' +
                '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        html += '<p><button type="button" class="button button-primary" id="mlbkp-goto-confirm">Weiter</button> ' +
            '<button type="button" class="button" id="mlbkp-back-to-sessions">Zurück</button></p></div>';

        $app.html(html);

        $app.off('click', '#mlbkp-back-to-sessions').on('click', '#mlbkp-back-to-sessions', function () {
            renderStep1($app);
        });

        $app.off('change', '.mlbkp-item-check').on('change', '.mlbkp-item-check', function () {
            state.selections[$(this).data('id')].checked = $(this).is(':checked');
        });

        $app.off('change', '.mlbkp-target-path').on('change', '.mlbkp-target-path', function () {
            state.selections[$(this).data('id')].target_path = $(this).val();
        });

        $app.off('click', '.mlbkp-toggle-select').on('click', '.mlbkp-toggle-select', function () {
            var id = $(this).data('id');
            var kind = $(this).data('kind');
            var $box = $('#mlbkp-sub-' + id);

            if ($box.is(':visible')) { $box.hide(); return; }
            $box.show();
            if ($box.data('loaded')) return;
            $box.data('loaded', true);

            loadSubSelection(id, kind, $box);
        });

        $app.off('click', '#mlbkp-goto-confirm').on('click', '#mlbkp-goto-confirm', function () {
            var selected = state.items.filter(function (it) { return state.selections[it.id].checked; });
            if (!selected.length) { alert('Bitte mindestens ein Element auswählen.'); return; }
            renderStep3($app, selected);
        });
    }

    function loadSubSelection(id, kind, $box) {
        var item = state.items.filter(function (it) { return it.id === id; })[0];
        $box.html('Lade …');

        var action = kind === 'tables' ? 'mlbkp_restore_list_db_tables' : 'mlbkp_restore_list_chunk_files';
        var data = { action: action, nonce: mlbkpRestoreData.nonce, remote_path: item.remote_path };
        if (kind === 'files') data.is_archive = item.is_archive ? 1 : 0;

        $.post(mlbkpRestoreData.ajaxUrl, data).done(function (res) {
            if (!res.success) { $box.html('<span class="mlb-error">❌ ' + escapeHtml(res.data.message || 'Fehler.') + '</span>'); return; }

            var list = kind === 'tables' ? res.data.tables : res.data.files.map(function (f) { return f.relative; });
            if (!list.length) { $box.html('<em>Leer.</em>'); return; }

            var html = '<div class="mlbkp-sub-list">';
            list.forEach(function (name) {
                html += '<label style="display:block;"><input type="checkbox" class="mlbkp-sub-item" value="' +
                    escapeAttr(name) + '"> ' + escapeHtml(name) + '</label>';
            });
            html += '</div>';
            $box.html(html);

            $box.off('change', '.mlbkp-sub-item').on('change', '.mlbkp-sub-item', function () {
                var checked = $box.find('.mlbkp-sub-item:checked').map(function () { return $(this).val(); }).get();
                var sel = state.selections[id];
                if (kind === 'tables') { sel.tables = checked; } else { sel.files = checked; }
                sel.mode = checked.length ? 'selective' : 'full';

                // Eine Datei-/Tabellen-Auswahl zählt als Auswahl des Elements —
                // die linke "Element wiederherstellen"-Checkbox automatisch mit
                // anhaken. Bewusst NUR an-, nie automatisch abhaken: wird die
                // letzte Datei wieder abgewählt, bleibt das Element markiert
                // (fällt dann auf "vollständig" zurück) — Abwählen bleibt eine
                // bewusste, manuelle Aktion über die linke Checkbox.
                if (checked.length > 0 && !sel.checked) {
                    sel.checked = true;
                    $('.mlbkp-item-check[data-id="' + id + '"]').prop('checked', true);
                }
            });
        }).fail(function () {
            $box.html('<span class="mlb-error">❌ Verbindung fehlgeschlagen.</span>');
        });
    }

    // ── Step 3: Bestätigen & Starten ──────────────────────────────────────

    function renderStep3($app, selected) {
        var html = '<div class="mlbkp-restore-card"><h2>3. Bestätigen</h2>';
        html += '<p>⚠ Bestehende Dateien/Tabellen an den Zielorten werden überschrieben. ' +
            'Vor jeder Überschreibung wird automatisch eine Sicherheitskopie angelegt.</p><ul>';

        selected.forEach(function (it) {
            var sel = state.selections[it.id];
            var detail = sel.mode === 'selective'
                ? (it.type === 'database' ? sel.tables.length + ' Tabelle(n)' : sel.files.length + ' Datei(en)')
                : 'vollständig';
            html += '<li><strong>' + escapeHtml(it.label) + '</strong> — ' + detail + '</li>';
        });

        html += '</ul><p><button type="button" class="button button-primary" id="mlbkp-confirm-restore">⏪ Wiederherstellung starten</button> ' +
            '<button type="button" class="button" id="mlbkp-back-to-items">Zurück</button></p>' +
            '<div id="mlbkp-restore-progress"></div></div>';

        $app.html(html);

        $app.off('click', '#mlbkp-back-to-items').on('click', '#mlbkp-back-to-items', function () {
            renderStep2($app);
        });

        $app.off('click', '#mlbkp-confirm-restore').on('click', '#mlbkp-confirm-restore', function () {
            startRestore($app, selected);
        });
    }

    function startRestore($app, selected) {
        var payload = selected.map(function (it) {
            var sel = state.selections[it.id];
            return {
                type: it.type,
                label: it.label,
                remote_path: it.remote_path,
                is_archive: it.is_archive,
                restore_mode: sel.mode,
                target_path: sel.target_path,
                files: sel.files,
                tables: sel.tables,
            };
        });

        $('#mlbkp-confirm-restore').prop('disabled', true).text('⏳ Wird gestartet …');

        $.post(mlbkpRestoreData.ajaxUrl, {
            action: 'mlbkp_restore_start',
            nonce: mlbkpRestoreData.nonce,
            session_dir: state.sessionDir,
            items: JSON.stringify(payload),
        }).done(function (res) {
            if (!res.success) {
                $('#mlbkp-restore-progress').html('<p class="mlb-error">❌ ' + escapeHtml(res.data.message || 'Fehler.') + '</p>');
                $('#mlbkp-confirm-restore').prop('disabled', false).text('⏪ Wiederherstellung starten');
                return;
            }
            state.restoreSessionId = res.data.session_id;
            $('#mlbkp-confirm-restore').hide();
            $('#mlbkp-restore-progress').html(
                '<p id="mlbkp-restore-status">⏳ Wiederherstellung läuft …</p>' +
                '<div id="mlbkp-restore-items"></div>' +
                '<button type="button" class="button" id="mlbkp-cancel-restore">Abbrechen</button>'
            );
            $('#mlbkp-cancel-restore').on('click', function () { cancelRestore(); });
            pollStatus();
        }).fail(function () {
            $('#mlbkp-restore-progress').html('<p class="mlb-error">❌ Verbindung fehlgeschlagen.</p>');
            $('#mlbkp-confirm-restore').prop('disabled', false).text('⏪ Wiederherstellung starten');
        });
    }

    function pollStatus() {
        state.pollTimer = setInterval(function () {
            $.post(mlbkpRestoreData.ajaxUrl, {
                action: 'mlbkp_restore_check_status',
                nonce: mlbkpRestoreData.nonce,
                session_id: state.restoreSessionId,
            }).done(function (res) {
                if (!res.success) return;
                renderProgress(res.data);
                if (res.data.status !== 'running') {
                    clearInterval(state.pollTimer);
                    $('#mlbkp-cancel-restore').hide();
                }
            });
        }, 3000);
    }

    function renderProgress(data) {
        var icons = { pending: '⏸', running: '⏳', done: '✅', error: '❌', skipped: '⏭' };
        var html = '';
        data.items.forEach(function (it) {
            html += '<div>' + (icons[it.status] || '•') + ' ' + escapeHtml(it.label) +
                (it.error ? ' — <span class="mlb-error">' + escapeHtml(it.error) + '</span>' : '') + '</div>';
        });
        $('#mlbkp-restore-items').html(html);

        var statusText = {
            running: '⏳ Läuft … (' + data.items_done + '/' + data.items_total + ')',
            success: '✅ Wiederherstellung abgeschlossen.',
            error: '❌ Wiederherstellung mit Fehlern beendet: ' + escapeHtml(data.error_message || ''),
            cancelled: '⏹ Abgebrochen.',
        };
        $('#mlbkp-restore-status').html(statusText[data.status] || data.status);

        if (data.status !== 'running' && data.safety_dir) {
            $('#mlbkp-restore-status').append(
                '<p class="description">Sicherheitskopie überschriebener Dateien: <code>' + escapeHtml(data.safety_dir) + '</code></p>'
            );
        }
    }

    function cancelRestore() {
        if (!state.restoreSessionId) return;
        $.post(mlbkpRestoreData.ajaxUrl, {
            action: 'mlbkp_restore_cancel',
            nonce: mlbkpRestoreData.nonce,
            session_id: state.restoreSessionId,
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }
    function escapeAttr(str) {
        return escapeHtml(str).replace(/"/g, '&quot;');
    }
    function formatBytes(bytes) {
        if (!bytes) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
    }

})(jQuery);
