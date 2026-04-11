/**
 * CHIROBASIX Google Reviews Widget - Admin JS
 */
(function ($) {
	'use strict';

	// Place search
	$('#cbxr-search-btn').on('click', function () {
		var query = $('#cbxr-place-search').val().trim();
		if (!query) return;

		var $btn = $(this);
		var $results = $('#cbxr-search-results');

		$btn.prop('disabled', true).text('Searching...');
		$results.html('');

		$.post(cbxrAdmin.ajaxUrl, {
			action: 'cbxr_search_places',
			nonce: cbxrAdmin.nonce,
			query: query
		}, function (response) {
			$btn.prop('disabled', false).text('Search');

			if (!response.success) {
				$results.html('<p style="color:#d63638;">' + response.data + '</p>');
				return;
			}

			if (!response.data.length) {
				$results.html('<p>No results found. Try a more specific search.</p>');
				return;
			}

			response.data.forEach(function (place) {
				var $item = $(
					'<div class="cbxr-search-result">' +
						'<div class="cbxr-search-result-info">' +
							'<span class="cbxr-search-result-name"></span>' +
							'<span class="cbxr-search-result-addr"></span>' +
						'</div>' +
						'<span class="cbxr-search-result-select">Select &rarr;</span>' +
					'</div>'
				);

				$item.find('.cbxr-search-result-name').text(place.name);
				$item.find('.cbxr-search-result-addr').text(place.formatted_address || '');
				$item.data('placeId', place.place_id);

				$item.on('click', function () {
					$('#cbxr_place_id').val(place.place_id);
					$results.html('<p style="color:#00a32a;"><strong>Selected:</strong> ' + place.name + '</p>');
				});

				$results.append($item);
			});
		}).fail(function () {
			$btn.prop('disabled', false).text('Search');
			$results.html('<p style="color:#d63638;">Search failed. Check your API key.</p>');
		});
	});

	// Enter key in search field
	$('#cbxr-place-search').on('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			$('#cbxr-search-btn').trigger('click');
		}
	});

	// Refresh reviews
	$('#cbxr-refresh-btn').on('click', function () {
		var $btn = $(this);
		var $status = $('#cbxr-refresh-status');

		$btn.prop('disabled', true);
		$status.html('<span class="cbxr-spinner"></span> Refreshing...');

		$.post(cbxrAdmin.ajaxUrl, {
			action: 'cbxr_refresh_reviews',
			nonce: cbxrAdmin.nonce
		}, function (response) {
			$btn.prop('disabled', false);

			if (!response.success) {
				$status.html('<span style="color:#d63638;">Error: ' + response.data + '</span>');
				return;
			}

			var d = response.data;
			$status.html(
				'<span style="color:#00a32a;">Done! ' +
				d.total_cached + ' reviews cached, ' +
				d.review_count + ' displayed (filtered). ' +
				'Rating: ' + d.rating + ' (' + d.total_reviews + ' total)</span>'
			);

			// Reload after 2s to update the status card
			setTimeout(function () { location.reload(); }, 2000);
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.html('<span style="color:#d63638;">Request failed.</span>');
		});
	});
})(jQuery);
