/**
 * CHIROBASIX Google Reviews Widget - Admin JS
 */
(function ($) {
	'use strict';

	// Place search — reads API key from the input field (no save required).
	$('#cbxr-search-btn').on('click', function () {
		var query = $('#cbxr-place-search').val().trim();
		if (!query) return;

		var apiKey = $('#cbxr_api_key').val().trim();
		if (!apiKey) {
			$('#cbxr-search-results').html('<p style="color:#d63638;">Please enter your Google API Key above first.</p>');
			$('#cbxr_api_key').focus();
			return;
		}

		var $btn = $(this);
		var $results = $('#cbxr-search-results');

		$btn.prop('disabled', true).text('Searching...');
		$results.html('');

		$.post(cbxrAdmin.ajaxUrl, {
			action: 'cbxr_search_places',
			nonce: cbxrAdmin.nonce,
			query: query,
			api_key: apiKey
		}, function (response) {
			$btn.prop('disabled', false).text('Search');

			if (!response.success) {
				$results.html('<p style="color:#d63638;">' + response.data + '</p>');
				return;
			}

			if (!response.data.length) {
				$results.html('<p>No results found. Try adding the city name (e.g. "Tri-States Chiropractic Dubuque").</p>');
				return;
			}

			response.data.forEach(function (place) {
				var ratingHtml = '';
				if (place.rating) {
					ratingHtml = '<span class="cbxr-search-result-rating">' +
						'<strong>' + place.rating + '</strong> &#9733;' +
						(place.user_ratings_total ? ' (' + place.user_ratings_total + ' reviews)' : '') +
						'</span>';
				}

				var $item = $(
					'<div class="cbxr-search-result">' +
						'<div class="cbxr-search-result-info">' +
							'<span class="cbxr-search-result-name"></span>' +
							'<span class="cbxr-search-result-addr"></span>' +
							ratingHtml +
						'</div>' +
						'<span class="cbxr-search-result-select">Select &#8594;</span>' +
					'</div>'
				);

				$item.find('.cbxr-search-result-name').text(place.name);
				$item.find('.cbxr-search-result-addr').text(place.formatted_address || '');

				$item.on('click', function () {
					$('#cbxr_place_id').val(place.place_id);
					$results.html(
						'<div class="cbxr-selected-place">' +
							'<p style="color:#00a32a; margin-bottom:8px;"><strong>&#10003; Selected:</strong> ' + place.name + '</p>' +
							'<p style="color:#666; font-size:12px;">Click <strong>Save Settings</strong> below, then <strong>Refresh Reviews Now</strong> to pull in reviews.</p>' +
						'</div>'
					);

					// Show map preview immediately.
					var mapHtml = '<iframe width="100%" height="250" style="border:0; border-radius:8px;" ' +
						'loading="lazy" referrerpolicy="no-referrer-when-downgrade" ' +
						'src="https://www.google.com/maps/embed/v1/place?key=' + encodeURIComponent(apiKey) +
						'&q=place_id:' + encodeURIComponent(place.place_id) + '"></iframe>';
					$('#cbxr-map-preview').html(mapHtml);
				});

				$results.append($item);
			});
		}).fail(function () {
			$btn.prop('disabled', false).text('Search');
			$results.html('<p style="color:#d63638;">Search request failed. Check your API key and make sure the Places API is enabled.</p>');
		});
	});

	// Enter key in search field.
	$('#cbxr-place-search').on('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			$('#cbxr-search-btn').trigger('click');
		}
	});

	// Refresh reviews.
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

			setTimeout(function () { location.reload(); }, 2000);
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.html('<span style="color:#d63638;">Request failed.</span>');
		});
	});
})(jQuery);
