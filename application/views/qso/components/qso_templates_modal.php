<?php
/**
 * China HAM Edition - QSO template manager.
 *
 * One modal does everything: list (apply / delete), save the current form
 * under a name, and optionally import the three built-in presets.
 * Storage is user_options (option_type = qso_template), so there is no schema.
 */
?>
<div class="modal fade" id="cnTplModal" tabindex="-1" aria-labelledby="cnTplModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cnTplModalLabel"><i class="fas fa-table-list me-1"></i><?= __('通联模板') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __("Close"); ?>"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted">
          <?= __('模板只保存「波段/频率/模式/中继/亚音/我方设备」这些环境参数，不会保存呼号、信号报告和对方信息。') ?>
        </p>

        <div class="row g-2 align-items-end mb-3">
          <div class="col-sm-8">
            <label for="cnTplName" class="form-label"><?= __('模板名') ?></label>
            <input type="text" class="form-control" id="cnTplName" maxlength="45" placeholder="<?= __('例如：439.050 中继点名') ?>">
            <div class="form-text"><?= __('最多 45 个字符。') ?></div>
          </div>
          <div class="col-sm-4">
            <button type="button" class="btn btn-primary w-100" id="cnTplSaveBtn">
              <i class="fas fa-floppy-disk me-1"></i><?= __('保存当前表单') ?>
            </button>
          </div>
        </div>

        <hr>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <strong><?= __('已有模板') ?></strong>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="cnTplPreset">
            <i class="fas fa-download me-1"></i><?= __('导入示例模板') ?>
          </button>
        </div>
        <div id="cnTplList" style="max-height: 40vh; overflow-y: auto;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __("Close"); ?></button>
      </div>
    </div>
  </div>
</div>
