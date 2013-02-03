<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Command_model extends CI_Model {
	function get_commands() {
		return $this->db->get('cmd_trigger');
	}

	function insert_command() {
		$data = array(
			'cat'		=> $this->input->post('cat'),
			'trigger'	=> $this->input->post('trigger'),
			'alias'		=> $this->input->post('alias'),
			'desc'		=> $this->input->post('desc'),
			'syntax'	=> $this->input->post('syntax'),
			'instr'		=> $this->input->post('instr'),
		);
		
		$this->db->insert('cmd_trigger', $data);
		
		if(($tid = $this->db->insert_id()) == FALSE) {
			return FALSE;
		}
		
		$perms = array();
		$permList = $this->input->post('perm');
		$pdescList = $this->input->post('pdesc');
		foreach($permList as $index => $perm) {
			$perms[] = array(
				'tid'	=> $tid,
				'perm'	=> $perm,
				'pdesc'	=> $pdescList[$index],
			);
		}
		$this->db->insert_batch('cmd_perms', $perms);
		$data['tid'] = $tid;
		return $data;
	}
	
	function update_command($id, $field, $value) {
		// Check to see the id exists
		$command = $this->db->get_where('cmd_trigger', array('tid' => $id), 1);
		if($command->num_rows()) {
			$this->db->where('tid', $id)->update('cmd_trigger', array($field => $value));
			return ($this->db->affected_rows() == 1) || ($command->row()->{$field} == $value);
		}
		return FALSE;
	}
	
	function delete_command($id) {
		$this->_delete('cmd_perms', 'tid', $id);
		return $this->_delete('cmd_trigger', 'tid', $id);
	}
	
	function get_command_details($tid) {
		$details = array(
			'command'	=> $this->db->get_where('cmd_trigger', array('tid' => $tid), 1)->row(),
			'permissions'	=> $this->db->get_where('cmd_perms', array('tid' => $tid)),
		);
		
		return $details;
	}
	
	function get_permissions() {
		return $this->db->join('cmd_trigger', 'cmd_trigger.tid=cmd_perms.tid')
						->get('cmd_perms');
	}
	
	function insert_permission() {
		$data = array(
			'tid'	=> $this->input->post('tid'),
			'perm'	=> $this->input->post('perm'),
			'pdesc'	=> $this->input->post('pdesc'),
		);
		
		$this->db->insert('cmd_perms', $data);
		$data['pid'] = $this->db->insert_id();
		
		return $data['pid'] == FALSE ? FALSE : $data;
	}
	
	function update_permission($id, $field, $value) {
		// Check to see the permission exists
		$permission = $this->db->get_where('cmd_perms', array('pid' => $id), 1);
		if($permission->num_rows()) {
			$this->db->where('pid', $id)->update('cmd_perms', array($field => $value));
			return ($this->db->affected_rows() == 1) || ($permission->row()->{$field} == $value);
		}
		
		return FALSE;
	}
	
	function delete_permission($id) {
		return $this->_delete('cmd_perms', 'pid', $id);
	}
	
	function search_commands($field, $value, $distinctValues) {
		return $this->_search('cmd_trigger', $field, $value, $distinctValues);
	}
	
	function _delete($table, $idField, $id) {
		$this->db->delete($table, array($idField => $id));
		return $this->db->affected_rows() > 0;
	}
	function _search($table, $field, $value, $distinctValues) {
		$this->db->select($field)->like($field, $value);
		if($distinctValues) {
			$this->db->distinct();
		}
		
		return $this->db->get($table);
	}
}