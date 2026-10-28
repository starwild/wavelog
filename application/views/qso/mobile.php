<?php
/**
 * China HAM Edition - quick log view (快速录入).
 *
 * A lighter Post QSO: same save endpoint (POST /index.php/qso?manual=1), same
 * field names, plus the right-hand column the official form has (map +
 * Previous Contacts). qso.js is NOT loaded - the small behaviours this page
 * needs live in cn_quickfill.js.
 *
 * Time: the user may enter Beijing time or UTC (cn_tz selector); it is
 * converted to UTC at save. The date field is interpreted in the SAME
 * timezone as the time field, so 00:30 BJT correctly rolls back to the
 * previous UTC day.
 */
$manual_mode = 1;
?>
<div class="container qso_panel" id="main-content">
  <form id="cn_mobile_form" method="post" action="<?php echo site_url('qso'); ?>?manual=<?php echo $manual_mode; ?>" autocomplete="off">
    <?php $this->load->view('qso/components/env_bar'); ?>

    <div class="row qsopane">
      <div class="col-sm-5">

        <div class="card mb-3">
          <div class="card-body">

            <div class="row g-2">
              <div class="col-4">
                <label class="form-label small mb-1" for="cn_qso_date"><?= __('日期') ?></label>
                <input type="date" class="form-control" id="cn_qso_date">
              </div>
              <div class="col-4">
                <label class="form-label small mb-1" for="cn_qso_time"><?= __('时间') ?></label>
                <input type="time" class="form-control" id="cn_qso_time">
              </div>
              <div class="col-4">
                <label class="form-label small mb-1" for="cn_tz"><?= __('时区') ?></label>
                <select id="cn_tz" class="form-select">
                  <option value="BJT" selected><?= __('北京时间') ?></option>
                  <option value="UTC">UTC</option>
                </select>
              </div>
              <div class="col-12">
                <div class="form-text mb-0" id="cn_utc_hint"><?= __('保存时自动转成 UTC；选择「北京时间」填当地时刻即可。') ?></div>
              </div>
            </div>

            <hr class="my-3">

            <div class="row g-2">
              <div class="col-6">
                <label class="form-label small mb-1" for="mode"><?= __("Mode"); ?></label>
                <select id="mode" class="form-select" name="mode">
                  <?php foreach ($modes as $mode) {
                      if ($mode->submode == null) {
                          printf('<option value="%s"%s>%s</option>', $mode->mode, $mode->mode === 'FM' ? ' selected="selected"' : '', $mode->mode);
                      } else {
                          printf('<option value="%s">&rArr; %s</option>', $mode->submode, $mode->submode);
                      }
                  } ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small mb-1">&nbsp;</label>
                <div class="btn-group w-100" role="group">
                  <?php foreach (array('FM', 'SSB', 'CW') as $m) { ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary cn-mode-chip" data-mode="<?php echo $m; ?>"><?php echo $m; ?></button>
                  <?php } ?>
                </div>
              </div>
              <div class="col-6">
                <label class="form-label small mb-1" for="band"><?= __("Band"); ?></label>
                <select id="band" class="form-select" name="band">
                  <?php foreach ($bands as $key => $bandgroup) {
                      echo '<optgroup label="' . strtoupper($key) . '">';
                      foreach ($bandgroup as $band) {
                          echo '<option value="' . $band . '"' . ($band === '70cm' ? ' selected="selected"' : '') . '>' . $band . '</option>';
                      }
                      echo '</optgroup>';
                  } ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small mb-1" for="frequency_mhz"><?= __("Frequency"); ?> (MHz)</label>
                <input type="number" step="0.001" min="0" class="form-control" id="frequency_mhz" inputmode="decimal" value="439.050" placeholder="439.050">
                <input type="hidden" id="frequency" name="freq_display" value="439050000">
              </div>
              <div class="col-6">
                <label class="form-label small mb-1" for="frequency_rx_mhz"><?= __('收信频率'); ?> (MHz)</label>
                <input type="number" step="0.001" min="0" class="form-control" id="frequency_rx_mhz" inputmode="decimal" placeholder="<?= __('同发射') ?>">
                <input type="hidden" id="frequency_rx" name="freq_display_rx" value="">
              </div>
              <div class="col-6">
                <label class="form-label small mb-1" for="stationProfile"><?= __("Station Location"); ?></label>
                <select id="stationProfile" class="form-select" name="station_profile">
                  <?php foreach ($stations->result() as $stationrow) { ?>
                  <option data-call="<?php echo html_escape($stationrow->station_callsign); ?>" data-city="<?php echo html_escape($stationrow->station_city); ?>" data-grid="<?php echo html_escape($stationrow->station_gridsquare); ?>" data-power="<?php echo html_escape($stationrow->station_power); ?>" value="<?php echo $stationrow->station_id; ?>" <?php if ($active_station_profile == $stationrow->station_id) { echo 'selected="selected"'; } ?>><?php echo html_escape($stationrow->station_profile_name); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small mb-1" for="cn_station_callsign"><?= __('我方台号'); ?></label>
                <input type="text" class="form-control" id="cn_station_callsign" value="" readonly title="<?= __('跟随所选台站，在台站位置里维护') ?>" style="background-color:transparent;">
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
              <a class="small" data-bs-toggle="collapse" href="#cn_mobile_more" role="button" aria-expanded="false" aria-controls="cn_mobile_more" id="cn_mobile_more_toggle"><?= __('更多字段（双方设备 · 天气/海拔 · 中继 · 备注）') ?> ▾</a>
            </p>
          </div>
        </div>

        <div class="collapse mb-3" id="cn_mobile_more">
          <div class="card">
            <div class="card-body">
              <?php $this->load->view('qso/components/cn_ham_fields'); ?>

              <div class="row mt-2">
                <div class="col-sm-6 mb-3">
                  <label for="transmit_power" class="form-label"><?= __('我方功率 (W)') ?></label>
                  <input type="number" step="0.001" inputmode="decimal" class="form-control" name="transmit_power" id="transmit_power">
                </div>
                <div class="col-sm-6 mb-3">
                  <label for="comment" class="form-label"><?= __('备注（对方）') ?></label>
                  <input type="text" class="form-control" name="comment" id="comment" maxlength="255">
                </div>
              </div>

              <div class="small text-muted">
                <?= __('我方') ?>：<span id="cn_my_call"></span> · <span id="cn_my_city"></span> · <span id="cn_my_grid"></span>
                （<?= __('在台站位置里维护') ?>）
              </div>
            </div>
          </div>
        </div>

        <input type="hidden" name="start_date" id="cn_start_date" value="">
        <input type="hidden" name="start_time" id="cn_start_time" value="">

        <div class="pb-4">
          <button type="button" class="btn btn-primary btn-lg w-100" id="cn_mobile_save"><i class="fas fa-save me-1"></i><?= __("Save QSO"); ?></button>
        </div>

      </div>
      <div class="col-sm-7">

        <!-- QSO Map (same look as official Post QSO) -->
        <div class="card qso-map mb-3">
          <div id="cn_map" data-lat="<?php echo html_escape($lat); ?>" data-lng="<?php echo html_escape($lng); ?>" style="width:100%;height:200px;"></div>
        </div>

        <div class="card previous-qsos">
          <div class="card-header"><h4 class="card-title mb-0" id="timesWorked" style="font-size:16px;font-weight:bold;"><?= __("Previous Contacts"); ?></h4></div>
          <div class="card-body">
            <div id="qso-last-table"
                 data-past-contacts-url="<?php echo site_url('/qso/component_past_contacts'); ?>"
                 data-auto-refresh="0"
                 aria-live="polite" aria-atomic="true">
            </div>
            <small style="float:right;"><?= __('保存后自动刷新') ?></small>
          </div>
        </div>

      </div>
    </div>
  </form>
</div>

<?php $this->load->view('qso/components/qso_templates_modal'); ?>

<style>
@media (max-width: 575.98px) {
  #cn_mobile_save { position: sticky; bottom: 8px; z-index: 1030; }
  .cn-rst-btn { min-height: 44px; }
}
</style>
