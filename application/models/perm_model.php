<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Perm_model extends CI_Model {
	public function fetch($pid, $version = 0) {
		$where = array('pid' => $pid);

		if(!empty($version)) {
			$where['version'] = $version;
		}

		return $this->_fetch($where);
	}

	public function insert($data) {
		if(empty($data['perm'])) {
			return FALSE;
		}

		$data['pid'] = $this->_get_new_id();
		$data['version'] = 1;
		$data['status'] = 1;

		$this->db->insert('cmd_perm', $data);
		if($this->db->affected_rows() == 1) {
			return (object)$data;
		}

		return FALSE;

	}

    public function clone_perm($pid, $version) {
        $perm = $this->fetch($pid, $version);
        return $this->insert((array)$perm);
    }

	public function edit($pid, $version, $data) {
		if(isset($data['perm']) && empty($data['perm'])) {
			return FALSE;
		}

		// Extract the identifiers from the data array and unset them
		// The remainder is the field being updated
		$where = array(
			'pid'     => $pid,
			'version' => $version,
			'status'  => 1,
		);

		$perm = $this->_fetch($where);
		if(empty($perm)) {
			return FALSE;
		}

		$perm->version = $this->_get_latest_version($perm->pid);
		foreach($data as $field => $value) {
			if(!$this->db->field_exists($field, 'cmd_perm') || $field == 'status') {
				return FALSE;
			}
			$perm->{$field} = $value;
		}

		$this->db->insert('cmd_perm', $perm);

		if($this->db->affected_rows() == 1) {
			return $perm;
		}

		return FALSE;
	}

	public function delete($pid, $version) {
		$perm = $this->_fetch(array('pid' => $pid, 'version' => $version));
		if(empty($perm)) {
			return FALSE;
		}

		$this->db->where(array('pid' => $pid, 'version' => $version))
					->update('cmd_perm', array('status' => 0));
		if($this->db->affected_rows() == 1) {
			$perm->status = 0;
			return $perm;
		}

		return FALSE;
	}

	public function restore($pid, $version) {
		$perm = $this->_fetch(array('pid' => $pid, 'version' => $version), TRUE);
		if(empty($perm) || $perm->status == 1) {
			return FALSE;
		}

		$perm->status = 1;
		$perm->version = $this->_get_latest_version($perm->pid);
		$this->db->insert('cmd_perm', $perm);
		if($this->db->affected_rows() == 1) {
			return $perm;
		}

		return FALSE;
	}

	public function revert($pid, $version) {
		$perm = $this->_fetch(array('pid' => $pid, 'version' => $version));
		if(empty($perm)) {
			echo 'empty';
			return FALSE;
		}

		$perm->version = $this->_get_latest_version($perm->pid);
		$this->db->insert('cmd_perm', $perm);
		if($this->db->affected_rows() == 1) {
			return $perm;
		}

		return FALSE;
	}

	protected function _fetch($where, $include_deleted = FALSE) {
		$perm = $this->db->order_by('version DESC')
						->limit(1)
						->get_where('cmd_perm', $where)
						->row();

		if(!empty($perm) && $perm->status == 0 && !$include_deleted) {
			return FALSE;
		}

		return $perm;
	}

	protected function _get_new_id() {
		$query = $this->db->select_max('pid')
						->get('cmd_perm')
						->row();

		return $query->pid + 1;
	}

	protected function _get_latest_version($pid) {
		$query = $this->db->select_max('version')
							->where(array('pid' => $pid))
							->get('cmd_perm')
							->row();
		return $query->version + 1;
	}
}