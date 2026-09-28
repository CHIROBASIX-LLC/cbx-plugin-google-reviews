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

	// The widget is positioned/hidden via inline style attributes (see render()), so it survives
	// WP Rocket "Remove Unused CSS" — which strips the stylesheet's `.cbxr-open` rule and would
	// otherwise leave the panel stuck off-canvas. Drive open/close through those same inline styles.
	var hiddenTransform = widget.classList.contains('cbxr-pos-right') ? 'translateX(101%)' : 'translateX(-101%)';

	// Only the first reviews are printed into the page; the rest are fetched once, when the panel is
	// about to open (hover/focus on the badge) or opens. On any failure a "See all reviews on Google"
	// link replaces the loading line, so the panel never looks broken.
	var more = widget.querySelector('.cbxr-more');
	var loading = false;
	function loadMore() {
		if (!more || loading || !window.fetch) { return; }
		loading = true;
		var src = more.getAttribute('data-cbxr-src');
		fetch(src, { credentials: 'same-origin' })
			.then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
			.then(function (data) {
				if (!data || typeof data.html !== 'string') { throw new Error('bad response'); }
				var list = more.parentNode;
				if (data.replace) {
					// The page is older than the review list: swap in the whole current list.
					var old = list.querySelectorAll('.cbxr-review-card');
					for (var i = 0; i < old.length; i++) { old[i].parentNode.removeChild(old[i]); }
				}
				var tpl = document.createElement('div');
				tpl.innerHTML = data.html;
				var cards = tpl.querySelectorAll('.cbxr-review-card');
				for (var j = 0; j < cards.length; j++) {
					var k = cards[j].getAttribute('data-k');
					if (k && list.querySelector('.cbxr-review-card[data-k="' + k + '"]')) { continue; } // never show one twice
					list.insertBefore(cards[j], more);
				}
				list.removeChild(more);
				more = null;
			})
			.catch(function () {
				if (!more) { return; }
				var href = more.getAttribute('data-cbxr-fallback');
				more.innerHTML = '';
				if (href) {
					var a = document.createElement('a');
					a.href = href; a.target = '_blank'; a.rel = 'noopener noreferrer';
					a.className = 'cbxr-more-link';
					a.textContent = 'See all reviews on Google';
					more.appendChild(a);
				}
				more.classList.add('cbxr-more-failed');
				more = null;
			});
	}

	function openPanel() {
		loadMore();
		widget.classList.add('cbxr-open');
		panel.setAttribute('aria-hidden', 'false');
		document.body.style.overflow = 'hidden';
		panel.style.transform = 'translateX(0)';
		if (overlay) { overlay.style.opacity = '1'; overlay.style.pointerEvents = 'auto'; }
	}

	function closePanel() {
		widget.classList.remove('cbxr-open');
		panel.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
		panel.style.transform = hiddenTransform;
		if (overlay) { overlay.style.opacity = '0'; overlay.style.pointerEvents = 'none'; }
	}

	badge.addEventListener('click', openPanel);
	badge.addEventListener('pointerenter', function (e) { if (e.pointerType !== 'touch') { loadMore(); } }); // hover prefetch, not phone swipes
	badge.addEventListener('focus', loadMore);
	close.addEventListener('click', closePanel);
	overlay.addEventListener('click', closePanel);

	// Escape key
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && widget.classList.contains('cbxr-open')) {
			closePanel();
		}
	});

	// Read more toggles (delegated, so cards loaded later work too)
	widget.addEventListener('click', function (e) {
		var btn = e.target && e.target.closest ? e.target.closest('.cbxr-read-more') : null;
		if (!btn) { return; }
		var textEl = btn.previousElementSibling;
		if (textEl && textEl.classList.contains('cbxr-review-text')) {
			var isExpanded = textEl.classList.toggle('cbxr-expanded');
			btn.textContent = isExpanded ? 'Show less' : 'Read more';
		}
	});
})();
