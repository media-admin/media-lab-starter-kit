/* global mltDashboard, jQuery */
(function ($) {
    'use strict';

    // ── Dashboard: Daten aktualisieren ──────────────────────────────────────

    $('#mlt-refresh-gsc').on('click', function () {
        const $btn    = $(this);
        const $result = $('#mlt-refresh-result');

        $btn.prop('disabled', true).text('⏳ Aktualisiere…');

        $.post(mltDashboard.ajaxUrl, {
            action: 'mlt_refresh_gsc',
            nonce:  mltDashboard.nonce,
        })
        .done(function (res) {
            if (res.success) {
                $result.text('✓ ' + res.data);
                setTimeout(() => location.reload(), 800);
            } else {
                $result.css('color', '#dc2626').text('✗ Fehler beim Aktualisieren.');
                $btn.prop('disabled', false).text('🔄 Daten aktualisieren');
            }
        })
        .fail(function () {
            $result.css('color', '#dc2626').text('✗ Verbindungsfehler.');
            $btn.prop('disabled', false).text('🔄 Daten aktualisieren');
        });
    });

    // ── Verlaufs-Chart: Reiter umschalten ───────────────────────────────────

    $(document).on('click', '.mlt-chart-tab', function () {
        const key = $(this).attr('data-chart');

        $('.mlt-chart-tab').removeClass('is-active').attr('aria-selected', 'false');
        $(this).addClass('is-active').attr('aria-selected', 'true');

        $('.mlt-chart-panel').each(function () {
            $(this).prop('hidden', $(this).attr('data-chart-panel') !== key);
        });
    });

})(jQuery);
