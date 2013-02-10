<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Log_model extends CI_Model {
	public static $events = array(
		self::EVENT_UNDEFINED,
		self::EVENT_LOGIN,
		self::EVENT_LOGOUT,
		self::EVENT_INSERT,
		self::EVENT_EDIT,
		self::EVENT_DELETE,
		self::EVENT_RESTORE,
		self::EVENT_PUBLISH,
		self::EVENT_UNPUBLISH,
		self::EVENT_ERROR,
		self::EVENT_INVALID_LOGIN,
	);

	const EVENT_UNDEFINED     = 'Undefined';
	const EVENT_LOGIN         = 'Login';
	const EVENT_LOGOUT        = 'Logout';
	const EVENT_INSERT        = 'Insert';
	const EVENT_EDIT          = 'Edit';
	const EVENT_DELETE        = 'Delete';
	const EVENT_RESTORE       = 'Restore';
	const EVENT_PUBLISH       = 'Publish';
	const EVENT_UNPUBLISH     = 'Unpublish';
	const EVENT_ERROR         = 'Error';
	const EVENT_INVALID_LOGIN = 'Invalid Login';

	public function fetch($offset = 0, $limit = 10) {
		return $this->_fetch($offset, $limit);

	}

	public function fetch_all() {
		return $this->fetch(0, FALSE);
	}

	public function insert($event) {
		if(!in_array($event['event'], self::$events)) {
			$event['event'] = self::EVENT_UNDEFINED;
		}
		$event['timestamp'] = time();

		$this->db->insert('cmd_log', $event);
	}

	protected function _fetch($offset, $limit) {
		$log = array();
		$this->db->select('lid, username, timestamp, event, data')
						->from('cmd_log')
						->join('cmd_user', 'cmd_user.uid=cmd_log.uid', 'left')
						->order_by('lid', 'desc');

		if($limit !== FALSE) {
			$this->db->limit($limit, $offset);
		}

		$query = $this->db->get();

		foreach($query->result() as $row) {
			if(empty($row->username)) {
				$row->username = 'Anonymous';
			}
			$log[] = $row;
		}

		return $log;
	}
}