/**
 * Drag & Drop Post Order
 * Macht die Admin-Tabelle sortierbar via jQuery UI Sortable.
 * Keine Änderung zur Vorgängerversion erforderlich.
 */
(function ($) {
	'use strict';

	$(function () {
		var $tbody = $('#the-list');
		if (!$tbody.length) return;

		var $notice = $('<div id="medialab-order-notice" style="display:none;"></div>');
		$('#wpbody-content').prepend($notice);

		function showNotice(msg, type) {
			$notice
				.attr('class', 'notice notice-' + type + ' is-dismissible')
				.html('<p>' + msg + '</p>')
				.show();

			// Dismiss-Button WP-Standard
			$(document).trigger('wp-updates-notice-added', [$notice]);
		}

		$tbody.sortable({
			items:            'tr',
			axis:             'y',
			handle:           '.medialab-drag-handle',
			cursor:           'grabbing',
			placeholder:      'medialab-sort-placeholder',
			forcePlaceholderSize: true,
			helper: function (e, ui) {
				// Spaltenbreiten beim Drag erhalten
				ui.children().each(function () {
					$(this).width($(this).width());
				});
				return ui;
			},
			start: function (e, ui) {
				ui.placeholder.html('<td colspan="' + ui.item.find('td').length + '"></td>');
			},
			stop: function () {
				saveOrder();
			}
		});

		// Drag-Handle in erste Spalte jeder Zeile einfügen
		$tbody.find('tr').each(function () {
			var $firstTd = $(this).find('td:first');
			$firstTd.prepend('<span class="medialab-drag-handle dashicons dashicons-menu" title="' +
				'Ziehen zum Sortieren' + '"></span>');
		});

		function saveOrder() {
			showNotice(medialabPostOrder.i18n.saving, 'info');

			var order = [];
			$tbody.find('tr').each(function () {
				var id = $(this).attr('id');
				if (id) {
					// WP-Standard: ID ist z.B. "post-42"
					var postId = parseInt(id.replace('post-', ''), 10);
					if (!isNaN(postId)) {
						order.push(postId);
					}
				}
			});

			$.post(medialabPostOrder.ajaxUrl, {
				action:    'medialab_update_post_order',
				nonce:     medialabPostOrder.nonce,
				post_type: medialabPostOrder.postType,
				order:     order
			}, function (response) {
				if (response.success) {
					showNotice(medialabPostOrder.i18n.saved, 'success');
				} else {
					showNotice(medialabPostOrder.i18n.error, 'error');
				}
			}).fail(function () {
				showNotice(medialabPostOrder.i18n.error, 'error');
			});
		}
	});

}(jQuery));
