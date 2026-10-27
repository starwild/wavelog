<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
	China HAM Edition - QSO fields, round 2 (weather / altitude / my remarks)

	The logging form records both sides symmetrically: equipment, power, QTH
	(position AND altitude), weather and remarks. Round 1 (308) already added
	the other station's antenna plus the CN-specific card fields; this round
	adds what was still missing for the symmetric layout:

		other station: COL_WEATHER, COL_ALT
		my station:    COL_MY_WEATHER, COL_MY_ALT, COL_MY_COMMENT

	All nullable strings. Written/read by the same four write paths as 308
	(create_qso / edit / import / API v2); ADIF export uses APP_WAVELOG_*
	application defined fields (ADIF 3.x has no tag for any of them).
*/

class Migration_cn_ham_weather_alt extends CI_Migration {

	public function up() {
		$table_name = $this->config->item('table_name');

		$columns = [
			'COL_WEATHER'    => ["VARCHAR(64) DEFAULT NULL", 'COL_CARD_NO'],
			'COL_ALT'        => ["VARCHAR(16) DEFAULT NULL", 'COL_WEATHER'],
			'COL_MY_WEATHER' => ["VARCHAR(64) DEFAULT NULL", 'COL_MY_ANTENNA'],
			'COL_MY_ALT'     => ["VARCHAR(16) DEFAULT NULL", 'COL_MY_WEATHER'],
			'COL_MY_COMMENT' => ["VARCHAR(255) DEFAULT NULL", 'COL_MY_ALT'],
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
			log_message('error', 'Migration 309 failed: ' . $e->getMessage());
			return false;
		}
	}

	public function down() {
		$table_name = $this->config->item('table_name');

		$columns = ['COL_MY_COMMENT', 'COL_MY_ALT', 'COL_MY_WEATHER', 'COL_ALT', 'COL_WEATHER'];

		try {
			foreach ($columns as $column) {
				$exists = $this->db->query("SHOW COLUMNS FROM `$table_name` LIKE '$column';")->num_rows() > 0;
				if (!$exists) {
					continue;
				}
				$this->db->query("ALTER TABLE `$table_name` DROP COLUMN $column;");
			}
		} catch (Exception $e) {
			log_message('error', 'Migration 309 rollback failed: ' . $e->getMessage());
			return false;
		}
	}

}
