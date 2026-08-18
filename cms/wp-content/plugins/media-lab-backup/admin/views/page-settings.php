<?php
defined( 'ABSPATH' ) || exit;

$s = mlbkp_get_settings();
$next_run        = MLBKP_Scheduler::get_next_run();
$suggested_folder = MLBKP_SFTP::get_suggested_folder();
?>

<form id="mlb-settings-form" class="mlb-settings-form">

    <?php /* ─── Status-Banner ─────────────────────────────────────────────── */ ?>
    <div class="mlb-status-bar">
        <div class="mlb-status-item">
            <span class="mlb-status-label">Nächstes Backup</span>
            <strong><?php echo esc_html( $next_run ?? 'Kein Zeitplan aktiv' ); ?></strong>
        </div>
        <?php $last = MLBKP_Logger::get_last_successful(); if ( $last ): ?>
        <div class="mlb-status-item">
            <span class="mlb-status-label">Letztes erfolgreiches Backup</span>
            <strong><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $last['finished_at'] ) ) ); ?></strong>
        </div>
        <?php endif; ?>
        <div class="mlb-status-item">
            <span class="mlb-status-label">phpseclib</span>
            <strong class="<?php echo class_exists( 'phpseclib3\Net\SFTP' ) ? 'mlb-ok' : 'mlb-error'; ?>">
                <?php echo class_exists( 'phpseclib3\Net\SFTP' ) ? '✅ Installiert' : '❌ Nicht installiert – composer install ausführen'; ?>
            </strong>
        </div>
    </div>

    <?php /* ─── SFTP-Verbindung ─────────────────────────────────────────── */ ?>
    <div class="mlb-card">
        <h2 class="mlb-card-title">🔌 SFTP-Verbindung (Hetzner Storage Box)</h2>
        <div class="mlb-grid-2">

            <div class="mlb-field">
                <label for="sftp_host">Hostname</label>
                <input type="text" id="sftp_host" name="sftp_host"
                       value="<?php echo esc_attr( $s['sftp_host'] ); ?>"
                       placeholder="u123456.your-storagebox.de" />
            </div>

            <div class="mlb-field">
                <label for="sftp_port">Port</label>
                <input type="number" id="sftp_port" name="sftp_port"
                       value="<?php echo esc_attr( $s['sftp_port'] ); ?>"
                       min="1" max="65535" style="width:120px;" />
                <p class="description">Standard: 22 (SFTP)</p>
            </div>

            <div class="mlb-field">
                <label for="sftp_username">Benutzername</label>
                <input type="text" id="sftp_username" name="sftp_username"
                       value="<?php echo esc_attr( $s['sftp_username'] ); ?>"
                       autocomplete="off" />
            </div>

            <div class="mlb-field">
                <label for="sftp_path">Remote-Basispfad</label>
                <input type="text" id="sftp_path" name="sftp_path"
                       value="<?php echo esc_attr( $s['sftp_path'] ); ?>"
                       placeholder="/" />
                <p class="description">Basisverzeichnis auf der Storage Box (z.B. <code>/</code> oder <code>/backups</code>).</p>
            </div>

            <div class="mlb-field mlb-span-2">
                <label for="sftp_site_folder">Website-Unterordner</label>
                <input type="text" id="sftp_site_folder" name="sftp_site_folder"
                       value="<?php echo esc_attr( $s['sftp_site_folder'] ); ?>"
                       placeholder="<?php echo esc_attr( $suggested_folder ); ?>" />
                <p class="description">
                    Unterordner innerhalb des Basispfads für diese Website.
                    Leer lassen für automatische Generierung aus der Domain
                    <strong>(Vorschlag: <code><?php echo esc_html( $suggested_folder ); ?></code>)</strong>.
                </p>
            </div>

        </div>

        <?php /* ── Authentifizierung ── */ ?>
        <div class="mlb-auth-section">
            <h3>Authentifizierung</h3>
            <div class="mlb-auth-tabs">
                <label class="mlb-auth-tab">
                    <input type="radio" name="sftp_auth_method" value="password"
                           <?php checked( $s['sftp_auth_method'] ?? 'password', 'password' ); ?> />
                    <span>🔑 Passwort</span>
                </label>
                <label class="mlb-auth-tab">
                    <input type="radio" name="sftp_auth_method" value="key"
                           <?php checked( $s['sftp_auth_method'] ?? 'password', 'key' ); ?> />
                    <span>🗝 SSH-Key</span>
                </label>
            </div>

            <div id="mlb-auth-password" class="mlb-auth-panel <?php echo ( $s['sftp_auth_method'] ?? 'password' ) !== 'key' ? 'active' : ''; ?>">
                <div class="mlb-field">
                    <label for="sftp_password">Passwort</label>
                    <input type="password" id="sftp_password" name="sftp_password"
                           value=""
                           placeholder="<?php echo ! empty( $s['sftp_password'] ) ? '(gespeichert – leer lassen zum Beibehalten)' : ''; ?>"
                           autocomplete="new-password" />
                </div>
            </div>

            <div id="mlb-auth-key" class="mlb-auth-panel <?php echo ( $s['sftp_auth_method'] ?? 'password' ) === 'key' ? 'active' : ''; ?>">
                <div class="mlb-field">
                    <label for="sftp_private_key">Private Key</label>
                    <textarea id="sftp_private_key" name="sftp_private_key" rows="8"
                              placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----"><?php echo esc_textarea( $s['sftp_private_key'] ?? '' ); ?></textarea>
                    <p class="description">
                        Inhalt der privaten Schlüsseldatei (z.B. <code>~/.ssh/id_rsa</code> oder <code>id_ed25519</code>).
                        Leer lassen um den gespeicherten Key beizubehalten.
                    </p>
                </div>
                <div class="mlb-field">
                    <label for="sftp_key_passphrase">Key-Passphrase <span style="font-weight:400;color:#646970;">(optional)</span></label>
                    <input type="password" id="sftp_key_passphrase" name="sftp_key_passphrase"
                           value=""
                           placeholder="<?php echo ! empty( $s['sftp_key_passphrase'] ) ? '(gespeichert)' : 'Leer wenn kein Passwort'; ?>"
                           autocomplete="new-password" />
                </div>
                <div class="mlb-key-info notice notice-info inline">
                    <p>
                        <strong>Public Key auf der Storage Box hinterlegen:</strong><br>
                        Den Inhalt der zugehörigen <code>.pub</code>-Datei in
                        <code>~/.ssh/authorized_keys</code> auf der Storage Box eintragen
                        (via SFTP/SSH oder Hetzner Robot).
                    </p>
                </div>
            </div>
        </div>

        <div class="mlb-connection-test-row">
            <button type="button" id="mlb-test-connection" class="button button-secondary">
                🔍 Verbindung testen
            </button>
            <span id="mlb-connection-status" class="mlb-inline-status"></span>
        </div>
    </div>

    <?php /* ─── Backup-Scope ─────────────────────────────────────────────── */ ?>
    <div class="mlb-card">
        <h2 class="mlb-card-title">📦 Was soll gesichert werden?</h2>
        <div class="mlb-scope-options">

            <label class="mlb-toggle-card <?php echo ! empty( $s['backup_database'] ) ? 'active' : ''; ?>">
                <input type="checkbox" name="backup_database" value="1"
                       <?php checked( ! empty( $s['backup_database'] ) ); ?> />
                <div class="mlb-toggle-icon">🗄</div>
                <div>
                    <strong>Datenbank</strong>
                    <span>SQL-Dump aller Tabellen (gzip-komprimiert)</span>
                </div>
            </label>

            <label class="mlb-toggle-card <?php echo ! empty( $s['backup_wpcontent'] ) ? 'active' : ''; ?>">
                <input type="checkbox" name="backup_wpcontent" value="1"
                       <?php checked( ! empty( $s['backup_wpcontent'] ) ); ?> />
                <div class="mlb-toggle-icon">📁</div>
                <div>
                    <strong>wp-content/</strong>
                    <span>Uploads, Plugins, Themes (ZIP)</span>
                    <?php $wpcontent_size = MLBKP_File_Backup::estimate_size( WP_CONTENT_DIR );
                    if ( $wpcontent_size > 0 ): ?>
                    <em>~<?php echo esc_html( MLBKP_Logger::format_bytes( $wpcontent_size ) ); ?></em>
                    <?php endif; ?>
                </div>
            </label>

            <label class="mlb-toggle-card <?php echo ! empty( $s['backup_wpcore'] ) ? 'active' : ''; ?>">
                <input type="checkbox" name="backup_wpcore" value="1"
                       <?php checked( ! empty( $s['backup_wpcore'] ) ); ?> />
                <div class="mlb-toggle-icon">⚙️</div>
                <div>
                    <strong>Vollständiges WordPress-Verzeichnis</strong>
                    <span>inkl. WordPress-Core-Dateien (große ZIP-Datei)</span>
                </div>
            </label>

        </div>

        <div class="mlb-field" style="margin-top:20px;">
            <label>Datei-Backup Methode</label>
            <div class="mlb-method-options">
                <label class="mlb-method-option">
                    <input type="radio" name="backup_file_method" value="zip"
                           <?php checked( ( $s['backup_file_method'] ?? 'zip' ), 'zip' ); ?> />
                    <div class="mlb-method-card">
                        <strong>📦 ZIP-Archiv</strong>
                        <span>Standard — erstellt ein lokales ZIP und lädt es hoch. Schnell und kompakt.</span>
                    </div>
                </label>
                <label class="mlb-method-option">
                    <input type="radio" name="backup_file_method" value="stream"
                           <?php checked( ( $s['backup_file_method'] ?? 'zip' ), 'stream' ); ?> />
                    <div class="mlb-method-card">
                        <strong>📂 Direktes SFTP-Streaming</strong>
                        <span>Für restriktive Hosts (Imunify360 Kill Modus, Magenta) — streamt Dateien einzeln ohne lokalen ZIP-Schreibvorgang. Langsamer, aber funktioniert überall.</span>
                    </div>
                </label>
            </div>
            <p class="description" style="margin-top:8px;">ZIP empfohlen für die meisten Hoster. Streaming wählen wenn File-Backups mit ZIP immer timeout-en.</p>
        </div>

        <div class="mlb-field mlb-excludes-field" style="margin-top:20px;">
            <label>Ausschlüsse <span style="font-weight:400;color:#646970;">(optional)</span></label>

            <div class="mlb-excludes-wrap">

                <div class="mlb-tree-panel">
                    <div class="mlb-tree-toolbar">
                        <strong>📂 wp-content/</strong>
                        <div class="mlb-tree-toolbar-actions">
                            <button type="button" id="mlb-load-tree" class="button button-small">
                                Verzeichnisbaum laden
                            </button>
                            <button type="button" id="mlb-tree-expand-all" class="button button-small" style="display:none;">↕ Alle aufklappen</button>
                            <button type="button" id="mlb-tree-collapse-all" class="button button-small" style="display:none;">↕ Alle zuklappen</button>
                        </div>
                    </div>
                    <div id="mlb-tree-container">
                        <p class="mlb-tree-hint">Lade den Verzeichnisbaum um Ordner per Klick auszuschließen.</p>
                    </div>
                </div>

                <div class="mlb-excludes-textarea-panel">
                    <div class="mlb-textarea-header">
                        <span>Ausgeschlossene Pfade</span>
                        <span class="mlb-exclude-count" id="mlb-exclude-count" style="display:none;"></span>
                    </div>
                    <textarea id="exclude_paths" name="exclude_paths" rows="12"
                              placeholder="themes/old-theme&#10;plugins/heavy-plugin&#10;uploads/2020"><?php echo esc_textarea( $s['exclude_paths'] ); ?></textarea>
                    <p class="description">
                        Ein Pfad pro Zeile, relativ zu <code>wp-content/</code>.
                        Baumauswahl und Textarea sind synchronisiert.
                    </p>
                </div>

            </div>
        </div>
    </div>

    <?php /* ─── Zeitplan ───────────────────────────────────────────────────── */ ?>
    <div class="mlb-card">
        <h2 class="mlb-card-title">🕐 Automatischer Zeitplan (WP-Cron)</h2>
        <div class="mlb-grid-2">

            <div class="mlb-field">
                <label for="schedule">Intervall</label>
                <select id="schedule" name="schedule">
                    <option value="none"   <?php selected( $s['schedule'], 'none'   ); ?>>Kein automatisches Backup</option>
                    <option value="daily"  <?php selected( $s['schedule'], 'daily'  ); ?>>Täglich</option>
                    <option value="weekly" <?php selected( $s['schedule'], 'weekly' ); ?>>Wöchentlich</option>
                </select>
            </div>

            <div class="mlb-field">
                <label for="schedule_time">Uhrzeit</label>
                <input type="time" id="schedule_time" name="schedule_time"
                       value="<?php echo esc_attr( $s['schedule_time'] ); ?>" />
                <p class="description">Serverzeit (UTC)</p>
            </div>

            <div class="mlb-field" id="mlb-field-day" style="<?php echo $s['schedule'] !== 'weekly' ? 'display:none;' : ''; ?>">
                <label for="schedule_day">Wochentag</label>
                <select id="schedule_day" name="schedule_day">
                    <?php
                    $days = [ 'monday'=>'Montag','tuesday'=>'Dienstag','wednesday'=>'Mittwoch',
                              'thursday'=>'Donnerstag','friday'=>'Freitag','saturday'=>'Samstag','sunday'=>'Sonntag' ];
                    foreach ( $days as $val => $label ):
                    ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['schedule_day'], $val ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mlb-field">
                <label for="retention_count">Aufbewahrung</label>
                <input type="number" id="retention_count" name="retention_count"
                       value="<?php echo esc_attr( $s['retention_count'] ); ?>"
                       min="1" max="365" style="width:100px;" />
                <p class="description">Anzahl der Backups, die behalten werden sollen.</p>
            </div>

        </div>
    </div>

    <?php /* ─── E-Mail-Benachrichtigung ──────────────────────────────────── */ ?>
    <div class="mlb-card">
        <h2 class="mlb-card-title">📧 E-Mail-Benachrichtigung</h2>
        <div class="mlb-grid-2">

            <div class="mlb-field">
                <label for="notify_email">E-Mail-Adresse</label>
                <input type="email" id="notify_email" name="notify_email"
                       value="<?php echo esc_attr( $s['notify_email'] ); ?>" />
            </div>

            <div class="mlb-field">
                <label for="notify_on">Benachrichtigen bei</label>
                <select id="notify_on" name="notify_on">
                    <option value="always" <?php selected( $s['notify_on'], 'always' ); ?>>Immer (Erfolg + Fehler)</option>
                    <option value="error"  <?php selected( $s['notify_on'], 'error'  ); ?>>Nur bei Fehler</option>
                    <option value="never"  <?php selected( $s['notify_on'], 'never'  ); ?>>Nie</option>
                </select>
            </div>

        </div>
    </div>

    <?php /* ─── Speichern ──────────────────────────────────────────────────── */ ?>
    <div class="mlb-submit-row">
        <button type="submit" id="mlb-save-settings" class="button button-primary button-large">
            💾 Einstellungen speichern
        </button>
        <span id="mlb-save-status" class="mlb-inline-status"></span>
    </div>

</form>
