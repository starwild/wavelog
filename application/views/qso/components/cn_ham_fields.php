<?php
/**
 * China HAM Edition - QSO form fields shared by the "new QSO" page, the quick
 * log page and the edit dialog. Keeping one file means the forms can never
 * drift apart on field names (a mismatch silently drops the value on save).
 *
 * Expects:
 *   $qso           - QSO row object (edit mode) or NULL (new QSO)
 *   $edit_mode     - bool, true when rendered inside the edit dialog
 *   $cn_sections   - optional array subset of ('equipment','uv','env');
 *                    default renders all three
 *   $cn_open       - optional map section => bool, cards start expanded
 *                    (e.g. array('equipment' => true, 'uv' => true))
 *   $cn_with_power - optional bool; render the 我方功率 input inside the
 *                    equipment card. Only pass true when the surrounding form
 *                    has NO transmit_power input of its own (the desktop form
 *                    and the edit dialog both already have one).
 */

if (!isset($qso)) { $qso = null; }
if (!isset($edit_mode)) { $edit_mode = false; }
if (!isset($cn_sections) || !is_array($cn_sections)) {
	$cn_sections = array('equipment', 'uv', 'env');
}
if (!isset($cn_open) || !is_array($cn_open)) { $cn_open = array(); }
if (!isset($cn_with_power)) { $cn_with_power = false; }

$cn_val = function ($column) use ($qso) {
	if ($qso === null || !isset($qso->$column)) { return ''; }
	return htmlspecialchars((string)$qso->$column, ENT_QUOTES, 'UTF-8');
};

$cn_pfx = $edit_mode ? 'edit_' : '';
$cn_suffix = $edit_mode ? '_edit' : '';

// Chinese values in the source string: gettext falls back to the msgid, so this
// renders correctly even before the .po files are regenerated.
$cn_labels = array(
	'station_callsign' => __('我方呼号'),
	'transmit_power'   => __('我方功率 (W)'),
	'my_rig'      => __('我方电台'),
	'my_antenna'  => __('我方天线'),
	'rig'         => __('对方电台'),
	'rig_ant'     => __('对方天线'),
	'rx_pwr'      => __('对方功率 (W)'),
	'cn_rpt'      => __('中继 / 直频'),
	'cn_ctcss'    => __('亚音'),
	'cn_district' => __('区县'),
	'cn_card_no'  => __('卡片编号'),
	'weather'     => __('对方天气'),
	'alt'         => __('对方海拔 (m)'),
	'my_weather'  => __('我方天气'),
	'my_alt'      => __('我方海拔 (m)'),
	'my_comment'  => __('我方备注'),
);

// collapse body class / aria state for a card
$cn_body = function ($section) use ($cn_open) {
	return (!empty($cn_open[$section])) ? 'card-body collapse show' : 'card-body collapse';
};
$cn_expanded = function ($section) use ($cn_open) {
	return (!empty($cn_open[$section])) ? 'true' : 'false';
};
$cn_has = function ($section) use ($cn_sections) {
	return in_array($section, $cn_sections, true);
};
?>

