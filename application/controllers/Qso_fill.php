<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * China HAM Edition - quick fill endpoints.
 *
 * Two features share these endpoints:
 *   - "fill in my previous QSO"  -> last()
 *   - "QSO templates"            -> templates() / template_save() / template_delete()
 *
 * The whole point of this controller is the $fillable whitelist: it is the one
 * and only place that decides which fields may be pushed into a new QSO form.
 * Everything the other station owns (callsign, RST, QTH, grid, their antenna,
 * their district, ...) is absent from it on purpose, so the browser can never
 * copy them even if the frontend code is wrong.
 */
class Qso_fill extends CI_Controller {

	/**
	 * payload key => [DB column, is numeric]
	 *
	 * Add a field here and it becomes available to BOTH features. Nothing else
	 * needs to change on the server side.
	 *
	 * Note on frequency: COL_FREQ / COL_FREQ_RX already hold **Hz**
	 * (Logbook_model::parse_frequency() turns "14.2M" into 14200000 and stores
	 * that as is), and the form input #frequency('name=freq_display') also
	 * speaks Hz. So the value is passed through untouched - converting it
	 * would inflate it by a factor of a million.
	 */
	protected $fillable = array(
		// link parameters
		'station_profile' => array('station_id', false),
		'band'            => array('COL_BAND', false),
		'band_rx'         => array('COL_BAND_RX', false),
		'freq_display'    => array('COL_FREQ', true),
		'freq_display_rx' => array('COL_FREQ_RX', true),
		'mode'            => array('COL_MODE', false),
		'prop_mode'       => array('COL_PROP_MODE', false),
		'sat_name'        => array('COL_SAT_NAME', false),
		'sat_mode'        => array('COL_SAT_MODE', false),
		'cn_rpt'          => array('COL_RPT', false),
		'cn_ctcss'        => array('COL_CTCSS', false),
		// my own gear
		'transmit_power'  => array('COL_TX_PWR', false),
		'my_rig'          => array('COL_MY_RIG', false),
		'my_antenna'      => array('COL_MY_ANTENNA', false),
	);

	private $template_type = 'qso_template';
	private $template_limit = 100;      // decision D5
	private $template_name_max = 45;    // user_options.option_name is varchar(45)

	public function __construct() {
		parent::__construct();
		$this->load->model('user_model');
		$this->load->model('logbook_model');
		$this->load->model('stations');
		$this->load->model('user_options_model');
	}

	// ------------------------------------------------------------------ endpoints

	/** GET /index.php/qso_fill/last[?callsign=..][?id=..] */
	public function last() {
		if (!$this->require_login()) {
			return;
		}

		$id = $this->input->get('id', TRUE);
		$callsign = $this->input->get('callsign', TRUE);

		if ($id !== null && $id !== '' && !$this->logbook_model->check_qso_is_accessible((int) $id)) {
			$this->json_out(403, array('ok' => false, 'error' => 'qso_not_accessible'));
			return;
		}

		$station_ids = $this->stations->all_station_ids_of_user();
		$row = $this->logbook_model->get_last_qso($station_ids, $callsign, $id);

		if ($row === null) {
			$this->json_out(200, array('ok' => false, 'error' => 'no_previous_qso'));
			return;
		}

		$this->json_out(200, array(
			'ok' => true,
			'source' => array(
				'id' => (int) $row->COL_PRIMARY_KEY,
				'time_on' => $row->COL_TIME_ON,
				// only for the toast text - never part of `fields`
				'callsign' => $row->COL_CALL,
			),
			'fields' => $this->project($row),
		));
	}

	/** GET /index.php/qso_fill/templates */
	public function templates() {
		if (!$this->require_login()) {
			return;
		}

		$rows = $this->user_options_model->get_options($this->template_type)->result();
		$out = array();
		foreach ($rows as $r) {
			// filter on read too: a stale row with a retired key must not resurface
			if (!isset($this->fillable[$r->option_key])) {
				continue;
			}
			$out[$r->option_name][$r->option_key] = $r->option_value;
		}
		$this->json_out(200, $out);
	}

