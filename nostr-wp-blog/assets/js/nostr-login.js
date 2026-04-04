(function () {
	'use strict';

	var cfg = window.nostrWpBlogLogin;
	if (!cfg || !cfg.restUrl || !cfg.nonce) {
		return;
	}

	function $(id) {
		return document.getElementById(id);
	}

	function setStatus(el, msg) {
		if (el) {
			el.textContent = msg || '';
		}
	}

	function getNostr() {
		return window.nostr;
	}

	function init() {
		var btn = $('nostr-wp-blog-login-btn');
		var statusEl = $('nostr-wp-blog-login-status');
		if (!btn || !statusEl) {
			return;
		}

		var nostr = getNostr();
		if (nostr && typeof nostr.getPublicKey === 'function' && typeof nostr.signEvent === 'function') {
			btn.disabled = false;
			if (cfg.strings && cfg.strings.button) {
				btn.textContent = cfg.strings.button;
			}
		} else {
			setStatus(statusEl, (cfg.strings && cfg.strings.noExtension) || '');
			return;
		}

		btn.addEventListener('click', function () {
			setStatus(statusEl, (cfg.strings && cfg.strings.working) || '…');
			btn.disabled = true;

			nostr
				.getPublicKey()
				.then(function (pubkey) {
					return fetch(cfg.restUrl + 'login-challenge', {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ nonce: cfg.nonce, pubkey: pubkey }),
					}).then(function (res) {
						return res.json().then(function (body) {
							return { res: res, body: body };
						});
					});
				})
				.then(function (out) {
					if (!out.res.ok) {
						var msg =
							(out.body && out.body.message) ||
							(out.body && out.body.code) ||
							((cfg.strings && cfg.strings.failed) || 'Error');
						setStatus(statusEl, msg);
						btn.disabled = false;
						return;
					}
					var d = out.body;
					var unsigned = {
						kind: 22242,
						created_at: Math.floor(Date.now() / 1000),
						tags: [
							['relay', d.relayUri],
							['challenge', d.challenge],
						],
						content: '',
					};
					return nostr.signEvent(unsigned).then(function (signed) {
						return fetch(cfg.restUrl + 'login-verify', {
							method: 'POST',
							credentials: 'same-origin',
							headers: { 'Content-Type': 'application/json' },
							body: JSON.stringify({
								nonce: cfg.nonce,
								token: d.token,
								redirect_to: cfg.redirectTo,
								event: {
									id: signed.id,
									pubkey: signed.pubkey,
									created_at: signed.created_at,
									kind: signed.kind,
									tags: signed.tags,
									content: signed.content || '',
									sig: signed.sig,
								},
							}),
						}).then(function (res) {
							return res.json().then(function (body) {
								return { res: res, body: body };
							});
						});
					});
				})
				.then(function (out) {
					if (!out) {
						return;
					}
					if (!out.res.ok) {
						var msg2 =
							(out.body && out.body.message) ||
							(out.body && out.body.code) ||
							((cfg.strings && cfg.strings.failed) || 'Error');
						setStatus(statusEl, msg2);
						btn.disabled = false;
						return;
					}
					if (out.body && out.body.redirect) {
						window.location.href = out.body.redirect;
						return;
					}
					setStatus(statusEl, (cfg.strings && cfg.strings.failed) || '');
					btn.disabled = false;
				})
				.catch(function () {
					setStatus(statusEl, (cfg.strings && cfg.strings.failed) || '');
					btn.disabled = false;
				});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