<?php if (!$edit_mode) { ?>

<?php if ($cn_has('equipment')) { ?>
<div class="card mt-3 cn-ham-card">
  <button type="button" class="card-header d-flex justify-content-between align-items-center w-100 text-start" data-bs-toggle="collapse" data-bs-target="#cn-ham-equipment" aria-expanded="<?= $cn_expanded('equipment'); ?>" aria-controls="cn-ham-equipment" style="cursor: pointer;">
    <span class="card-title mb-0"><i class="fas fa-broadcast-tower me-1"></i><?= __('双方设备') ?></span>
    <span aria-hidden="true"><i class="fas fa-up-down"></i></span>
  </button>
  <div class="<?= $cn_body('equipment'); ?>" id="cn-ham-equipment">
    <div class="row">
      <div class="col-sm-6">
        <h6 class="small text-muted border-bottom pb-1 mt-0"><i class="fas fa-house-signal me-1"></i><?= __('我方') ?></h6>
        <div class="mb-3">
          <label for="my_rig" class="form-label"><?= $cn_labels['my_rig']; ?></label>
          <input type="text" class="form-control form-control-sm" name="my_rig" id="my_rig" maxlength="64" value="<?= $cn_val('COL_MY_RIG'); ?>" placeholder="<?= __('例如：FT-5DR') ?>">
        </div>
        <div class="mb-3">
          <label for="my_antenna" class="form-label"><?= $cn_labels['my_antenna']; ?></label>
          <input type="text" class="form-control form-control-sm" name="my_antenna" id="my_antenna" maxlength="64" value="<?= $cn_val('COL_MY_ANTENNA'); ?>" placeholder="<?= __('例如：X-50 玻璃钢') ?>">
        </div>
        <?php if ($cn_with_power) { ?>
        <div class="mb-3">
          <label for="transmit_power" class="form-label"><?= $cn_labels['transmit_power']; ?></label>
          <input type="number" step="0.001" inputmode="decimal" class="form-control form-control-sm" name="transmit_power" id="transmit_power" value="<?= $cn_val('COL_TX_PWR'); ?>">
        </div>
        <?php } ?>
      </div>
      <div class="col-sm-6">
        <h6 class="small text-muted border-bottom pb-1 mt-0"><i class="fas fa-tower-broadcast me-1"></i><?= __('对方') ?></h6>
        <div class="mb-3">
          <label for="rig" class="form-label"><?= $cn_labels['rig']; ?></label>
          <input type="text" class="form-control form-control-sm" name="rig" id="rig" maxlength="64" value="<?= $cn_val('COL_RIG'); ?>" placeholder="<?= __('例如：UV-5R') ?>">
        </div>
        <div class="mb-3">
          <label for="rig_ant" class="form-label"><?= $cn_labels['rig_ant']; ?></label>
          <input type="text" class="form-control form-control-sm" name="rig_ant" id="rig_ant" maxlength="64" value="<?= $cn_val('COL_RIG_ANT'); ?>" placeholder="<?= __('例如：GP-9 / 车载苗子') ?>">
        </div>
        <div class="mb-3">
          <label for="rx_pwr" class="form-label"><?= $cn_labels['rx_pwr']; ?></label>
          <input type="number" step="0.001" inputmode="decimal" class="form-control form-control-sm" name="rx_pwr" id="rx_pwr" value="<?= $cn_val('COL_RX_PWR'); ?>">
        </div>
      </div>
    </div>
    <small class="form-text text-muted"><?= $cn_with_power ? __('左右两列同类对齐：电台对电台、天线对天线、功率对功率。区县、中继等字段导出为 ADIF APP_WAVELOG_* 自定义字段。') : __('左右两列同类对齐。我方功率在上方「发射功率」里填。区县、中继等字段导出为 ADIF APP_WAVELOG_* 自定义字段。') ?></small>
  </div>
</div>
<?php } ?>

<?php if ($cn_has('uv')) { ?>
<div class="card mt-2 cn-ham-card">
  <button type="button" class="card-header d-flex justify-content-between align-items-center w-100 text-start" data-bs-toggle="collapse" data-bs-target="#cn-ham-uv" aria-expanded="<?= $cn_expanded('uv'); ?>" aria-controls="cn-ham-uv" style="cursor: pointer;">
    <span class="card-title mb-0"><i class="fas fa-tower-cell me-1"></i><?= __('UV 通联') ?></span>
    <span aria-hidden="true"><i class="fas fa-up-down"></i></span>
  </button>
  <div class="<?= $cn_body('uv'); ?>" id="cn-ham-uv">
    <div class="row">
      <div class="col-sm-6 mb-3">
        <label for="cn_rpt" class="form-label"><?= $cn_labels['cn_rpt']; ?></label>
        <input type="text" class="form-control form-control-sm" name="cn_rpt" id="cn_rpt" maxlength="64" value="<?= $cn_val('COL_RPT'); ?>" placeholder="439.050 -5">
      </div>
      <div class="col-sm-6 mb-3">
        <label for="cn_ctcss" class="form-label"><?= $cn_labels['cn_ctcss']; ?></label>
        <input type="text" class="form-control form-control-sm" name="cn_ctcss" id="cn_ctcss" maxlength="16" value="<?= $cn_val('COL_CTCSS'); ?>" placeholder="T88.5">
      </div>
      <div class="col-sm-6 mb-3">
        <label for="cn_district" class="form-label"><?= $cn_labels['cn_district']; ?></label>
        <input type="text" class="form-control form-control-sm" name="cn_district" id="cn_district" maxlength="64" value="<?= $cn_val('COL_CN_DISTRICT'); ?>" placeholder="<?= __('例如：朝阳区') ?>">
      </div>
      <div class="col-sm-6 mb-3">
        <label for="cn_card_no" class="form-label"><?= $cn_labels['cn_card_no']; ?></label>
        <input type="text" class="form-control form-control-sm" name="cn_card_no" id="cn_card_no" maxlength="64" value="<?= $cn_val('COL_CARD_NO'); ?>" placeholder="C-0001">
      </div>
    </div>
    <small class="form-text text-muted"><?= __('中继写「下行频率 差频」，如 439.050 -5；亚音写 T88.5 / TSQ 88.5 / DCS 023。') ?></small>
  </div>
</div>
<?php } ?>

<?php if ($cn_has('env')) { ?>
<div class="card mt-2 cn-ham-card">
  <button type="button" class="card-header d-flex justify-content-between align-items-center w-100 text-start" data-bs-toggle="collapse" data-bs-target="#cn-ham-env" aria-expanded="<?= $cn_expanded('env'); ?>" aria-controls="cn-ham-env" style="cursor: pointer;">
    <span class="card-title mb-0"><i class="fas fa-cloud-sun me-1"></i><?= __('环境与备注（双方）') ?></span>
    <span aria-hidden="true"><i class="fas fa-up-down"></i></span>
  </button>
  <div class="<?= $cn_body('env'); ?>" id="cn-ham-env">
    <div class="row">
      <div class="col-sm-6">
        <h6 class="small text-muted border-bottom pb-1 mt-0"><i class="fas fa-house-signal me-1"></i><?= __('我方') ?></h6>
        <div class="mb-3">
          <label for="my_weather" class="form-label"><?= $cn_labels['my_weather']; ?></label>
          <input type="text" class="form-control form-control-sm" name="my_weather" id="my_weather" maxlength="64" value="<?= $cn_val('COL_MY_WEATHER'); ?>" placeholder="例如：多云 · 3 级风">
        </div>
        <div class="mb-3">
          <label for="my_alt" class="form-label"><?= $cn_labels['my_alt']; ?></label>
          <input type="text" class="form-control form-control-sm" name="my_alt" id="my_alt" maxlength="16" value="<?= $cn_val('COL_MY_ALT'); ?>" placeholder="例如：1200（山顶）">
        </div>
        <div class="mb-3">
          <label for="my_comment" class="form-label"><?= $cn_labels['my_comment']; ?></label>
          <input type="text" class="form-control form-control-sm" name="my_comment" id="my_comment" maxlength="255" value="<?= $cn_val('COL_MY_COMMENT'); ?>" placeholder="写给自己看的：设备状态、信号变化等">
        </div>
      </div>
      <div class="col-sm-6">
        <h6 class="small text-muted border-bottom pb-1 mt-0"><i class="fas fa-tower-broadcast me-1"></i><?= __('对方') ?></h6>
        <div class="mb-3">
          <label for="weather" class="form-label"><?= $cn_labels['weather']; ?></label>
          <input type="text" class="form-control form-control-sm" name="weather" id="weather" maxlength="64" value="<?= $cn_val('COL_WEATHER'); ?>" placeholder="例如：晴 · 微风">
        </div>
        <div class="mb-3">
          <label for="alt" class="form-label"><?= $cn_labels['alt']; ?></label>
          <input type="text" class="form-control form-control-sm" name="alt" id="alt" maxlength="16" value="<?= $cn_val('COL_ALT'); ?>" placeholder="例如：850">
        </div>
      </div>
    </div>
    <small class="form-text text-muted"><?= __('左列我方、右列对方，按场次记录。对方备注写在「备注（对方）」里。') ?></small>
  </div>
</div>
<?php } ?>

<?php } else { ?>

<div class="card mt-3 cn-ham-card">
  <button type="button" class="card-header d-flex justify-content-between align-items-center w-100 text-start" data-bs-toggle="collapse" data-bs-target="#cn-ham-equipment-edit" aria-expanded="false" aria-controls="cn-ham-equipment-edit" style="cursor: pointer;">
    <span class="card-title mb-0"><i class="fas fa-broadcast-tower me-1"></i><?= __('双方设备') ?></span>
    <span aria-hidden="true"><i class="fas fa-up-down"></i></span>
  </button>
  <div class="card-body collapse" id="cn-ham-equipment-edit">
    <div class="row">
      <div class="col-sm-6 mb-2">
        <label for="station_callsign_edit" class="form-label"><?= $cn_labels['station_callsign']; ?>（<?= __('本条覆盖，留空按台站') ?>）</label>
        <input type="text" class="form-control" name="station_callsign" id="station_callsign_edit" maxlength="32" value="<?= $cn_val('COL_STATION_CALLSIGN'); ?>">
      </div>
      <div class="col-sm-6 mb-2">
        <label for="my_city_edit" class="form-label"><?= __('我方 QTH'); ?>（<?= __('本条覆盖，留空按台站') ?>）</label>
        <input type="text" class="form-control" name="my_city" id="my_city_edit" maxlength="64" value="<?= $cn_val('COL_MY_CITY'); ?>">
      </div>
    </div>
    <div class="row">
      <div class="col-sm-6">
        <h6 class="small text-muted border-bottom pb-1 mt-0"><i class="fas fa-house-signal me-1"></i><?= __('我方') ?></h6>
        <div class="mb-3">
          <label for="my_rig_edit" class="form-label"><?= $cn_labels['my_rig']; ?></label>
          <input type="text" class="form-control" name="my_rig" id="my_rig_edit" maxlength="64" value="<?= $cn_val('COL_MY_RIG'); ?>">
        </div>
        <div class="mb-3">
          <label for="my_antenna_edit" class="form-label"><?= $cn_labels['my_antenna']; ?></label>
          <input type="text" class="form-control" name="my_antenna" id="my_antenna_edit" maxlength="64" value="<?= $cn_val('COL_MY_ANTENNA'); ?>">
        </div>
      </div>
      <div class="col-sm-6">
        <h6 class="small text-muted border-bottom pb-1 mt-0"><i class="fas fa-tower-broadcast me-1"></i><?= __('对方') ?></h6>
        <div class="mb-3">
          <label for="rig_edit" class="form-label"><?= $cn_labels['rig']; ?></label>
          <input type="text" class="form-control" name="rig" id="rig_edit" maxlength="64" value="<?= $cn_val('COL_RIG'); ?>">
        </div>
        <div class="mb-3">
          <label for="rig_ant_edit" class="form-label"><?= $cn_labels['rig_ant']; ?></label>
          <input type="text" class="form-control" name="rig_ant" id="rig_ant_edit" maxlength="64" value="<?= $cn_val('COL_RIG_ANT'); ?>">
        </div>
        <div class="mb-3">
          <label for="rx_pwr_edit" class="form-label"><?= $cn_labels['rx_pwr']; ?></label>
          <input type="number" step="0.001" inputmode="decimal" class="form-control" name="rx_pwr" id="rx_pwr_edit" value="<?= $cn_val('COL_RX_PWR'); ?>">
        </div>
      </div>
    </div>
    <small class="form-text text-muted"><?= __('左右两列同类对齐。我方功率在上方「发射功率」里填。') ?></small>
  </div>
</div>

<div class="card mt-2 cn-ham-card">
  <button type="button" class="card-header d-flex justify-content-between align-items-center w-100 text-start" data-bs-toggle="collapse" data-bs-target="#cn-ham-uv-edit" aria-expanded="false" aria-controls="cn-ham-uv-edit" style="cursor: pointer;">
    <span class="card-title mb-0"><i class="fas fa-tower-cell me-1"></i><?= __('UV 通联') ?></span>
    <span aria-hidden="true"><i class="fas fa-up-down"></i></span>
  </button>
  <div class="card-body collapse" id="cn-ham-uv-edit">
    <div class="row">
      <div class="col-sm-6 mb-3">
        <label for="cn_rpt_edit" class="form-label"><?= $cn_labels['cn_rpt']; ?></label>
        <input type="text" class="form-control" name="cn_rpt" id="cn_rpt_edit" maxlength="64" value="<?= $cn_val('COL_RPT'); ?>">
      </div>
      <div class="col-sm-6 mb-3">
        <label for="cn_ctcss_edit" class="form-label"><?= $cn_labels['cn_ctcss']; ?></label>
        <input type="text" class="form-control" name="cn_ctcss" id="cn_ctcss_edit" maxlength="16" value="<?= $cn_val('COL_CTCSS'); ?>">
      </div>
      <div class="col-sm-6 mb-3">
        <label for="cn_district_edit" class="form-label"><?= $cn_labels['cn_district']; ?></label>
        <input type="text" class="form-control" name="cn_district" id="cn_district_edit" maxlength="64" value="<?= $cn_val('COL_CN_DISTRICT'); ?>">
      </div>
      <div class="col-sm-6 mb-3">
        <label for="cn_card_no_edit" class="form-label"><?= $cn_labels['cn_card_no']; ?></label>
        <input type="text" class="form-control" name="cn_card_no" id="cn_card_no_edit" maxlength="64" value="<?= $cn_val('COL_CARD_NO'); ?>">
      </div>
    </div>
  </div>
</div>

<div class="card mt-2 cn-ham-card">
  <button type="button" class="card-header d-flex justify-content-between align-items-center w-100 text-start" data-bs-toggle="collapse" data-bs-target="#cn-ham-env_edit" aria-expanded="false" aria-controls="cn-ham-env_edit" style="cursor: pointer;">
    <span class="card-title mb-0"><i class="fas fa-cloud-sun me-1"></i><?= __('环境与备注（双方）') ?></span>
    <span aria-hidden="true"><i class="fas fa-up-down"></i></span>
  </button>
  <div class="card-body collapse" id="cn-ham-env_edit">
    <div class="row">
      <div class="col-sm-6">
        <h6 class="small text-muted border-bottom pb-1 mt-0"><i class="fas fa-house-signal me-1"></i><?= __('我方') ?></h6>
        <div class="mb-3">
          <label for="my_weather_edit" class="form-label"><?= $cn_labels['my_weather']; ?></label>
          <input type="text" class="form-control" name="my_weather" id="my_weather_edit" maxlength="64" value="<?= $cn_val('COL_MY_WEATHER'); ?>" placeholder="例如：多云 · 3 级风">
        </div>
        <div class="mb-3">
          <label for="my_alt_edit" class="form-label"><?= $cn_labels['my_alt']; ?></label>
          <input type="text" class="form-control" name="my_alt" id="my_alt_edit" maxlength="16" value="<?= $cn_val('COL_MY_ALT'); ?>" placeholder="例如：1200（山顶）">
        </div>
        <div class="mb-3">
          <label for="my_comment_edit" class="form-label"><?= $cn_labels['my_comment']; ?></label>
          <input type="text" class="form-control" name="my_comment" id="my_comment_edit" maxlength="255" value="<?= $cn_val('COL_MY_COMMENT'); ?>" placeholder="写给自己看的：设备状态、信号变化等">
        </div>
      </div>
      <div class="col-sm-6">
        <h6 class="small text-muted border-bottom pb-1 mt-0"><i class="fas fa-tower-broadcast me-1"></i><?= __('对方') ?></h6>
        <div class="mb-3">
          <label for="weather_edit" class="form-label"><?= $cn_labels['weather']; ?></label>
          <input type="text" class="form-control" name="weather" id="weather_edit" maxlength="64" value="<?= $cn_val('COL_WEATHER'); ?>" placeholder="例如：晴 · 微风">
        </div>
        <div class="mb-3">
          <label for="alt_edit" class="form-label"><?= $cn_labels['alt']; ?></label>
          <input type="text" class="form-control" name="alt" id="alt_edit" maxlength="16" value="<?= $cn_val('COL_ALT'); ?>" placeholder="例如：850">
        </div>
      </div>
    </div>
    <small class="form-text text-muted"><?= __('左列我方、右列对方，按场次记录。对方备注写上方的「备注」里。') ?></small>
  </div>
</div>
<?php } ?>
