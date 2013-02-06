<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if(!function_exists('log_in')) {
	function log_in($user) {
		$CI =& get_instance();
		$CI->session->set_userdata('user', $user);

		return TRUE;
	}
}

if(!function_exists('log_out')) {
	function log_out() {
		$CI =& get_instance();
		$CI->session->unset_userdata('user');

		return TRUE;
	}
}

if(!function_exists('is_logged_in')) {
	function is_logged_in() {
		$CI =& get_instance();
		if(isset($CI->session)) {
			return $CI->session->userdata('user');
		}
		return FALSE;
	}
}