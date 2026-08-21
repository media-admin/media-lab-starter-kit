/* global mlbkpData, jQuery */
(function ($) {
    'use strict';

    // ── Hilfsfunktionen ───────────────────────────────────────────────────────

    function setStatus($el, message, type) {
        $el.removeClass('mlb-status-ok mlb-status-error mlb-status-info')
            .addClass('mlb-status-' + type)
            .text(message)
            .show();
    }

    function appendLog(line) {
        const $log = $('#mlb-log-output');
        $log.append($('<div class="mlb-log-line"></div>').text(line));
        $log.scrollTop($log[0].scrollHeight);
    }

    // ── Verzeichnisbaum ───────────────────────────────────────────────────────

    var treeLoaded = false;

    $('#mlb-load-tree').on('click', function () {
        if (treeLoaded) return;
        var $btn = $(this).prop('disabled', true).text('Lädt …');
        var $container = $('#mlb-tree-container');

        $.ajax({
            url:    mlbkpData.ajaxUrl,
            method: 'POST',
            data: { action: 'mlbkp_get_file_tree', nonce: mlbkpData.nonce },
            success: function (res) {
                if (!res.success) {
                    $container.html('<p class="mlb-tree-error">Fehler beim Laden.</p>');
                    return;
                }
                treeLoaded = true;
                $container.empty().append(renderTree(res.data.tree));
                syncTreeFromTextarea();
                $btn.hide();
                $('#mlb-tree-expand-all, #mlb-tree-collapse-all').show();
                updateExcludeCount();
            },
            error: function () {
                $container.html('<p class="mlb-tree-error">Verbindungsfehler.</p>');
                $btn.prop('disabled', false).text('Erneut versuchen');
            },
        });
    });

    // Baum rendern
    function renderTree(nodes) {
        if (!nodes || !nodes.length) return null;
        var $ul = $('<ul class="mlb-tree">');

        nodes.forEach(function (node) {
            var hasChildren = node.children && node.children.length > 0;
            var $li = $('<li class="mlb-tree-item">');

            // Toggle-Button
            var $toggle = $('<button type="button" class="mlb-tree-toggle">')
                .html(hasChildren ? '▶' : '<span class="mlb-tree-spacer"></span>')
                .prop('disabled', !hasChildren);

            // Checkbox
            var $cb = $('<input type="checkbox">')
                .attr('id', 'mlbtree-' + node.path.replace(/\//g, '-'))
                .val(node.path);

            // Label
            var $label = $('<label>')
                .attr('for', 'mlbtree-' + node.path.replace(/\//g, '-'))
                .append($('<span class="mlb-tree-icon">').text('📁'))
                .append($('<span class="mlb-tree-name">').text(node.name));

            $li.append($toggle).append($cb).append($label);

            // Kinder-Container
            if (hasChildren) {
                var $children = $('<div class="mlb-tree-children" style="display:none;">');
                $children.append(renderTree(node.children));
                $li.append($children);

                $toggle.on('click', function () {
                    var open = $children.is(':visible');
                    $children.slideToggle(150);
                    $toggle.html(open ? '▶' : '▼');
                    $li.toggleClass('mlb-tree-open', !open);
                });
            }

            // Checkbox → Textarea sync
            $cb.on('change', function () {
                var path = $(this).val();
                var checked = this.checked;

                // Kinder-Checkboxen mitziehen
                $li.find('input[type="checkbox"]').prop('checked', checked).each(function () {
                    syncCheckboxToTextarea($(this).val(), checked);
                });

                syncCheckboxToTextarea(path, checked);
                updateExcludeCount();
            });

            $ul.append($li);
        });

        return $ul;
    }

    // Checkbox-Zustand in Textarea spiegeln
    function syncCheckboxToTextarea(path, checked) {
        var lines = getExcludeLines();
        if (checked) {
            if (!lines.includes(path)) {
                lines.push(path);
                // Redundante Kind-Pfade entfernen
                lines = lines.filter(function (l) {
                    return l === path || !l.startsWith(path + '/');
                });
            }
        } else {
            lines = lines.filter(function (l) { return l !== path; });
        }
        setExcludeLines(lines);
    }

    // Textarea → Baum-Checkboxen synchronisieren
    function syncTreeFromTextarea() {
        var lines = getExcludeLines();
        $('#mlb-tree-container input[type="checkbox"]').each(function () {
            var path = $(this).val();
            var shouldCheck = lines.some(function (l) {
                return l === path || path.startsWith(l + '/');
            });
            $(this).prop('checked', shouldCheck);
        });
    }

    // Textarea → Baum bei manueller Eingabe aktualisieren
    $('#exclude_paths').on('input', function () {
        if (treeLoaded) syncTreeFromTextarea();
        updateExcludeCount();
    });

    // Alle aufklappen / zuklappen
    $('#mlb-tree-expand-all').on('click', function () {
        $('#mlb-tree-container .mlb-tree-children').slideDown(100);
        $('#mlb-tree-container .mlb-tree-toggle').html('▼');
        $('#mlb-tree-container .mlb-tree-item').addClass('mlb-tree-open');
    });

    $('#mlb-tree-collapse-all').on('click', function () {
        $('#mlb-tree-container .mlb-tree-children').slideUp(100);
        $('#mlb-tree-container .mlb-tree-toggle:not(:disabled)').html('▶');
        $('#mlb-tree-container .mlb-tree-item').removeClass('mlb-tree-open');
    });

    function getExcludeLines() {
        var val = $('#exclude_paths').val();
        if ( !val ) return [];
        return val
            .split('\n')
            .map(function (l) { return l.trim(); })
            .filter(function (l) { return l !== ''; });
    }

    function setExcludeLines(lines) {
        $('#exclude_paths').val(lines.join('\n'));
    }

    function updateExcludeCount() {
        var count = getExcludeLines().length;
        var $badge = $('#mlb-exclude-count');
        if (count > 0) {
            $badge.text(count + ' ausgeschlossen').show();
        } else {
            $badge.hide();
        }
    }

    updateExcludeCount();

    // ── Auth-Methode umschalten ───────────────────────────────────────────────

    function updateAuthPanel() {
        var method = $('input[name="sftp_auth_method"]:checked').val();
        $('#mlb-auth-password').toggleClass('active', method === 'password');
        $('#mlb-auth-key').toggleClass('active', method === 'key');
    }

    $(document).on('change', 'input[name="sftp_auth_method"]', updateAuthPanel);
    updateAuthPanel();

    // ── Einstellungen speichern ───────────────────────────────────────────────

    $('#mlb-settings-form').on('submit', function (e) {
        e.preventDefault();

        const $btn    = $('#mlb-save-settings').prop('disabled', true);
        const $status = $('#mlb-save-status');

        setStatus($status, mlbkpData.strings.saving, 'info');

        $.ajax({
            url:    mlbkpData.ajaxUrl,
            method: 'POST',
            data:   $(this).serialize() + '&action=mlbkp_save_settings&nonce=' + mlbkpData.nonce,
            success: function (res) {
                if (res.success) {
                    setStatus($status, res.data.message, 'ok');
                    if (res.data.next_run) {
                        // Nächsten Backup-Zeitpunkt im Status-Banner aktualisieren
                        $('.mlb-status-item:first strong').text(res.data.next_run);
                    }
                } else {
                    setStatus($status, res.data.message || 'Fehler.', 'error');
                }
            },
            error: function () {
                setStatus($status, 'Verbindungsfehler.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false);
            },
        });
    });

    // Toggle-Karten aktiv markieren
    $(document).on('change', '.mlb-toggle-card input[type="checkbox"]', function () {
        $(this).closest('.mlb-toggle-card').toggleClass('active', this.checked);
    });

    // Wochentag-Feld ein-/ausblenden
    $('#schedule').on('change', function () {
        $('#mlb-field-day').toggle($(this).val() === 'weekly');
    });

    // ── SFTP-Verbindung testen ────────────────────────────────────────────────

    $('#mlb-test-connection').on('click', function () {
        const $btn    = $(this).prop('disabled', true);
        const $status = $('#mlb-connection-status');

        setStatus($status, mlbkpData.strings.testing, 'info');

        $.ajax({
            url:    mlbkpData.ajaxUrl,
            method: 'POST',
            data: {
                action:        'mlbkp_test_connection',
                nonce:         mlbkpData.nonce,
                sftp_host:          $('#sftp_host').val(),
                sftp_port:          $('#sftp_port').val(),
                sftp_username:      $('#sftp_username').val(),
                sftp_password:      $('#sftp_password').val(),
                sftp_path:          $('#sftp_path').val(),
                sftp_site_folder:   $('#sftp_site_folder').val(),
                sftp_auth_method:   $('input[name="sftp_auth_method"]:checked').val(),
                sftp_private_key:   $('#sftp_private_key').val(),
                sftp_key_passphrase: $('#sftp_key_passphrase').val(),
            },
            success: function (res) {
                if (res.success) {
                    setStatus($status, mlbkpData.strings.conn_success, 'ok');
                } else {
                    setStatus($status, mlbkpData.strings.conn_error + (res.data.message || ''), 'error');
                }
            },
            error: function () {
                setStatus($status, 'Verbindungsfehler.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false);
            },
        });
    });

    // ── Backup starten (Session/Chunk-basiert) ────────────────────────────────
    /**
     * Pollt den Fortschritt einer laufenden Session und aktualisiert UI +
     * Live-Log. Ausgelagert, damit sowohl ein frisch gestartetes Backup als
     * auch ein beim Seitenaufruf bereits laufendes Backup (Tab-Wechsel/Reload
     * während des Laufs) dieselbe Logik nutzen.
     */
    function startStatusPolling(logId, sessionId, total, $btn, $status, $logCard, $logOut) {
        var pollInterval = setInterval(function () {
            $.ajax({
                url:    mlbkpData.ajaxUrl,
                method: 'POST',
                data: { action: 'mlbkp_check_status', nonce: mlbkpData.nonce, log_id: logId, session_id: sessionId },
                success: function (poll) {
                    if (!poll.success) return;

                    var s = poll.data.status;

                    // Chunk-Fortschritt aktualisieren
                    if (poll.data.chunks && poll.data.chunks.length) {
                        $logOut.empty();
                        var doneCount = 0;
                        var allDone   = true;
                        poll.data.chunks.forEach(function (c) {
                            var icon = { pending: '⏸', running: '⏳', done: '✅', error: '❌', skipped: '⏭' }[c.status] || '•';
                            var line = icon + ' ' + c.label + (c.size ? ' (' + c.size + ')' : '') + (c.error ? ' — ' + c.error : '');
                            appendLog(line);
                            if (c.status === 'done' || c.status === 'error' || c.status === 'skipped') doneCount++;
                            if (c.status === 'pending' || c.status === 'running') allDone = false;
                        });
                        $btn.text('⏳ ' + doneCount + '/' + total + ' Chunks …');

                        // Alle Chunks fertig aber Status noch running → explizit finalisieren
                        if (allDone && s === 'running') {
                            clearInterval(pollInterval);
                            $.ajax({
                                url:    mlbkpData.ajaxUrl,
                                method: 'POST',
                                data:   { action: 'mlbkp_finalize_session', nonce: mlbkpData.nonce, session_id: sessionId, log_id: logId },
                                success: function (fin) {
                                    $btn.prop('disabled', false).text('▶ Backup starten');
                                    $('#mlb-cancel-backup').hide();
                                    appendLog('🎉 Backup erfolgreich abgeschlossen.' + (fin.data && fin.data.file_size ? ' Gesamt: ' + fin.data.file_size : ''));
                                    setStatus($status, mlbkpData.strings.success, 'ok');
                                    $logCard.addClass('mlb-log-success');
                                },
                                error: function () {
                                    $btn.prop('disabled', false).text('▶ Backup starten');
                                    appendLog('⚠ Backup abgeschlossen aber Status-Update fehlgeschlagen. Bitte Protokoll prüfen.');
                                }
                            });
                            return;
                        }
                    }

                    if (s === 'running') return;

                    clearInterval(pollInterval);
                    $btn.prop('disabled', false).text('▶ Backup starten');
                    $('#mlb-cancel-backup').hide();

                    if (s === 'success') {
                        appendLog('🎉 Backup erfolgreich abgeschlossen.' + (poll.data.file_size ? ' Gesamt: ' + poll.data.file_size : '') + (poll.data.duration ? ' | ' + poll.data.duration : ''));
                        setStatus($status, mlbkpData.strings.success, 'ok');
                        $logCard.addClass('mlb-log-success');
                    } else if (s === 'cancelled') {
                        appendLog('🛑 Backup wurde abgebrochen.');
                        setStatus($status, '🛑 Abgebrochen.', 'info');
                    } else {
                        appendLog('❌ ' + (poll.data.error_message || 'Fehler.'));
                        setStatus($status, mlbkpData.strings.error, 'error');
                        $logCard.addClass('mlb-log-error');
                    }
                },
                error: function () {}
            });
        }, 4000);

        return pollInterval;
    }

    $('#mlb-start-backup').on('click', function () {
        var $btn      = $(this).prop('disabled', true).text('⏳ Wird gestartet …');
        var $status   = $('#mlb-run-status');
        var $logCard  = $('#mlb-log-card').show();
        var $logOut   = $('#mlb-log-output').empty();
        var backupType = $('input[name="backup_type"]:checked').val() || 'full';

        setStatus($status, mlbkpData.strings.running, 'info');
        appendLog('[' + new Date().toLocaleTimeString('de-AT') + '] Backup wird gestartet …');

        $.ajax({
            url:    mlbkpData.ajaxUrl,
            method: 'POST',
            data: { action: 'mlbkp_run_backup', nonce: mlbkpData.nonce, backup_type: backupType },
            success: function (res) {
                if (!res.success) {
                    setStatus($status, '❌ ' + (res.data.message || 'Fehler.'), 'error');
                    $btn.prop('disabled', false).text('▶ Backup starten');
                    return;
                }

                var logId     = res.data.log_id;
                var sessionId = res.data.session_id;
                var total     = res.data.chunks_total || 1;
                var labels    = res.data.chunk_labels || [];

                appendLog('[' + new Date().toLocaleTimeString('de-AT') + '] Session ' + sessionId + ' — ' + total + ' Chunk(s) geplant:');
                labels.forEach(function(l) { appendLog('   ⏸ ' + l); });

                $btn.text('⏳ ' + total + ' Chunks läuft …');
                $('#mlb-cancel-backup').data('log-id', logId).show();

                startStatusPolling(logId, sessionId, total, $btn, $status, $logCard, $logOut);
            },
            error: function () {
                setStatus($status, 'AJAX-Fehler.', 'error');
                $btn.prop('disabled', false).text('▶ Backup starten');
            },
        });
    });

    // ── Live-Log nach Tab-Wechsel/Reload wiederherstellen ─────────────────────
    // Falls beim Laden dieser Seite bereits eine Session läuft (z.B. weil der
    // Nutzer auf "Protokoll" oder "Einstellungen" gewechselt und wieder zurück
    // ist), Polling automatisch fortsetzen statt eine leere Seite mit aktivem
    // "Backup starten"-Button zu zeigen (Risiko: versehentlicher Doppelstart).
    if (mlbkpData.running && $('#mlb-start-backup').length) {
        var resumeData = mlbkpData.running;
        var $rBtn      = $('#mlb-start-backup').prop('disabled', true).text('⏳ ' + resumeData.chunksTotal + ' Chunks läuft …');
        var $rStatus   = $('#mlb-run-status');
        var $rLogCard  = $('#mlb-log-card').show();
        var $rLogOut   = $('#mlb-log-output').empty();

        setStatus($rStatus, mlbkpData.strings.running, 'info');
        appendLog('[' + new Date().toLocaleTimeString('de-AT') + '] Laufendes Backup erkannt (Session ' + resumeData.sessionId + ') — Live-Protokoll wird fortgesetzt …');
        resumeData.chunkLabels.forEach(function (l) { appendLog('   ⏸ ' + l); });

        $('#mlb-cancel-backup').data('log-id', resumeData.logId).show();

        startStatusPolling(resumeData.logId, resumeData.sessionId, resumeData.chunksTotal, $rBtn, $rStatus, $rLogCard, $rLogOut);
    }

    // ── Hängende Jobs bereinigen ──────────────────────────────────────────────

    $(document).on('click', '#mlb-cleanup-stuck', function () {
        var $btn = $(this).prop('disabled', true).text('⏳ Bereinige …');
        $.ajax({
            url:    mlbkpData.ajaxUrl,
            method: 'POST',
            data:   { action: 'mlbkp_cleanup_stuck', nonce: mlbkpData.nonce },
            success: function (res) {
                var $result = $('#mlb-cleanup-result').show();
                if (res.success) {
                    setStatus($result, '✅ ' + res.data.message, 'ok');
                    // Seite nach kurzer Verzögerung neu laden damit Logs aktualisiert werden
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    setStatus($result, '❌ Fehler.', 'error');
                }
            },
            complete: function () {
                $btn.prop('disabled', false).text('🧹 Hängende Jobs bereinigen');
            }
        });
    });

    // ── Backup abbrechen ──────────────────────────────────────────────────────

    $(document).on('click', '#mlb-cancel-backup', function () {
        var logId = $(this).data('log-id');
        var $btn  = $(this).prop('disabled', true).text('⏳ Wird abgebrochen …');

        $.ajax({
            url:    mlbkpData.ajaxUrl,
            method: 'POST',
            data: { action: 'mlbkp_cancel_backup', nonce: mlbkpData.nonce, log_id: logId },
            success: function (res) {
                if (res.success) {
                    appendLog('[' + new Date().toLocaleTimeString('de-AT') + '] 🛑 Abbruch-Signal gesendet …');
                } else {
                    $btn.prop('disabled', false).text('⏹ Abbrechen');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('⏹ Abbrechen');
            }
        });
    });

    // ── Backup-Typ-Karten ─────────────────────────────────────────────────────

    $(document).on('change', '.mlb-type-option input[type="radio"]', function () {
        $('.mlb-type-card').removeClass('active');
        $(this).closest('.mlb-type-option').find('.mlb-type-card').addClass('active');
    });

    // Initial aktive Karte markieren
    $('input[name="backup_type"]:checked').closest('.mlb-type-option').find('.mlb-type-card').addClass('active');

}(jQuery));
