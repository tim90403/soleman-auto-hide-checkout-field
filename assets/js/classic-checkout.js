(function ($) {
	'use strict';

	function getSelectedPaymentMethod() {
		var $checked = $('input[name="payment_method"]:checked');
		return $checked.length ? $checked.val() : '';
	}

	function getVisibleFields(paymentMethod) {
		var map = (window.sahcfConfig && sahcfConfig.visibilityMap) || {};
		if (!paymentMethod || !Object.prototype.hasOwnProperty.call(map, paymentMethod)) {
			return null; // show all
		}
		return map[paymentMethod] || [];
	}

	function getAllFields() {
		return (window.sahcfConfig && sahcfConfig.allFields) || [];
	}

	function findFieldWrapper(fieldKey) {
		var $byId = $('#' + fieldKey + '_field');
		if ($byId.length) {
			return $byId;
		}
		var $byName = $('[name="' + fieldKey + '"]').closest('.form-row, .woocommerce-input-wrapper, p');
		if ($byName.length) {
			return $byName.first();
		}
		return $();
	}

	function applyVisibility() {
		var payment = getSelectedPaymentMethod();
		var visible = getVisibleFields(payment);
		var all = getAllFields();

		all.forEach(function (fieldKey) {
			var $wrap = findFieldWrapper(fieldKey);
			var $input = $('#' + fieldKey + ', [name="' + fieldKey + '"]');
			var shouldShow = visible === null || visible.indexOf(fieldKey) !== -1;

			if (!$wrap.length && !$input.length) {
				return;
			}

			if (shouldShow) {
				$wrap.removeClass('sahcf-hidden').show();
				$input.each(function () {
					var $el = $(this);
					if ($el.data('sahcfWasRequired')) {
						$el.prop('required', true);
						$el.attr('aria-required', 'true');
						$el.data('sahcfWasRequired', false);
					}
					$el.closest('.form-row').addClass('validate-required').removeClass('sahcf-optionalized');
				});
			} else {
				$wrap.addClass('sahcf-hidden').hide();
				$input.each(function () {
					var $el = $(this);
					if ($el.prop('required') || $el.attr('aria-required') === 'true') {
						$el.data('sahcfWasRequired', true);
						$el.prop('required', false);
						$el.removeAttr('required');
						$el.attr('aria-required', 'false');
					}
					$el.closest('.form-row').removeClass('validate-required').addClass('sahcf-optionalized');
					// Clear value so stale required data is not submitted.
					if ($el.is(':checkbox, :radio')) {
						$el.prop('checked', false);
					} else if (!$el.is('select')) {
						$el.val('');
					} else {
						$el.prop('selectedIndex', 0);
					}
				});
			}
		});
	}

	$(function () {
		if (!$('form.checkout').length) {
			return;
		}

		applyVisibility();

		$(document.body).on('change', 'input[name="payment_method"]', function () {
			applyVisibility();
		});

		$(document.body).on('updated_checkout', function () {
			applyVisibility();
		});
	});
})(jQuery);
