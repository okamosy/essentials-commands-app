<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Trigger_model extends CI_Model {
	public function __construct() {
		parent::__construct();
	}

	public function fetch($tid, $version = 0) {
		$data = array(
			'tid' => $tid,
		);
		if(!empty($version)) {
			$data['version'] = $version;
		}

		return $this->_fetch($data);
	}

	public function insert(array $data) {
		if(empty($data['trigger']) || empty($data['cat'])) {
			return FALSE;
		}

		$data['tid'] = $this->_get_new_id();
		$data['version'] = 1;

		$this->db->insert('cmd_trigger', $data);

		if($this->db->affected_rows() == 1) {
			return (object)$data;
		}

		return FALSE;
	}

    public function clone_trigger($tid, $version) {
        $current_trigger = $this->fetch($tid, $version);

        return $this->insert((array)$current_trigger);
    }

	public function edit($tid, $version, $data) {
		$where = array(
			'tid'     => $tid,
			'version' => $version,
		);
		$trigger = $this->_fetch($where);
		if(empty($trigger)) {
			return FALSE;
		}

		$trigger->version = $this->_get_latest_version($trigger->tid);
		foreach($data as $field => $value) {
			if(!$this->db->field_exists($field, 'cmd_trigger') || $field == 'status') {
				return FALSE;
			}
			if(empty($value) && ($field == 'trigger' || $field == 'cat')) {
				return FALSE;
			}
			$trigger->{$field} = $value;
		}

		$this->db->insert('cmd_trigger', $trigger);
		if($this->db->affected_rows() == 1) {
			return $trigger;
		}

		return FALSE;
	}

	public function delete($tid) {
		$trigger = $this->_fetch(array('tid' => $tid));
		if(empty($trigger)) {
			return FALSE;
		}

		$this->db->where(array('tid' => $trigger->tid, 'version' => $trigger->version))
				->update('cmd_trigger', array('status' => 0));
		if($this->db->affected_rows() == 1) {
			return $trigger;
		}

		return FALSE;
	}

	public function restore($tid) {
		$trigger = $this->_fetch(array('tid' => $tid), TRUE);
		if(empty($trigger) || $trigger->status != 0) {
			return FALSE;
		}

		$trigger->status = 1;
		$trigger->version++;
		$this->db->insert('cmd_trigger', $trigger);
		if($this->db->affected_rows() == 1) {
			return $trigger;
		}

		return FALSE;
	}

	public function revert($tid, $version) {
		$trigger = $this->_fetch(array('tid' => $tid));
		$revert_to = $this->_fetch(array('tid' => $tid, 'version' => $version));
		if(empty($trigger) || empty($revert_to) || $trigger == $revert_to) {
			return FALSE;
		}

		$revert_to->version = $this->_get_latest_version($trigger->tid);
		$this->db->insert('cmd_trigger', $revert_to);
		if($this->db->affected_rows() == 1) {
			return $revert_to;
		}

		return FALSE;
	}

	private function _fetch($where, $include_deleted = FALSE) {
		$trigger = $this->db->order_by('version DESC')
							->where($where)
							->limit(1)
							->get('cmd_trigger')
							->row();

		if(!empty($trigger) && $trigger->status == 0 && !$include_deleted) {
			return FALSE;
		}

		return $trigger;
	}

	private function _get_new_id() {
		$query = $this->db->select_max('tid')
						->get('cmd_trigger')
						->row();
		return $query->tid + 1;
	}

	private function _get_latest_version($tid) {
		$query = $this->db->select_max('version')
							->get_where('cmd_trigger', array('tid' => $tid))
							->row();
		return $query->version + 1;
	}
}