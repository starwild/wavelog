<?php
/**
 * China HAM Edition - mobile quick log view.
 *
 * Field names are IDENTICAL to the desktop form (callsign/band/mode/
 * freq_display/rst_sent/rst_rcvd/...) because it posts to the same
 * POST /index.php/qso?manual=1 endpoint. The "more fields" block is the
 * shared cn_ham_fields partial - never re-declare those inputs here.
 *
 * qso.js is NOT loaded on this page (it is 3600 lines and pulls the map).
 * The small behaviours this page needs (RST defaults, band/freq sync, save,
 * lookup) live in cn_quickfill.js instead.
 */
$manual_mode = 1;
?>
<div class="container" style="max-width: 560px;">

  <form id="cn_mobile_form" method="post" action="<?php echo site_url('qso'); ?>?manual=<?php echo $manual_mode; ?>" autocomplete="off">
    <?php $this->load->view('qso/components/env_bar'); ?>

    <div class="card mb-3">
      <div class="card-body">

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label small mb-1" for="mode"><?= __("Mode"); ?></label>
            <select id="mode" class="form-select" name="mode">
              <?php foreach ($modes as $mode) {
                  if ($mode->submode == null) {
                      printf('<option value="%s">%s</option>', $mode->mode, $mode->mode);
                  } else {
                      printf('<option value="%s">&rArr; %s</option>', $mode->submode, $mode->submode);
                  }
              } ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small mb-1" for="band"><?= __("Band"); ?></label>
            <select id="band" class="form-select" name="band">
              <?php foreach ($bands as $key => $bandgroup) {
                  echo '<optgroup label="' . strtoupper($key) . '">';
                  foreach ($bandgroup as $band) {
                      echo '<option value="' . $band . '">' . $band . '</option>';
                  }
                  echo '</optgroup>';
              } ?>
            </select>
          </div>
          <div class="col-7">
            <label class="form-label small mb-1" for="frequency"><?= __("Frequency"); ?> (MHz)</label>
            <input type="number" step="0.001" min="0" class="form-control" id="frequency_mhz" inputmode="decimal">
            <input type="hidden" id="frequency" name="freq_display" value="">
          </div>
          <div class="col-5">
            <label class="form-label small mb-1" for="stationProfile"><?= __("Station Location"); ?></label>
            <select id="stationProfile" class="form-select" name="station_profile">
              <?php foreach ($stations->result() as $stationrow) { ?>
              <option data-call="<?php echo html_escape($stationrow->station_callsign); ?>" data-city="<?php echo html_escape($stationrow->station_city); ?>" data-grid="<?php echo html_escape($stationrow->station_gridsquare); ?>" data-power="<?php echo html_escape($stationrow->station_power); ?>" value="<?php echo $stationrow->station_id; ?>" <?php if ($active_station_profile == $stationrow->station_id) { echo 'selected="selected"'; } ?>><?php echo html_escape($stationrow->station_profile_name); ?></option>
              <?php } ?>
            </select>
          </div>
        </div>

        <hr class="my-3">

        <div class="mb-2">
          <label class="form-label mb-1" for="callsign" style="font-size:1rem;"><?= __("Callsign"); ?> *</label>
          <div class="input-group input-group-lg">
            <input type="text" class="form-control text-uppercase" id="callsign" name="callsign" placeholder="BG7ABC" maxlength="32" autocomplete="off">
            <button type="button" class="btn btn-outline-secondary" id="cn_call_lookup" title="<?= __('查历史通联') ?>"><i class="fas fa-search"></i></button>
          </div>
          <div id="cn_past_box" class="mt-2 d-none"></div>
        </div>

        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small mb-1" for="rst_sent"><?= __("RST Sent"); ?></label>
            <input type="text" class="form-control" id="rst_sent" name="rst_sent" value="59" maxlength="6">
            <div class="btn-group w-100 mt-1" role="group">
              <?php foreach (array('59', '58', '57', '55') as $i => $v) { ?>
              <button type="button" class="btn btn-sm btn-outline-secondary cn-rst-btn<?php echo $i === 0 ? ' active' : ''; ?>" id="rst_btn_<?php echo $v; ?>" data-target="rst_sent" data-rst="<?php echo $v; ?>"><?php echo $v; ?></button>
              <?php } ?>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label small mb-1" for="rst_rcvd"><?= __("RST Rcvd"); ?></label>
            <input type="text" class="form-control" id="rst_rcvd" name="rst_rcvd" value="59" maxlength="6">
            <div class="btn-group w-100 mt-1" role="group">
              <?php foreach (array('59', '58', '57', '55') as $i => $v) { ?>
              <button type="button" class="btn btn-sm btn-outline-secondary cn-rst-btn<?php echo $i === 0 ? ' active' : ''; ?>" id="rst_rcv_btn_<?php echo $v; ?>" data-target="rst_rcvd" data-rst="<?php echo $v; ?>"><?php echo $v; ?></button>
              <?php } ?>
            </div>
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label small mb-1" for="qth"><?= __("QTH"); ?> (<?= __('选填') ?>)</label>
          <input type="text" class="form-control" id="qth" name="qth" maxlength="64">
        </div>

        <p class="mb-0">
          <a class="small" data-bs-toggle="collapse" href="#cn_mobile_more" role="button" aria-expanded="false" aria-controls="cn_mobile_more" id="cn_mobile_more_toggle"><?= __('更多字段（对方/我的设备 · 中继 · 备注）') ?> ▾</a>
        </p>
      </div>
    </div>

    <div class="collapse mb-3" id="cn_mobile_more">
      <div class="card">
        <div class="card-body">
          <?php $this->load->view('qso/components/cn_ham_fields'); ?>
        </div>
      </div>
    </div>

    <input type="hidden" name="start_date" id="cn_start_date" value="">
    <input type="hidden" name="start_time" id="cn_start_time" value="">

    <div class="pb-4">
      <button type="button" class="btn btn-primary btn-lg w-100" id="cn_mobile_save"><i class="fas fa-save me-1"></i><?= __("Save QSO"); ?></button>
    </div>
  </form>
</div>

<style>
@media (max-width: 575.98px) {
  #cn_mobile_save { position: sticky; bottom: 8px; z-index: 1030; }
  .cn-rst-btn { min-height: 44px; }
}
</style>