	/** POST /index.php/qso_fill/template_save  {"name":"..","fields":{..},"overwrite":0|1} */
	public function template_save() {
		if (!$this->require_login()) {
			return;
		}

		$body = $this->json_body();
		if ($body === null) {
			$this->json_out(400, array('ok' => false, 'error' => 'bad_request'));
			return;
		}

		$name = trim((string) ($body['name'] ?? ''));
		if ($name === '' || mb_strlen($name, 'UTF-8') > $this->template_name_max) {
			$this->json_out(400, array('ok' => false, 'error' => 'template_name_invalid'));
			return;
		}

		$raw = is_array($body['fields'] ?? null) ? $body['fields'] : array();
		$clean = array();
		foreach ($raw as $key => $value) {
			if (!isset($this->fillable[$key])) {
				log_message('debug', 'qso_fill: dropped non whitelisted template key "' . $key . '"');
				continue;
			}
			if ($value === null) {
				continue;
			}
			$clean[$key] = (string) $value;
		}
		if (empty($clean)) {
			$this->json_out(400, array('ok' => false, 'error' => 'template_empty'));
			return;
		}

		$exists = $this->user_options_model
			->get_options($this->template_type, array('option_name' => $name))
			->num_rows() > 0;

		if ($exists && empty($body['overwrite'])) {
			$this->json_out(409, array('ok' => false, 'error' => 'template_name_exists'));
			return;
		}
		if (!$exists && $this->template_count() >= $this->template_limit) {
			$this->json_out(409, array('ok' => false, 'error' => 'template_limit_reached'));
			return;
		}

		// remove first: set_option() only upserts the submitted keys, stale ones would survive
		$this->user_options_model->del_option($this->template_type, $name);
		$this->user_options_model->set_option($this->template_type, $name, $clean);

		$this->json_out(200, array('ok' => true, 'name' => $name, 'fields' => $clean));
	}

	/** POST /index.php/qso_fill/template_delete  {"name":".."} */
	public function template_delete() {
		if (!$this->require_login()) {
			return;
		}

		$body = $this->json_body();
		if ($body === null) {
			$this->json_out(400, array('ok' => false, 'error' => 'bad_request'));
			return;
		}

		$name = trim((string) ($body['name'] ?? ''));
		if ($name === '') {
			$this->json_out(400, array('ok' => false, 'error' => 'template_name_invalid'));
			return;
		}

		$exists = $this->user_options_model
			->get_options($this->template_type, array('option_name' => $name))
			->num_rows() > 0;
		if (!$exists) {
			$this->json_out(404, array('ok' => false, 'error' => 'template_not_found'));
			return;
		}

		$this->user_options_model->del_option($this->template_type, $name);
		$this->json_out(200, array('ok' => true));
	}

	// ------------------------------------------------------------------ helpers

	/**
	 * DB row -> payload, restricted to $fillable and to non empty values.
	 *
	 * Everything is passed through as a string; frequencies are already Hz in
	 * both the DB and the form, so there is nothing to convert.
	 */
	private function project($row) {
		$out = array();
		foreach ($this->fillable as $key => $spec) {
			list($column, $is_numeric) = $spec;
			if (!isset($row->$column)) {
				continue;
			}
			$value = $row->$column;
			if ($value === null || $value === '') {
				continue;
			}
			if ($is_numeric) {
				if (!is_numeric($value) || (float) $value <= 0) {
					continue;
				}
				$value = (string) (int) round((float) $value);
			} else {
				$value = (string) $value;
			}
			$out[$key] = $value;
		}
		return $out;
	}

	private function template_count() {
		$rows = $this->user_options_model->get_options($this->template_type)->result();
		$names = array();
		foreach ($rows as $r) {
			$names[$r->option_name] = true;
		}
		return count($names);
	}

	private function require_login() {
		if (!$this->user_model->authorize(2)) {
			$this->json_out(401, array('ok' => false, 'error' => 'not_authenticated'));
			return false;
		}
		return true;
	}

	private function json_body() {
		$raw = file_get_contents('php://input');
		if ($raw === false || trim($raw) === '') {
			return null;
		}
		$decoded = json_decode($raw, true);
		return is_array($decoded) ? $decoded : null;
	}

	private function json_out($status, $payload) {
		$this->output
			->set_status_header($status)
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($payload));
	}
}
