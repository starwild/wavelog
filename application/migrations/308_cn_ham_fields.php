<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
	China HAM Edition - QSO fields

	Adds the fields needed to record what a mainland-China QSO actually contains:
	the other station's antenna, the district, the repeater/frequency pair and
	CTCSS tone used on VHF/UHF, and the paper card serial number.

	All columns are nullable strings and all are written/read by the standard
	four write paths (create_qso / edit / import / API v2).  ADIF export uses
	the APP_WAVELOG_* application defined fields because ADIF 3.x has no
	standard tag for any of them.
*/

class Migration_cn_ham_fields extends CI_Migration {

	public function up() {
		$table_name = $this->config->item('table_name');

		// column => [definition, AFTER anchor]
		$columns = [
			'COL_RIG_ANT'     => ["VARCHAR(255) DEFAULT NULL", 'COL_RIG_INTL'],
			'COL_CN_DISTRICT' => ["VARCHAR(64) DEFAULT NULL", 'COL_NOTES_INTL'],
			'COL_RPT'         => ["VARCHAR(64) DEFAULT NULL", 'COL_CN_DISTRICT'],
			'COL_CTCSS'       => ["VARCHAR(16) DEFAULT NULL", 'COL_RPT'],
			'COL_CARD_NO'     => ["VARCHAR(64) DEFAULT NULL", 'COL_CTCSS'],
		];

		try {
			foreach ($columns as $column => $def) {
				$exists = $this->db->query("SHOW COLUMNS FROM `$table_name` LIKE '$column';")->num_rows() > 0;
				if ($exists) {
					log_message('info', 'Column "' . $column . '" already exists, skipping ALTER TABLE.');
					continue;
				}
				$this->db->query("ALTER TABLE `$table_name` ADD COLUMN $column {$def[0]} AFTER {$def[1]};");
			}
		} catch (Exception $e) {
			log_message('error', 'Migration 308 failed: ' . $e->getMessage());
			return false;
		}
	}

	public function down() {
		$table_name = $this->config->item('table_name');

		$columns = ['COL_RIG_ANT', 'COL_CN_DISTRICT', 'COL_RPT', 'COL_CTCSS', 'COL_CARD_NO'];

		try {
			foreach ($columns as $column) {
				$exists = $this->db->query("SHOW COLUMNS FROM `$table_name` LIKE '$column';")->num_rows() > 0;
				if (!$exists) {
					continue;
				}
				$this->db->query("ALTER TABLE `$table_name` DROP COLUMN $column;");
			}
		} catch (Exception $e) {
			log_message('error', 'Migration 308 rollback failed: ' . $e->getMessage());
			return false;
		}
	}

}
