<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * China HAM Edition - mobile quick log page.
 *
 * A one-handed logging screen (/index.php/qso_mobile). It posts to the SAME
 * endpoint as the desktop form (POST /index.php/qso?manual=1) and reuses the
 * cn_ham_fields partial, so there is exactly one save path and one set of
 * field names.
 *
 * Deliberately NOT reusing Qso::index(): that drags in the map, the radio
 * websocket, SAT/SOTA/IOTA tabs and the 3600-line qso.js. This page is meant
 * to work on a phone with one thumb.
 */
class Qso_mobile extends CI_Controller {

	public function index() {
		if (!$this->user_model->authorize(2)) {
			$this->session->set_flashdata('error', __("You're not allowed to do that!"));
			redirect('dashboard');
		}

		$this->load->model('stations');
		$this->load->model('usermodes');
		$this->load->model('bands');

		$data['page_title'] = __("Quick Log");
		$data['modes'] = $this->usermodes->active();
		$data['bands'] = $this->bands->get_user_bands_for_qso_entry();
		$data['stations'] = $this->stations->all_of_user();
		$data['active_station_profile'] = $this->stations->find_active();

		$footerData = array();
		$footerData['scripts'] = array('assets/js/sections/cn_quickfill.js');

		$this->load->view('interface_assets/header', $data);
		$this->load->view('qso/mobile', $data);
		$this->load->view('interface_assets/footer', $footerData);
	}
}
