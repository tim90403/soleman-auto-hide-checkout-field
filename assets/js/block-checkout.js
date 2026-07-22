(function () {
	'use strict';

	var config = window.sahcfBlockConfig || { visibilityMap: {}, allFields: [] };
	var STYLE_ID = 'sahcf-block-dynamic-style';
	var lastPayment = '';

	function getVisibleFields(paymentMethod) {
		var map = config.visibilityMap || {};
		if (!paymentMethod || !Object.prototype.hasOwnProperty.call(map, paymentMethod)) {
			return null;
		}
		return map[paymentMethod] || [];
	}

	function mapClassicToBlock(fieldKey) {
		if (fieldKey.indexOf('billing_') === 0) {
			var billingName = fieldKey.slice(8);
			if (billingName === 'email') {
				return { group: 'contact', name: 'email' };
			}
			return { group: 'billing', name: billingName };
		}
		if (fieldKey.indexOf('shipping_') === 0) {
			return { group: 'shipping', name: fieldKey.slice(9) };
		}
		if (fieldKey === 'order_comments' || fieldKey === 'customer_note') {
			return { group: 'order', name: 'order_comments' };
		}
		return { group: 'additional', name: fieldKey };
	}

	function buildSelectors(fieldKey) {
		var mapped = mapClassicToBlock(fieldKey);
		var selectors = [];
		var name = mapped.name;

		// Core address / contact fields in Woo Blocks.
		selectors.push('.wc-block-components-address-form__' + name);
		selectors.push('.wc-block-components-text-input-' + name);
		selectors.push('#billing-' + name);
		selectors.push('#shipping-' + name);
		selectors.push('#email');

		if (mapped.group === 'billing') {
			selectors.push('.wc-block-checkout__billing-fields .wc-block-components-address-form__' + name);
		}
		if (mapped.group === 'shipping') {
			selectors.push('.wc-block-checkout__shipping-fields .wc-block-components-address-form__' + name);
		}
		if (mapped.group === 'contact' && name === 'email') {
			selectors.push('.wc-block-components-address-form__email');
			selectors.push('.wc-block-components-text-input-email');
			selectors.push('#email');
		}
		if (mapped.group === 'order' && name === 'order_comments') {
			selectors.push('.wc-block-checkout__add-note');
			selectors.push('.wc-block-components-textarea');
		}

		// Custom / THWCFD fields.
		selectors.push('[id="' + fieldKey + '"]');
		selectors.push('[name="' + fieldKey + '"]');
		selectors.push('[id*="' + fieldKey + '"]');
		selectors.push('[data-id="' + fieldKey + '"]');
		selectors.push('.wc-block-components-address-form__' + fieldKey);

		return selectors;
	}

	function applyVisibility(paymentMethod) {
		var visible = getVisibleFields(paymentMethod);
		var all = config.allFields || [];
		var rules = [];

		all.forEach(function (fieldKey) {
			var shouldShow = visible === null || visible.indexOf(fieldKey) !== -1;
			if (shouldShow) {
				return;
			}
			buildSelectors(fieldKey).forEach(function (selector) {
				rules.push(selector + '{display:none!important;}');
			});
		});

		var styleEl = document.getElementById(STYLE_ID);
		if (!styleEl) {
			styleEl = document.createElement('style');
			styleEl.id = STYLE_ID;
			document.head.appendChild(styleEl);
		}
		styleEl.textContent = rules.join('\n');

		// Soften HTML required attributes on currently hidden inputs.
		all.forEach(function (fieldKey) {
			var shouldShow = visible === null || visible.indexOf(fieldKey) !== -1;
			buildSelectors(fieldKey).forEach(function (selector) {
				document.querySelectorAll(selector + ' input, ' + selector + ' select, ' + selector + ' textarea, ' + selector).forEach(function (el) {
					if (!el || !el.tagName) {
						return;
					}
					var tag = el.tagName.toLowerCase();
					if (['input', 'select', 'textarea'].indexOf(tag) === -1) {
						return;
					}
					if (shouldShow) {
						if (el.dataset.sahcfWasRequired === '1') {
							el.required = true;
							el.setAttribute('aria-required', 'true');
							delete el.dataset.sahcfWasRequired;
						}
					} else if (el.required || el.getAttribute('aria-required') === 'true') {
						el.dataset.sahcfWasRequired = '1';
						el.required = false;
						el.setAttribute('aria-required', 'false');
						el.removeAttribute('required');
					}
				});
			});
		});
	}

	function readActivePaymentMethod() {
		try {
			if (window.wp && wp.data && wp.data.select) {
				var store =
					(window.wc && wc.wcBlocksData && wc.wcBlocksData.PAYMENT_STORE_KEY) ||
					'wc/store/payment';
				var select = wp.data.select(store);
				if (select && typeof select.getActivePaymentMethod === 'function') {
					return select.getActivePaymentMethod() || '';
				}
			}
		} catch (e) {
			// Fall through.
		}

		var checked = document.querySelector(
			'.wc-block-checkout input[name="radio-control-wc-payment-method-options"]:checked, .wc-block-components-radio-control__input:checked'
		);
		return checked ? checked.value : '';
	}

	var syncTimer = null;

	function sync() {
		if (syncTimer) {
			window.clearTimeout(syncTimer);
		}
		syncTimer = window.setTimeout(function () {
			var payment = readActivePaymentMethod();
			lastPayment = payment;
			applyVisibility(payment);
		}, 50);
	}

	function boot() {
		var hasBlock = document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout');
		if (!hasBlock) {
			return;
		}

		sync();

		if (window.wp && wp.data && typeof wp.data.subscribe === 'function') {
			wp.data.subscribe(function () {
				sync();
			});
		}

		document.addEventListener('change', function (event) {
			var t = event.target;
			if (!t) {
				return;
			}
			if (
				t.name === 'radio-control-wc-payment-method-options' ||
				(t.classList && t.classList.contains('wc-block-components-radio-control__input'))
			) {
				sync();
			}
		});

		if (window.MutationObserver) {
			var observer = new MutationObserver(function () {
				sync();
			});
			observer.observe(hasBlock, { childList: true, subtree: true });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
