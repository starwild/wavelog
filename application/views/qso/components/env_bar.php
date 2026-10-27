<?php
/**
 * China HAM Edition - environment summary bar.
 *
 * Shows what the current QSO will be saved with (mode / freq / band and the
 * station attributes) plus the scene selector. Read-only: it renders from the
 * live form values, it never posts anything itself.
 */
$isMobile = ($this->uri->segment(1) === 'qso_mobile');
?>
<div id="cn_env_bar_wrap" class="alert alert-secondary py-2 px-3 mb-3" style="font-size:.9rem;">
  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
    <div id="cn_env_bar" style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
    <div class="d-flex gap-2 align-items-center">
      <select id="cn_scene_select" class="form-select form-select-sm" style="width:auto;max-width:220px;" aria-label="<?= __('场景') ?>">
        <option value=""><?= __('场景：未选择') ?></option>
      </select>
      <?php if (!$isMobile) { ?>
      <a class="btn btn-sm btn-outline-secondary text-nowrap" href="<?= site_url('qso_mobile') ?>" target="_blank" rel="noopener" title="<?= __('手机上单手记通联') ?>"><?= __('手机快速录入') ?></a>
      <?php } ?>
    </div>
  </div>
</div>
<?php if (!$isMobile) { ?>
<style>
@media (max-width: 767.98px) {
  #saveQso { position: sticky; bottom: 8px; z-index: 1030; }
}
</style>
<?php } ?>
