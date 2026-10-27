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
	 * qso.js requests `band_to_freq/160m/AM` twice during page init and the
	 * responses land late (measured: 1.9 s and 3.5 s after load on the PHP
	 * built-in server, which serves requests serially). Each response writes
	 * #frequency with the 160m default - so a fill done at load time can be
	 * silently undone afterwards, leaving 1.838 MHz on a 70cm QSO.
	 *
	 * So keep watching for up to 8 s and re-assert whenever the value drifts.
	 * Guarding is cancelled as soon as the user touches frequency/band, so we
	 * never fight a human. Re-asserting with a change event is safe: the band
	 * is written to the matching value first, so the band handler sees them as
	 * consistent and will not fire another band_to_freq.
	 */
	function guardFrequency(fields) {
		if (fields.freq_display === undefined) {
			return;
		}
		var target = String(fields.freq_display);
		var deadline = Date.now() + 8000;
		var applying = false;
		var timer = null;

		function stop() {
			if (timer) {
				clearInterval(timer);
				timer = null;
			}
			$('#frequency, #band').off('.cnqf');
		}

		function reassert() {
			applying = true;
			if (fields.band !== undefined) {
				$('#band').val(fields.band);
			}
			$('#frequency').val(target).trigger('change');
			applying = false;
		}

		// the user takes over -> stop correcting
		$('#frequency, #band').on('keydown.cnqf input.cnqf', function () {
			if (!applying) {
				stop();
			}
		});

		timer = setInterval(function () {
			if (Date.now() > deadline) {
				stop();
				return;
			}
			if (String($('#frequency').val() || '') !== target) {
				reassert();
			}
		}, 250);
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

	function applyTemplate(name, opts) {
		opts = opts || {};
		getJSON(API + 'templates').done(function (all) {
			var fields = all && all[name];
			if (!fields) {
				if (!opts.silent) {
					notify('模板不存在或已被删除', false);
				}
				return;
			}
			apply(fields);
			if (!opts.silent) {
				notify('已应用模板「' + name + '」（呼号未改动）');
			}
		}).fail(function () {
			if (!opts.silent) {
				notify('模板读取失败', false);
			}
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

	// ------------------------------------------------------------------ env bar + scene (M0/M1)

	function cnUtcHHMM() {
		var d = new Date();
		var p = function (n) { return String(n).padStart(2, '0'); };
		return p(d.getUTCHours()) + ':' + p(d.getUTCMinutes());
	}

	// The bar renders from the LIVE form values (never a copied snapshot), so
	// "what the bar says" is always "what will be saved".
	function renderEnvBar() {
		var $bar = $('#cn_env_bar');
		if (!$bar.length) {
			return;
		}
		var mode = $('#mode').val() || '-';
		var freq = parseFloat($('#frequency').val());
		var band = $('#band').val() || '-';
		var $opt = $('#stationProfile option:selected');
		var call = String($opt.data('call') || '');
		var city = String($opt.data('city') || '');
		var grid = String($opt.data('grid') || '');
		var pwr = String($opt.data('power') || '');
		var left = [cnUtcHHMM() + ' UTC', mode, (isFinite(freq) && freq > 0) ? (freq / 1000000).toFixed(3) + ' MHz' : '-', band].join(' · ');
		var right = [call, city, grid, pwr ? pwr + 'W' : ''].filter(Boolean).join(' · ');
		$bar.text(left + (right ? '　|　' + right : ''));
	}

	function bindEnvBar() {
		if (!$('#cn_env_bar').length) {
			return;
		}
		renderEnvBar();
		['#mode', '#band', '#frequency', '#stationProfile'].forEach(function (sel) {
			$(document).on('change.cnenv', sel, renderEnvBar);
		});
	}

	function renderSceneSelect() {
		var $sel = $('#cn_scene_select');
		if (!$sel.length) {
			return;
		}
		getJSON(API + 'templates').done(function (all) {
			$sel.find('option:not(:first)').remove();
			var names = Object.keys(all || {}).sort();
			names.forEach(function (name) {
				$sel.append($('<option></option>').attr('value', name).text(name));
			});
			getJSON(API + 'active_scene').done(function (r) {
				var name = (r && r.name) || '';
				if (name && !all[name]) {
					name = '';   // marker survives a deleted template - ignore it
				}
				$sel.val(name);
				if (name) {
					apply(all[name], { silent: true });   // open page -> scene already on
				}
				renderEnvBar();
			});
		});
	}

	function saveActiveScene(name) {
		$.ajax({
			url: API + 'active_scene_save',
			method: 'POST',
			contentType: 'application/json; charset=utf-8',
			dataType: 'json',
			data: JSON.stringify({ name: name })
		});
	}

	// ------------------------------------------------------------------ mobile page (M2)

	// "now" in Beijing wall-clock, for pre-filling the date/time inputs
	function cnNowBjtParts() {
		var d = new Date(Date.now() + 8 * 3600 * 1000);
		var p = function (n) { return String(n).padStart(2, '0'); };
		return {
			date: d.getUTCFullYear() + '-' + p(d.getUTCMonth() + 1) + '-' + p(d.getUTCDate()),
			time: p(d.getUTCHours()) + ':' + p(d.getUTCMinutes())
		};
	}

	// date "YYYY-MM-DD" + time "HH:MM" in the chosen timezone -> the
	// dd/mm/yy + HH:MM (UTC) pair the save endpoint expects. Handles the
	// midnight rollover (00:30 BJT is 16:30 UTC the day before).
	function cnConvertToUtc(dateStr, timeStr, tz) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(dateStr || ''));
		var t = /^(\d{1,2}):(\d{2})/.exec(String(timeStr || ''));
		if (!m || !t) {
			return null;
		}
		var ms = Date.UTC(+m[1], +m[2] - 1, +m[3], +t[1], +t[2]);
		if (tz === 'BJT') {
			ms -= 8 * 3600 * 1000;
		}
		var d = new Date(ms);
		var p = function (n) { return String(n).padStart(2, '0'); };
		return {
			date: p(d.getUTCDate()) + '/' + p(d.getUTCMonth() + 1) + '/' + String(d.getUTCFullYear()).slice(-2),
			time: p(d.getUTCHours()) + ':' + p(d.getUTCMinutes()),
			hint: p(d.getUTCHours()) + ':' + p(d.getUTCMinutes()) + ' UTC / ' + p(d.getUTCDate()) + '-' + p(d.getUTCMonth() + 1)
		};
	}

	function bootMobile() {
		if (!$('#cn_mobile_save').length) {
			return;
		}

		// RST quick buttons: fill-in shortcuts, not a radio group. A hand-typed
		// value stays until the user picks another button or changes mode.
		$(document).on('click', '.cn-rst-btn', function () {
			var v = String($(this).data('rst'));
			var target = String($(this).data('target'));
			$('#' + target).val(v);
			$('.cn-rst-btn[data-target="' + target + '"]').removeClass('active');
			$(this).addClass('active');
		});

		// band -> default frequency (mirrors the desktop band handler)
		$('#band').on('change', function () {
			var cur = $('#frequency').val();
			if (!cur || frequencyToBand(cur) !== $(this).val()) {
				$.get(base_url + 'index.php/qso/band_to_freq/' + $(this).val() + '/' + $('#mode').val(), function (result) {
					$('#frequency').val(result).trigger('change');
				});
			}
		});

		// frequency -> band
		$('#frequency').on('change', function () {
			var b = frequencyToBand($('#frequency').val());
			if (b && $('#band option[value="' + b + '"]').length) {
				$('#band').val(b);
			}
		});

		// MHz display field <-> hidden Hz field
		$('#frequency_mhz').on('input change', function () {
			var v = parseFloat($(this).val());
			$('#frequency').val(isFinite(v) && v > 0 ? String(Math.round(v * 1000000)) : '');
		});
		$('#frequency').on('change', function () {
			var hz = parseFloat($(this).val());
			$('#frequency_mhz').val(isFinite(hz) && hz > 0 ? (hz / 1000000).toFixed(3) : '');
		});

		// mode change -> RST default (same as desktop: switching mode resets)
		$('#mode').on('change', function () {
			setRst($(this).val());
			$('.cn-rst-btn').removeClass('active');
			$('.cn-rst-btn[data-target="rst_sent"][data-rst="' + $('#rst_sent').val() + '"]').addClass('active');
			$('.cn-rst-btn[data-target="rst_rcvd"][data-rst="' + $('#rst_rcvd').val() + '"]').addClass('active');
		});

		// lookup: my recent QSOs with this callsign
		$('#cn_call_lookup').on('click', function () {
			var call = $('#callsign').val().trim();
			var $box = $('#cn_past_box');
			if (!call) {
				notify('先填呼号再查', false);
				return;
			}
			$.getJSON(API + 'past?callsign=' + encodeURIComponent(call)).done(function (r) {
				if (!r.ok || !r.count) {
					$box.removeClass('d-none').html('<span class="badge text-bg-info">首次通联</span>');
					return;
				}
				var html = '<div class="small text-muted mb-1">通联过 ' + r.count + ' 次（最近）</div>';
				r.items.forEach(function (it) {
					html += '<div class="small">' + it.time_on + ' · ' + it.band + ' · ' + it.mode + ' · ' + it.rst_sent + '</div>';
				});
				$box.removeClass('d-none').html(html);
			}).fail(function () {
				notify('查询失败', false);
			});
		});

		// save -> same endpoint as the desktop form
		$('#cn_mobile_save').on('click', function (e) {
			e.preventDefault();
			var call = $('#callsign').val().trim();
			if (!call) {
				notify('请先填写对方呼号', false);
				$('#callsign').addClass('is-invalid').trigger('focus');
				return;
			}
			$('#callsign').removeClass('is-invalid');

			var utc = cnConvertToUtc($('#cn_qso_date').val(), $('#cn_qso_time').val(), $('#cn_tz').val());
			if (!utc) {
				notify('\u65e5\u671f\u6216\u65f6\u95f4\u683c\u5f0f\u4e0d\u5bf9', false);
				return;
			}
			$('#cn_start_date').val(utc.date);
			$('#cn_start_time').val(utc.time);

			var payload = {};
			$('#cn_mobile_form').serializeArray().forEach(function (x) { payload[x.name] = x.value; });

			$.ajax({ url: $('#cn_mobile_form').attr('action'), method: 'POST', data: payload })
				.done(function (res) {
					var ok = true;
					try { ok = JSON.parse(res).message === 'success'; } catch (err) { /* non-json means ok */ }
					if (!ok) {
						notify('保存失败：' + res, false);
						return;
					}
					notify('已保存 ' + call + '（' + cnUtcHHMM() + ' UTC）');
					// continuous logging: clear counterpart fields, keep scene/expand state
					$('#callsign').val('');
					$('#qth').val('');
					setRst($('#mode').val());
					$('.cn-rst-btn').removeClass('active');
					$('.cn-rst-btn[data-target="rst_sent"][data-rst="' + $('#rst_sent').val() + '"]').addClass('active');
					$('.cn-rst-btn[data-target="rst_rcvd"][data-rst="' + $('#rst_rcvd').val() + '"]').addClass('active');
					renderEnvBar();
					$('#callsign').trigger('focus');
				})
				.fail(function () {
					notify('保存失败：网络或服务异常', false);
				});
		});

		// page load: put a sensible default frequency on the bar (band+mode default)
		$('#band').trigger('change');

		// time inputs: prefill "now" in Beijing time; live UTC hint on change
		function refreshUtcHint() {
			var utc = cnConvertToUtc($('#cn_qso_date').val(), $('#cn_qso_time').val(), $('#cn_tz').val());
			$('#cn_utc_hint').text(utc ? ('\u4fdd\u5b58\u65f6\u81ea\u52a8\u8f6c\u6210 UTC\uff1a' + utc.hint)
			                           : '\u4fdd\u5b58\u65f6\u81ea\u52a8\u8f6c\u6210 UTC\uff1b\u9009\u62e9\u300c\u5317\u4eac\u65f6\u95f4\u300d\u586b\u5f53\u5730\u65f6\u523b\u5373\u53ef\u3002');
		}
		var nowBjt = cnNowBjtParts();
		$('#cn_qso_date').val(nowBjt.date);
		$('#cn_qso_time').val(nowBjt.time);
		refreshUtcHint();
		$('#cn_qso_date, #cn_qso_time, #cn_tz').on('input change', refreshUtcHint);

		// mode quick chips (FM / SSB / CW)
		$(document).on('click', '.cn-mode-chip', function () {
			var m = String($(this).data('mode'));
			var $opt = $('#mode option[value="' + m + '"]');
			if (!$opt.length) {
				notify('\u6a21\u5f0f\u5217\u8868\u91cc\u6ca1\u6709 ' + m, false);
				return;
			}
			$('#mode').val(m).trigger('change');
		});

		// RX frequency: mirrors TX until the user types one (simplex default)
		var cnRxTouched = false;
		$('#frequency_rx_mhz').on('input', function () {
			cnRxTouched = true;
			var v = parseFloat($(this).val());
			$('#frequency_rx').val(isFinite(v) && v > 0 ? String(Math.round(v * 1000000)) : '');
		});
		$('#frequency').on('change', function () {
			if (cnRxTouched) {
				return;
			}
			var hz = parseFloat($(this).val());
			var mhz = isFinite(hz) && hz > 0 ? (hz / 1000000).toFixed(3) : '';
			$('#frequency_rx_mhz').val(mhz);
			$('#frequency_rx').val(mhz ? String(Math.round(hz)) : '');
		});

		// my-station info line inside "more fields"
		function renderMyInfo() {
			var $opt = $('#stationProfile option:selected');
			$('#cn_my_call').text($opt.data('call') || '-');
			$('#cn_my_city').text($opt.data('city') || '-');
			$('#cn_my_grid').text($opt.data('grid') || '-');
		}
		renderMyInfo();
		$(document).on('change.cnenv', '#stationProfile', renderMyInfo);
	}

	function boot() {
		$(document).on('click', '#cn_fill_last', function (e) {
			e.preventDefault();
			fromLast();
		});

		$(document).on('change', '#cn_scene_select', function () {
			var name = $(this).val() || '';
			if (!name) {
				saveActiveScene('');
				return;
			}
			getJSON(API + 'templates').done(function (all) {
				var fields = all && all[name];
				if (!fields) {
					notify('场景不存在或已被删除', false);
					return;
				}
				apply(fields, { silent: true });   // scene never touches callsign
				saveActiveScene(name);
				renderEnvBar();
				notify('已切换场景「' + name + '」');
			});
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
		bindEnvBar();
		renderSceneSelect();
		bootMobile();

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
