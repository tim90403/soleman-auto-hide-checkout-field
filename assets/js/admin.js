(function ($) {
	'use strict';

	function activateTab($tab) {
		var target = $tab.data('target');
		$('.sahcf-gateway-tab').removeClass('is-active');
		$('.sahcf-gateway-panel').removeClass('is-active');
		$tab.addClass('is-active');
		$('#' + target).addClass('is-active');
	}

	$(function () {
		var $root = $('.sahcf-settings');
		if (!$root.length) {
			return;
		}

		$root.on('click', '.sahcf-gateway-tab', function (e) {
			e.preventDefault();
			activateTab($(this));
		});

		$root.on('click', '.sahcf-select-all', function (e) {
			e.preventDefault();
			$(this).closest('.sahcf-gateway-panel').find('.sahcf-field-checkbox').prop('checked', true);
		});

		$root.on('click', '.sahcf-select-none', function (e) {
			e.preventDefault();
			$(this).closest('.sahcf-gateway-panel').find('.sahcf-field-checkbox').prop('checked', false);
		});
	});
})(jQuery);
