/**
 * CHIROBASIX Google Reviews Widget - Frontend Widget
 */
(function () {
	'use strict';

	var widget  = document.getElementById('cbxr-widget');
	var badge   = document.getElementById('cbxr-badge');
	var panel   = document.getElementById('cbxr-panel');
	var close   = document.getElementById('cbxr-close');
	var overlay = document.getElementById('cbxr-overlay');

	if (!widget || !badge || !panel) return;

	function openPanel() {
		widget.classList.add('cbxr-open');
		panel.setAttribute('aria-hidden', 'false');
		document.body.style.overflow = 'hidden';
	}

	function closePanel() {
		widget.classList.remove('cbxr-open');
		panel.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
	}

	badge.addEventListener('click', openPanel);
	close.addEventListener('click', closePanel);
	overlay.addEventListener('click', closePanel);

	// Escape key
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && widget.classList.contains('cbxr-open')) {
			closePanel();
		}
	});

	// Read more toggles
	var readMoreBtns = widget.querySelectorAll('.cbxr-read-more');
	readMoreBtns.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var textEl = btn.previousElementSibling;
			if (textEl && textEl.classList.contains('cbxr-review-text')) {
				var isExpanded = textEl.classList.toggle('cbxr-expanded');
				btn.textContent = isExpanded ? 'Show less' : 'Read more';
			}
		});
	});
})();
