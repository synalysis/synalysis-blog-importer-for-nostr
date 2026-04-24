(function () {
	'use strict';

	var cfg = window.synalysisBlogImporterLogin;
	if (!cfg || !cfg.restUrl || !cfg.nonce) {
		return;
	}

	var EVT = 'synalysis-blog-importer-for-nostr:provider';
	var missedProviderEvent = false;
	var tryAttachNow = null;

	window.addEventListener(
		EVT,
		function () {
			missedProviderEvent = true;
			if (typeof tryAttachNow === 'function') {
				tryAttachNow();
			}
		},
		false
	);

	function $(id) {
		return document.getElementById(id);
	}

	function setStatus(el, msg) {
		if (el) {
			el.textContent = msg || '';
		}
	}

	function getNostrProvider() {
		var n = window.nostr;
		if (n && typeof n.getPublicKey === 'function' && typeof n.signEvent === 'function') {
			return n;
		}
		return null;
	}

	function attachLoginHandler(btn, statusEl, nostr) {
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

	function init() {
		var btn = $('synalysis-blog-importer-login-btn');
		var statusEl = $('synalysis-blog-importer-login-status');
		if (!btn || !statusEl) {
			return;
		}

		var loginAttached = false;

		function onNostrReady(nostr) {
			if (loginAttached || !nostr) {
				return;
			}
			loginAttached = true;
			setStatus(statusEl, '');
			btn.disabled = false;
			if (cfg.strings && cfg.strings.button) {
				btn.textContent = cfg.strings.button;
			}
			attachLoginHandler(btn, statusEl, nostr);
		}

		function tryAttach() {
			onNostrReady(getNostrProvider());
		}

		tryAttachNow = tryAttach;
		if (missedProviderEvent) {
			tryAttach();
		}
		tryAttach();

		window.addEventListener(
			'load',
			function () {
				tryAttach();
				window.setTimeout(function () {
					if (!loginAttached) {
						setStatus(statusEl, (cfg.strings && cfg.strings.noExtension) || '');
					}
				}, 0);
			},
			{ once: true }
		);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
