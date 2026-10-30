(function ($) {
	'use strict';
	$(function () {
		const $form = $('#smm-native-settings-form');
		const $preview = $('#smm-native-preview');
		let timer = null;

		$('.smm-native-tabs').on('click', 'button[data-tab]', function () {
			const tab = $(this).data('tab');
			$('.smm-native-tabs button').removeClass('is-active');
			$(this).addClass('is-active');
			$('.smm-native-panel').removeClass('is-active');
			$('.smm-native-panel[data-panel="' + tab + '"]').addClass('is-active');
		});

		function collectSettings() {
			const out = {};
			$form.find('[data-smm-setting]').each(function () {
				const key = $(this).data('smm-setting');
				if ($(this).is(':checkbox')) out[key] = $(this).is(':checked');
				else {
					let val = $(this).val();
					if ($(this).attr('type') === 'number') val = val === '' ? 0 : Number(val);
					out[key] = val;
				}
			});
			return out;
		}

		function refreshPreview() {
			if (!$preview.length || !window.SMMNativeSettings) return;
			const menuId = Number($preview.data('menu-id') || 0);
			if (!menuId) return;
			$preview.addClass('is-loading');
			$.post(SMMNativeSettings.ajaxUrl, {
				action: 'smm_native_preview',
				nonce: SMMNativeSettings.nonce,
				menuId: menuId,
				settings: JSON.stringify(collectSettings())
			}).done(function (response) {
				if (response && response.success && response.data && response.data.html) {
					$preview.html(response.data.html);
					if (window.SMMInitMenus) window.SMMInitMenus($preview[0]);
				}
			}).always(function () { $preview.removeClass('is-loading'); });
		}

		$form.on('input change', '[data-smm-setting]', function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(refreshPreview, 280);
		});
	});
})(jQuery);
