(function ($) {
	'use strict';
	function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

	function optionHtml(options, current) {
		return (options || []).map(function (opt) {
			return '<option value="' + esc(opt.value) + '"' + (String(opt.value) === String(current) ? ' selected' : '') + '>' + esc(opt.label) + '</option>';
		}).join('');
	}

	function refreshHeadingMode($box) {
		const mode = $box.find('.smm-auto-heading-mode').val() || 'none';
		$box.find('.smm-auto-heading-manual-wrap').toggle(mode === 'manual');
		$box.find('.smm-auto-heading-link-wrap').toggle(mode === 'manual' || mode === 'linked');
		$box.find('.smm-auto-heading-parent-note').toggle(mode === 'parent');
		const $desc = $box.find('.smm-auto-heading-link-description');
		if (mode === 'linked') $desc.text('Titel und Link werden automatisch aus dem gewählten Inhalt übernommen.');
		else if (mode === 'manual') $desc.text('Optional: Die freie Überschrift kann auf den gewählten Inhalt verlinken.');
	}


	function refreshMoreTarget($box) {
		const mode = $box.find('.smm-auto-more-target').val() || 'auto';
		$box.find('.smm-auto-more-linked-wrap').toggle(mode === 'linked');
		$box.find('.smm-auto-more-custom-wrap').toggle(mode === 'custom');
		const $note = $box.find('.smm-auto-more-source-note');
		const $text = $note.find('.description');
		let note = '';
		if (mode === 'auto') note = 'Reihenfolge der automatischen Zielwahl: gewählte Kategorie/Taxonomie → übergeordneter Inhalt → Archiv des Inhaltstyps.';
		else if (mode === 'taxonomy') note = 'Verwendet die unter „Kategorie / Begriff“ gewählte Taxonomie-Seite.';
		else if (mode === 'parent') note = 'Verwendet den unter „Übergeordneter Inhalt“ gewählten Inhalt.';
		else if (mode === 'archive') note = 'Verwendet die Archivseite des gewählten Inhaltstyps, sofern dieser ein öffentliches Archiv besitzt.';
		$text.text(note);
		$note.toggle(!!note);
	}

	function refreshAutoBox($box) {
		if (!window.SMMNativeMenu) return;
		refreshHeadingMode($box);
		refreshMoreTarget($box);
		const postType = $box.find('.smm-auto-post-type').val() || 'post';
		const filters = (SMMNativeMenu.contentFilters && SMMNativeMenu.contentFilters[postType]) || [];
		const $tax = $box.find('.smm-auto-taxonomy');
		const taxCurrent = $tax.attr('data-current') || $tax.val() || '';
		const taxOptions = [{label: 'Kein Filter', value: ''}].concat(filters.map(function (f) { return {label: f.label, value: f.taxonomy}; }));
		if (!taxOptions.some(function (o) { return String(o.value) === String(taxCurrent); })) $tax.attr('data-current', '');
		$tax.html(optionHtml(taxOptions, $tax.attr('data-current') || ''));
		const tax = $tax.val() || '';
		const filter = filters.find(function (f) { return f.taxonomy === tax; });
		const $term = $box.find('.smm-auto-term');
		const termCurrent = $term.attr('data-current') || $term.val() || 0;
		if (filter && filter.terms && filter.terms.length) {
			$term.html(optionHtml(filter.terms, termCurrent));
			$box.find('.smm-auto-term-wrap').show();
		} else {
			$term.html('<option value="0">Alle</option>');
			$box.find('.smm-auto-term-wrap').hide();
		}
		const parents = (SMMNativeMenu.contentParents && SMMNativeMenu.contentParents[postType]) || [];
		const $parent = $box.find('.smm-auto-parent');
		const parentCurrent = $parent.attr('data-current') || $parent.val() || 0;
		if (parents.length) {
			$parent.html(optionHtml(parents, parentCurrent));
			$box.find('.smm-auto-parent-wrap').show();
		} else {
			$parent.html('<option value="0">Alle Ebenen</option>');
			$box.find('.smm-auto-parent-wrap').hide();
		}
	}

	$(function () {
		$('.smm-add-special').on('click', function () {
			const $btn = $(this);
			const $wrap = $btn.closest('.smm-special-metabox');
			const kind = $btn.data('kind');
			const heading = $wrap.find('.smm-special-heading[data-kind="' + kind + '"]').val() || '';
			const menuId = Number($wrap.data('menu-id') || 0);
			if (!menuId || !window.SMMNativeMenu) return;
			$btn.prop('disabled', true).text('Wird hinzugefügt …');
			$.post(SMMNativeMenu.ajaxUrl, { action: 'smm_add_special_nav_item', nonce: SMMNativeMenu.nonce, menuId: menuId, kind: kind, heading: heading })
				.done(function (res) {
					if (res && res.success) window.location.href = 'nav-menus.php?action=edit&menu=' + menuId;
					else alert((res && res.data && res.data.message) || 'Element konnte nicht hinzugefügt werden.');
				}).fail(function () { alert('Element konnte nicht hinzugefügt werden.'); })
				.always(function () { $btn.prop('disabled', false); });
		});

		$('[data-smm-auto-item]').each(function () { refreshAutoBox($(this)); });
		$(document).on('change', '.smm-auto-post-type', function () {
			const $box = $(this).closest('[data-smm-auto-item]');
			$box.find('.smm-auto-taxonomy,.smm-auto-term,.smm-auto-parent').attr('data-current', '');
			refreshAutoBox($box);
		});
		$(document).on('change', '.smm-auto-taxonomy', function () {
			const $box = $(this).closest('[data-smm-auto-item]');
			$(this).attr('data-current', $(this).val());
			$box.find('.smm-auto-term').attr('data-current', '');
			refreshAutoBox($box);
		});
		$(document).on('change', '.smm-auto-heading-mode', function () {
			refreshHeadingMode($(this).closest('[data-smm-auto-item]'));
		});
		$(document).on('change', '.smm-auto-more-target', function () {
			refreshMoreTarget($(this).closest('[data-smm-auto-item]'));
		});
	});
})(jQuery);
