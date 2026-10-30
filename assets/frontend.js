(function () {
	'use strict';

	function intData(element, key, fallback) {
		var value = parseInt(element.dataset[key], 10);
		return Number.isFinite(value) ? value : fallback;
	}

	function cssLengthToPx(value, fallback) {
		if (!value) return fallback;
		var probe = document.createElement('div');
		probe.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;width:' + value + ';';
		document.documentElement.appendChild(probe);
		var width = probe.getBoundingClientRect().width;
		probe.remove();
		return width > 0 ? width : fallback;
	}

	function themeWidth(variableName, fallback) {
		var value = window.getComputedStyle(document.documentElement).getPropertyValue(variableName).trim();
		return cssLengthToPx(value, fallback);
	}

	function unlockPageIfPossible() {
		if (!document.querySelector('.smm-mega-menu.is-mobile-open')) {
			document.documentElement.classList.remove('smm-overlay-open');
			document.body.classList.remove('smm-overlay-open');
		}
	}

	function initMenu(menu) {
		if (menu.dataset.smmReady === '1') return;
		menu.dataset.smmReady = '1';

		var behavior = menu.dataset.smmBehavior || 'hover';
		var clickTarget = menu.dataset.smmClickTarget || 'arrow';
		var openDelay = intData(menu, 'smmOpenDelay', 100);
		var closeDelay = intData(menu, 'smmCloseDelay', 240);
		var breakpoint = intData(menu, 'smmBreakpoint', 900);
		var mobileCollapsed = menu.dataset.smmMobileCollapsed !== '0';
		var overlay = menu.querySelector(':scope > .smm-mobile-overlay');
		var row = overlay ? overlay.querySelector('.smm-menu-row') : menu.querySelector(':scope > .smm-menu-row');
		var items = row ? Array.prototype.slice.call(row.querySelectorAll(':scope > [data-smm-item]')) : [];
		var mainToggle = menu.querySelector(':scope > .smm-mobile-main-toggle');
		var overlayClose = overlay ? overlay.querySelector('.smm-mobile-overlay-close') : null;
		var openTimer = null;
		var closeTimer = null;
		var current = null;

		function isMobile() {
			return window.innerWidth < breakpoint;
		}

		function panelFor(item) {
			return item ? item.querySelector(':scope > .smm-panel') : null;
		}

		function toggleFor(item) {
			return item ? item.querySelector(':scope > .smm-trigger-wrap > .smm-toggle') : null;
		}

		function topLinkFor(item) {
			return item ? item.querySelector(':scope > .smm-trigger-wrap > .smm-top-link') : null;
		}

		function focusableInOverlay() {
			if (!overlay) return [];
			return Array.prototype.slice.call(overlay.querySelectorAll(
				'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
			)).filter(function (element) {
				return !element.hidden && element.getAttribute('aria-hidden') !== 'true' && element.offsetParent !== null;
			});
		}

		function lockPage() {
			document.documentElement.classList.add('smm-overlay-open');
			document.body.classList.add('smm-overlay-open');
		}

		function openMobileOverlay(moveFocus) {
			if (!isMobile() || !mobileCollapsed || !overlay) return;
			menu.classList.add('is-mobile-open');
			menu.setAttribute('data-smm-overlay-open', '1');
			if (mainToggle) mainToggle.setAttribute('aria-expanded', 'true');
			overlay.setAttribute('aria-hidden', 'false');
			lockPage();
			if (moveFocus !== false) {
				window.requestAnimationFrame(function () {
					if (overlayClose) overlayClose.focus();
				});
			}
		}

		function closeMobileOverlay(restoreFocus) {
			if (!overlay) return;
			menu.classList.remove('is-mobile-open');
			menu.removeAttribute('data-smm-overlay-open');
			if (mainToggle) mainToggle.setAttribute('aria-expanded', 'false');
			overlay.setAttribute('aria-hidden', 'true');
			closeAll(null);
			unlockPageIfPossible();
			if (restoreFocus && mainToggle) mainToggle.focus();
		}

		function setMobileClass() {
			var mobile = isMobile();
			menu.classList.toggle('smm-mobile', mobile);
			if (!mobile) {
				closeMobileOverlay(false);
				if (overlay) overlay.setAttribute('aria-hidden', 'false');
				positionCurrent();
			} else if (overlay) {
				if (mobileCollapsed) {
					overlay.setAttribute('aria-hidden', menu.classList.contains('is-mobile-open') ? 'false' : 'true');
				} else {
					// Backward compatibility: an explicitly uncollapsed mobile menu remains inline.
					overlay.setAttribute('aria-hidden', 'false');
				}
			}
		}

		function panelTargetWidth(mode, menuRect, available) {
			if (mode === 'viewport') return available;
			if (mode === 'navigation') return Math.min(menuRect.width, available);
			if (mode === 'content') return Math.min(themeWidth('--wp--style--global--content-size', 760), available);
			if (mode === 'wide') return Math.min(themeWidth('--wp--style--global--wide-size', 1200), available);
			return Math.min(intData(menu, 'smmCustomWidth', 1100), available);
		}

		function positionPanel(panel) {
			if (!panel || isMobile() || menu.classList.contains('smm-orientation-vertical')) return;
			var gutter = intData(menu, 'smmEdgeGutter', 16);
			var menuRect = menu.getBoundingClientRect();
			var viewportWidth = document.documentElement.clientWidth;

			if (panel.classList.contains('smm-dropdown-panel')) {
				var owner = panel.closest('[data-smm-item]');
				if (!owner) return;
				var ownerRect = owner.getBoundingClientRect();
				panel.style.width = '';
				panel.style.left = '0px';
				var width = Math.min(panel.getBoundingClientRect().width || 220, Math.max(220, viewportWidth - gutter * 2));
				var left = ownerRect.left - menuRect.left;
				if (ownerRect.left + width > viewportWidth - gutter) {
					left = ownerRect.right - menuRect.left - width;
				}
				if (menuRect.left + left < gutter) left += gutter - (menuRect.left + left);
				panel.style.left = Math.round(left) + 'px';
				panel.classList.toggle('is-flyout-left', ownerRect.right + 460 > viewportWidth - gutter);
				return;
			}

			var mode = menu.dataset.smmWidthMode || 'wide';
			var alignment = menu.dataset.smmAlignment || 'center';
			var available = Math.max(280, viewportWidth - gutter * 2);
			var width = panelTargetWidth(mode, menuRect, available);
			var left;

			if (mode === 'viewport') {
				left = gutter - menuRect.left;
			} else if (mode === 'content' || mode === 'wide') {
				// Theme content/wide sizes describe the page layout, not the menu row.
				// Center these panels in the viewport so they sit exactly over the
				// corresponding centered WordPress content area, even when the nav
				// block itself is narrower or aligned to one side of the header.
				left = ((viewportWidth - width) / 2) - menuRect.left;
			} else if (alignment === 'left') {
				left = 0;
			} else if (alignment === 'right') {
				left = menuRect.width - width;
			} else {
				left = (menuRect.width - width) / 2;
			}

			var absoluteLeft = menuRect.left + left;
			if (absoluteLeft < gutter) left += gutter - absoluteLeft;
			if (absoluteLeft + width > viewportWidth - gutter) left -= absoluteLeft + width - (viewportWidth - gutter);

			panel.style.left = Math.round(left) + 'px';
			panel.style.width = Math.round(width) + 'px';
		}

		function positionCurrent() {
			positionPanel(panelFor(current));
		}

		function setExpanded(item, expanded) {
			var toggle = toggleFor(item);
			var panel = panelFor(item);
			item.classList.toggle('is-open', expanded);
			if (toggle) {
				toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
				var label = toggle.getAttribute('aria-label') || '';
				if (label) {
					toggle.setAttribute('aria-label', label.replace(expanded ? ' öffnen' : ' schließen', expanded ? ' schließen' : ' öffnen'));
				}
			}
			if (panel) panel.hidden = !expanded;
		}

		function closeItem(item, restoreFocus) {
			if (!item) return;
			setExpanded(item, false);
			if (current === item) current = null;
			if (restoreFocus) {
				var toggle = toggleFor(item);
				if (toggle) toggle.focus();
			}
		}

		function closeAll(except) {
			items.forEach(function (item) {
				if (item !== except && item.classList.contains('is-open')) closeItem(item, false);
			});
		}

		function openItem(item) {
			var panel = panelFor(item);
			var toggle = toggleFor(item);
			if (!panel || !toggle) return;
			window.clearTimeout(openTimer);
			window.clearTimeout(closeTimer);

			// Do not replay the entrance animation when the pointer moves from
			// the trigger into an already open descendant panel. Browsers can
			// emit another pointerenter for absolutely positioned descendants.
			if (item.classList.contains('is-open')) {
				current = item;
				positionPanel(panel);
				return;
			}

			closeAll(item);
			current = item;
			setExpanded(item, true);
			positionPanel(panel);
			panel.classList.add('smm-entering');
			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(function () { panel.classList.remove('smm-entering'); });
			});
		}

		function toggleItem(item) {
			if (item.classList.contains('is-open')) closeItem(item, false);
			else openItem(item);
		}

		function scheduleOpen(item) {
			window.clearTimeout(closeTimer);
			window.clearTimeout(openTimer);
			if (item && item.classList.contains('is-open')) {
				current = item;
				return;
			}
			openTimer = window.setTimeout(function () { openItem(item); }, openDelay);
		}

		function scheduleClose(item) {
			window.clearTimeout(openTimer);
			window.clearTimeout(closeTimer);
			closeTimer = window.setTimeout(function () { closeItem(item, false); }, closeDelay);
		}

		items.forEach(function (item) {
			var panel = panelFor(item);
			var toggle = toggleFor(item);
			var topLink = topLinkFor(item);
			if (!panel || !toggle) return;

			toggle.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				toggleItem(item);
			});

			if (topLink) {
				topLink.addEventListener('click', function (event) {
					if (!isMobile() && behavior === 'click' && clickTarget === 'item') {
						event.preventDefault();
						toggleItem(item);
					}
				});
			}

			item.addEventListener('pointerenter', function (event) {
				if (behavior === 'hover' && !isMobile() && event.pointerType !== 'touch') scheduleOpen(item);
			});

			item.addEventListener('pointerleave', function (event) {
				if (behavior === 'hover' && !isMobile() && event.pointerType !== 'touch') scheduleClose(item);
			});

			// Explicitly keep the item open while the pointer is over the panel.
			// This also bridges small visual offsets between bar and panel.
			panel.addEventListener('pointerenter', function (event) {
				if (behavior !== 'hover' || isMobile() || event.pointerType === 'touch') return;
				window.clearTimeout(openTimer);
				window.clearTimeout(closeTimer);
				current = item;
			});

			panel.addEventListener('pointerleave', function (event) {
				if (behavior === 'hover' && !isMobile() && event.pointerType !== 'touch') scheduleClose(item);
			});

			item.addEventListener('focusin', function () {
				// Inline click menus open through their toggle, avoiding focus/click double toggles.
				if (menu.classList.contains('smm-orientation-vertical') && behavior === 'click') return;
				if (!isMobile() && !item.classList.contains('is-open')) openItem(item);
			});

			item.addEventListener('focusout', function () {
				window.setTimeout(function () {
					if (!item.contains(document.activeElement) && !isMobile()) scheduleClose(item);
				}, 0);
			});

			item.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					event.preventDefault();
					if (isMobile() && menu.classList.contains('is-mobile-open')) closeMobileOverlay(true);
					else closeItem(item, true);
				} else if ((event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') && event.target === toggle) {
					event.preventDefault();
					openItem(item);
					var first = panel.querySelector('a[href], button:not([disabled])');
					if (first && event.key === 'ArrowDown') first.focus();
				}
			});
		});

		if (mainToggle) {
			mainToggle.addEventListener('click', function () {
				if (isMobile() && mobileCollapsed) {
					if (menu.classList.contains('is-mobile-open')) closeMobileOverlay(false);
					else openMobileOverlay(true);
					return;
				}
				var open = menu.classList.toggle('is-mobile-open');
				mainToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				if (!open) closeAll(null);
			});
		}

		if (overlayClose) {
			overlayClose.addEventListener('click', function () {
				closeMobileOverlay(true);
			});
		}

		if (overlay) {
			overlay.addEventListener('keydown', function (event) {
				if (!isMobile() || !mobileCollapsed || !menu.classList.contains('is-mobile-open')) return;
				if (event.key === 'Escape') {
					event.preventDefault();
					closeMobileOverlay(true);
					return;
				}
				if (event.key === 'Tab') {
					var focusable = focusableInOverlay();
					if (!focusable.length) return;
					var first = focusable[0];
					var last = focusable[focusable.length - 1];
					if (event.shiftKey && document.activeElement === first) {
						event.preventDefault();
						last.focus();
					} else if (!event.shiftKey && document.activeElement === last) {
						event.preventDefault();
						first.focus();
					}
				}
			});
		}

		document.addEventListener('pointerdown', function (event) {
			if (!isMobile() && current && !menu.contains(event.target)) closeItem(current, false);
		});

		window.addEventListener('resize', function () {
			setMobileClass();
			positionCurrent();
		});
		window.addEventListener('scroll', positionCurrent, { passive: true });
		setMobileClass();
	}

	function initAll(root) {
		var scope = root || document;
		if (scope.matches && scope.matches('.smm-mega-menu')) initMenu(scope);
		scope.querySelectorAll('.smm-mega-menu').forEach(initMenu);
	}

	window.SMMInitMenus = initAll;
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { initAll(document); });
	else initAll(document);
})();
