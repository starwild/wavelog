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
                <label class="form-label small mb-1" for="cn_shift"><?= __('差频') ?></label>
                <select id="cn_shift" class="form-select">
                  <option value="0"><?= __('单工（同频）') ?></option>
                  <option value="-5" selected><?= __('下差 5 MHz') ?></option>
                  <option value="-10"><?= __('下差 10 MHz') ?></option>
                  <option value="-1.6"><?= __('下差 1.6 MHz') ?></option>
                  <option value="5"><?= __('上差 5 MHz') ?></option>
                  <option value="custom"><?= __('自定义') ?></option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small mb-1" for="frequency_mhz"><?= __('发送频率'); ?> (MHz)</label>
                <input type="number" step="0.001" min="0" class="form-control" id="frequency_mhz" inputmode="decimal" value="439.050" placeholder="439.050">
                <input type="hidden" id="frequency" name="freq_display" value="439050000">
              </div>
              <div class="col-6">
                <label class="form-label small mb-1" for="frequency_rx_mhz"><?= __('接收频率'); ?> (MHz)</label>
                <input type="number" step="0.001" min="0" class="form-control" id="frequency_rx_mhz" inputmode="decimal" placeholder="<?= __('随差频联动') ?>">
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
                <label class="form-label small mb-1" for="cn_station_callsign"><?= __('我方呼号'); ?></label>
                <input type="text" class="form-control" id="cn_station_callsign" name="station_callsign" value="" maxlength="32" title="<?= __('默认跟随台站，可按条覆盖；留空用台站默认') ?>">
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

            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label small mb-1" for="my_city"><?= __('我方 QTH'); ?> (<?= __('选填') ?>)</label>
                <input type="text" class="form-control" id="my_city" name="my_city" maxlength="64" placeholder="<?= __('默认用台站位置') ?>">
              </div>
              <div class="col-6">
                <label class="form-label small mb-1" for="qth"><?= __('对方 QTH'); ?> (<?= __('选填') ?>)</label>
                <input type="text" class="form-control" id="qth" name="qth" maxlength="64">
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

        <?php $this->load->view('qso/components/cn_ham_fields', array('cn_sections' => array('equipment', 'env'), 'cn_open' => array('equipment' => true, 'env' => true), 'cn_with_power' => true, 'cn_with_comment' => true)); ?>

        <div class="card previous-qsos mt-2">
          <button type="button" class="card-header d-flex justify-content-between align-items-center w-100 text-start" data-bs-toggle="collapse" data-bs-target="#cn_past_body" aria-expanded="false" aria-controls="cn_past_body" style="cursor: pointer;">
            <h4 class="card-title mb-0" id="timesWorked"><?= __("Previous Contacts"); ?></h4>
            <span aria-hidden="true"><i class="fas fa-up-down"></i></span>
          </button>
          <div class="card-body collapse" id="cn_past_body">
            <div id="qso-last-table"
                 data-past-contacts-url="<?php echo site_url('/qso/component_past_contacts'); ?>"
                 data-auto-refresh="0"
                 aria-live="polite" aria-atomic="true">
            </div>
            <small style="float:right;"><?= __('保存后自动刷新并展开') ?></small>
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
