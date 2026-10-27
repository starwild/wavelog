/*
 * China HAM Edition - quick fill.
 *
 * Fills the QSO form from one of three sources:
 *   1. my previous QSO            -> CnQuickFill.fromLast()
 *   2. a named QSO template       -> CnQuickFill.applyTemplate(name)
 *   3. a specific historical QSO  -> CnQuickFill.fromId(pk)   (?from=<pk> on load)
 *
 * Everything it may write comes from the server whitelist
 * (application/controllers/Qso_fill.php:: $fillable). This file never decides
 * "which field is safe" - it only knows how to put a value into the DOM.
 * That is why callsign / RST / QTH can never be copied: they simply never
 * arrive here.
 */
(function ($) {
	'use strict';

	var API = (window.base_url || '/') + 'index.php/qso_fill/';

	// payload key -> form element
	var SELECTORS = {
		station_profile: '#stationProfile',
		band: '#band',
		band_rx: '#band_rx',
		freq_display: '#frequency',
		freq_display_rx: '#frequency_rx',
		mode: '#mode',
		prop_mode: '#selectPropagation',
		sat_name: '#sat_name',
		sat_mode: '#sat_mode',
		cn_rpt: '#cn_rpt',
		cn_ctcss: '#cn_ctcss',
		transmit_power: '#transmit_power',
		my_rig: '#my_rig',
		my_antenna: '#my_antenna'
	};

	// fields whose change event triggers recalculation elsewhere in qso.js.
	//
	// Frequency MUST come before band: the band change handler compares
	// frequencyToBand(#frequency) with the new band and, when they differ,
	// fires an async GET /qso/band_to_freq that overwrites #frequency later.
	// Writing the frequency first makes that comparison succeed, so the async
	// default never runs and our value survives.
	var FILL_ORDER = ['freq_display', 'band', 'freq_display_rx', 'mode'];
	var NEEDS_CHANGE_EVENT = ['freq_display', 'band', 'freq_display_rx', 'mode'];

	function notify(text, ok) {
		if (typeof showToast === 'function') {
			showToast(ok === false ? 'Error' : 'Info', text, ok === false ? 'bg-danger text-white' : 'bg-info text-dark', 4000);
		} else {
			console.log('[CnQuickFill] ' + text);
		}
	}

	/**
	 * Put a whitelisted field set into the form.
	 *
	 * Order matters: band -> frequency -> mode. The band change handler resets
	 * the frequency to the band default when they disagree, so the frequency
	 * has to be written afterwards. The mode change handler resets RST, so we
	 * snapshot RST first and put it back at the end.
	 */
	function apply(fields, opts) {
		opts = opts || {};
		if (!fields) {
			return false;
		}

		var keep = {
			rst_sent: $('#rst_sent').val(),
			rst_rcvd: $('#rst_rcvd').val()
		};

		if (fields.station_profile !== undefined && $('#stationProfile').length) {
			$('#stationProfile').val(fields.station_profile);
			if (typeof panMap === 'function') {
				try { panMap(fields.station_profile); } catch (e) { /* map not ready */ }
			}
		}

		FILL_ORDER.forEach(function (key) {
			if (fields[key] === undefined) {
				return;
			}
			var $el = $(SELECTORS[key]);
			if (!$el.length) {
				return;
			}
			$el.val(fields[key]).trigger('change');
		});

		Object.keys(SELECTORS).forEach(function (key) {
			if (fields[key] === undefined || NEEDS_CHANGE_EVENT.indexOf(key) !== -1) {
				return;
			}
			var $el = $(SELECTORS[key]);
			if ($el.length) {
				$el.val(fields[key]);
			}
		});

		if (opts.preserve !== false) {
			$('#rst_sent').val(keep.rst_sent);
			$('#rst_rcvd').val(keep.rst_rcvd);
		}

		guardFrequency(fields);
		return true;
	}

	/**
	 * qso.js fires `band_to_freq/160m/AM` during page init (~250 ms in) and the
	 * responses land afterwards, overwriting #frequency with the band default.
	 * When we fill right after load (?from=<pk>) that leaves e.g. 1.838 MHz on
	 * a 70cm QSO.
	 *
	 * So: watch for a while and re-assert the frequency if it drifted. Stop
	 * after 3 consecutive stable checks (or ~5 s) so we never fight the user.
	 * Re-asserting with a change event is safe here: band is already set to the
	 * matching value, so the band handler sees them as consistent and does not
	 * fire another band_to_freq.
	 */
	function guardFrequency(fields) {
		if (fields.freq_display === undefined) {
			return;
		}
		var target = String(fields.freq_display);
		var tries = 0;
		var stable = 0;
		var timer = setInterval(function () {
			tries++;
			var current = String($('#frequency').val() || '');
			if (current === target) {
				stable++;
				if (stable >= 3) {
					clearInterval(timer);
				}
				return;
			}
			stable = 0;
			if (tries > 16) {
				clearInterval(timer);
				return;
			}
			if (fields.band !== undefined) {
				$('#band').val(fields.band);
			}
			$('#frequency').val(target).trigger('change');
		}, 300);
	}

	function getJSON(url) {
		return $.ajax({ url: url, method: 'GET', dataType: 'json' });
	}

	function applyResponse(resp) {
		if (!resp || resp.ok !== true) {
			if (resp && resp.error === 'no_previous_qso') {
				notify('还没有可填入的历史记录');
			} else {
				notify('回填失败：' + ((resp && resp.error) || 'unknown'), false);
			}
			return false;
		}
		apply(resp.fields || {});
		var src = resp.source || {};
		var when = src.time_on ? String(src.time_on).substring(0, 16) : '';
		notify('已填入 ' + when + (src.callsign ? ' 与 ' + src.callsign + ' ' : ' ') + '的那套参数（呼号未改动）');
		return true;
	}

	function fromLast() {
		getJSON(API + 'last').done(applyResponse).fail(function () {
			notify('回填失败：网络或服务异常', false);
		});
	}

	function fromId(id) {
		if (!id) {
			return;
		}
		getJSON(API + 'last?id=' + encodeURIComponent(id)).done(applyResponse).fail(function () {
			notify('回填失败：网络或服务异常', false);
		});
	}

	function applyTemplate(name) {
		getJSON(API + 'templates').done(function (all) {
			var fields = all && all[name];
			if (!fields) {
				notify('模板不存在或已被删除', false);
				return;
			}
			apply(fields);
			notify('已应用模板「' + name + '」（呼号未改动）');
		}).fail(function () {
			notify('模板读取失败', false);
		});
	}

	// ------------------------------------------------------------------ template menu/modal

	function renderMenu() {
		var $box = $('#cn_tpl_menu');
		if (!$box.length) {
			return;
		}
		getJSON(API + 'templates').done(function (all) {
			$box.empty();
			var names = Object.keys(all || {});
			if (!names.length) {
				$box.append($('<li><span class="dropdown-item-text text-muted small">还没有模板</span></li>'));
				return;
			}
			names.sort().forEach(function (name) {
				var $a = $('<a class="dropdown-item cn-tpl-item" href="#"></a>')
					.attr('data-tpl', name)
					.text(name);
				$box.append($('<li></li>').append($a));
			});
		});
	}

	function renderModalList() {
		var $list = $('#cnTplList');
		if (!$list.length) {
			return;
		}
		getJSON(API + 'templates').done(function (all) {
			$list.empty();
			var names = Object.keys(all || {});
			if (!names.length) {
				$list.append('<div class="text-muted small">还没有模板。填好表单后在上方输入名字保存。</div>');
				return;
			}
			names.sort().forEach(function (name) {
				var keys = Object.keys(all[name] || {});
				var $row = $('<div class="d-flex justify-content-between align-items-center border-bottom py-1"></div>');
				var $left = $('<div class="me-2"></div>');
				$left.append($('<div></div>').text(name));
				$left.append($('<div class="small text-muted"></div>').text(keys.join(', ')));
				var $btns = $('<div class="text-nowrap"></div>');
				$btns.append($('<button type="button" class="btn btn-sm btn-outline-primary me-1"></button>')
					.text('应用').attr('data-tpl', name).addClass('cn-tpl-apply'));
				$btns.append($('<button type="button" class="btn btn-sm btn-outline-danger"></button>')
					.text('删除').attr('data-tpl', name).addClass('cn-tpl-del'));
				$row.append($left).append($btns);
				$list.append($row);
			});
		});
	}

	function collectCurrentForm() {
		var fields = {};
		Object.keys(SELECTORS).forEach(function (key) {
			var $el = $(SELECTORS[key]);
			if (!$el.length) {
				return;
			}
			var v = $el.val();
			if (v !== null && v !== undefined && String(v).trim() !== '') {
				fields[key] = String(v);
			}
		});
		return fields;
	}

	function saveTemplate(name, overwrite) {
		var payload = { name: name, fields: collectCurrentForm() };
		if (overwrite) {
			payload.overwrite = 1;
		}
		return $.ajax({
			url: API + 'template_save',
			method: 'POST',
			contentType: 'application/json; charset=utf-8',
			dataType: 'json',
			data: JSON.stringify(payload)
		}).then(function (resp) {
			notify('模板「' + name + '」已保存');
			renderMenu();
			renderModalList();
			return resp;
		}, function (xhr) {
			var resp = (xhr && xhr.responseJSON) || {};
			if (resp.error === 'template_name_exists') {
				if (window.confirm('模板「' + name + '」已存在，要覆盖吗？')) {
					return saveTemplate(name, true);
				}
				return null;
			}
			var msg = resp.error || 'unknown';
			if (msg === 'template_name_invalid') {
				msg = '名字不能为空，且不超过 45 个字符';
			} else if (msg === 'template_limit_reached') {
				msg = '模板数量已达上限';
			}
			notify('保存失败：' + msg, false);
			return null;
		});
	}

	function deleteTemplate(name) {
		if (!window.confirm('删除模板「' + name + '」？')) {
			return;
		}
		$.ajax({
			url: API + 'template_delete',
			method: 'POST',
			contentType: 'application/json; charset=utf-8',
			dataType: 'json',
			data: JSON.stringify({ name: name })
		}).done(function () {
			notify('模板「' + name + '」已删除');
			renderMenu();
			renderModalList();
		}).fail(function () {
			notify('删除失败', false);
		});
	}

	function seedPresets() {
		var presets = [
			{ name: 'UV 中继点名', fields: { band: '70cm', mode: 'FM' } },
			{ name: 'UV 直频 145.500', fields: { band: '2m', mode: 'FM', freq_display: '145500000', cn_rpt: '145.500' } },
			{ name: '短波 SSB 20m', fields: { band: '20m', mode: 'SSB', freq_display: '14200000' } }
		];
		if (!window.confirm('导入 3 个示例模板？（同名的会被跳过）')) {
			return;
		}
		presets.reduce(function (chain, p) {
			return chain.then(function () {
				return $.ajax({
					url: API + 'template_save',
					method: 'POST',
					contentType: 'application/json; charset=utf-8',
					dataType: 'json',
					data: JSON.stringify(p)
				}).catch(function () { return null; });
			});
		}, $.when()).then(function () {
			notify('示例模板已导入');
			renderMenu();
			renderModalList();
		});
	}

	function openModal(focusName) {
		var el = document.getElementById('cnTplModal');
		if (!el) {
			return;
		}
		renderModalList();
		if ($('#cnTplName').length && !$('#cnTplName').val()) {
			$('#cnTplName').val((($('#band').val() || '') + '/' + ($('#mode').val() || '')).replace(/^\/|\/$/g, ''));
		}
		bootstrap.Modal.getOrCreateInstance(el).show();
		if (focusName) {
			setTimeout(function () { $('#cnTplName').trigger('focus'); }, 300);
		}
	}

	function boot() {
		if (!$('#cn_fill_last').length) {
			return;
		}

		$(document).on('click', '#cn_fill_last', function (e) {
			e.preventDefault();
			fromLast();
		});

		$(document).on('click', '#cn_tpl_menu .cn-tpl-item', function (e) {
			e.preventDefault();
			applyTemplate($(this).attr('data-tpl'));
		});

		$(document).on('click', '#cn_tpl_save', function (e) {
			e.preventDefault();
			openModal(true);
		});

		$(document).on('click', '#cn_tpl_manage', function (e) {
			e.preventDefault();
			openModal(false);
		});

		$(document).on('click', '.cn-tpl-apply', function () {
			applyTemplate($(this).attr('data-tpl'));
		});

		$(document).on('click', '.cn-tpl-del', function () {
			deleteTemplate($(this).attr('data-tpl'));
		});

		$(document).on('click', '#cnTplSaveBtn', function () {
			var name = ($('#cnTplName').val() || '').trim();
			if (!name) {
				notify('请先给模板起个名字', false);
				return;
			}
			saveTemplate(name, false);
		});

		$(document).on('click', '#cnTplPreset', function (e) {
			e.preventDefault();
			seedPresets();
		});

		renderMenu();

		// qso?manual=1&from=<pk>  -> fill on load. Defer so qso.js's own
		// document.ready (script loaded before this one) has finished binding.
		var m = /[?&]from=(\d+)/.exec(window.location.search);
		if (m) {
			setTimeout(function () { fromId(m[1]); }, 0);
		}
	}

	window.CnQuickFill = {
		apply: apply,
		fromLast: fromLast,
		fromId: fromId,
		applyTemplate: applyTemplate,
		renderMenu: renderMenu
	};

	$(document).ready(boot);
})(jQuery);
