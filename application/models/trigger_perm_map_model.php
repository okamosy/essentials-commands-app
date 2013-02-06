<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Trigger_Perm_Map_model extends CI_Model {
	public function fetch(array $trigger) {
		$trigger['status'] = 1;

		$query = $this->db->where($trigger)
							->get('cmd_trigger_perm_map');
		$map = array();
		foreach($query->result() as $row) {
			$map[$row->pid] = $row;
		}

		return $map;
	}

	public function insert($data) {
		// Check if this already exists
		$mapping = $this->db->get_where('cmd_trigger_perm_map', $data);
		if($mapping->num_rows() > 0) {
			return FALSE;
		}

		$this->db->insert('cmd_trigger_perm_map', $data);
		return ($this->db->affected_rows() == 1);
	}

	public function edit($updated, $existing) {
		$this->db->update('cmd_trigger_perm_map', $updated, $existing);
		return ($this->db->affected_rows() == 1);
	}

	public function delete($trigger) {
		$this->db->update('cmd_trigger_perm_map', array('status' => 0), $trigger);
		return ($this->db->affected_rows() > 0);
	}
}