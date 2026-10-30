(function (blocks, element, i18n, components, blockEditor, ServerSideRender) {
	'use strict';
	const el = element.createElement;
	const { registerBlockType } = blocks;
	const useEffect = element.useEffect;
	const { __ } = i18n;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, SelectControl, Button, Notice } = components;

	registerBlockType('smm/mega-menu', {
		edit: function (props) {
			const attrs = props.attributes || {};
			const blockProps = useBlockProps({ className: 'smm-editor-block' });
			const menus = (window.SMMBlockData && SMMBlockData.nativeMenus) || [];
			const legacyMap = (window.SMMBlockData && SMMBlockData.legacyNativeMap) || {};
			const navigationMap = (window.SMMBlockData && SMMBlockData.navigationNativeMap) || {};
			const migrated = !attrs.navMenuId ? Number(legacyMap[String(attrs.menuId || 0)] || navigationMap[String(attrs.navigationId || 0)] || 0) : 0;
			const selected = Number(attrs.navMenuId || migrated || 0);
			useEffect(function () { if (!attrs.navMenuId && migrated) props.setAttributes({ navMenuId: migrated }); }, [migrated]);
			return el('div', blockProps,
				el(InspectorControls, {},
					el(PanelBody, { title: __('Megamenü', 'schlichtes-megamenue'), initialOpen: true },
						el(SelectControl, {
							label: __('WordPress-Menü auswählen', 'schlichtes-megamenue'),
							value: selected,
							options: menus,
							onChange: function (value) { props.setAttributes({ navMenuId: Number(value || 0) }); }
						}),
						el(Button, {
							variant: 'primary',
							href: selected ? ((window.SMMBlockData && SMMBlockData.structureUrl) || '#') + '?action=edit&menu=' + selected : ((window.SMMBlockData && SMMBlockData.structureUrl) || '#'),
							target: '_blank'
						}, __('Menüstruktur bearbeiten', 'schlichtes-megamenue')),
						el(Button, {
							variant: 'secondary',
							href: selected ? ((window.SMMBlockData && SMMBlockData.adminUrl) || '#') + '&menu_id=' + selected : ((window.SMMBlockData && SMMBlockData.adminUrl) || '#'),
							target: '_blank',
							style: { marginTop: '8px' }
						}, __('Design & Verhalten', 'schlichtes-megamenue')),
						el('p', { className: 'components-base-control__help' }, __('Sortierung und Hierarchie werden nativ im WordPress-Menüeditor verwaltet.', 'schlichtes-megamenue'))
					)
				),
				selected ? el(ServerSideRender, { block: 'smm/mega-menu', attributes: attrs }) :
					el(Notice, { status: 'info', isDismissible: false }, __('Wähle ein WordPress-Menü aus. Die Menüstruktur wird unter „Megamenü → Menüstruktur“ bzw. im nativen WordPress-Menüeditor gepflegt.', 'schlichtes-megamenue'))
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender);
