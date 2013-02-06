<?php

/**
 * @group Model
 */

class Trigger_Perm_MapModelTest extends CIUnit_TestCase {
	private $_mm;

	public function __construct($name = NULL, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);
	}

	public function setUp() {
		parent::tearDown();
		parent::setUp();

		$this->CI->load->model('Trigger_Perm_Map_model');
		$this->_mm = $this->CI->Trigger_Perm_Map_model;
		$this->dbfixt('cmd_trigger_perm_map');

		$mapping = array();
		foreach ($this->cmd_trigger_perm_map_fixt as $map) {
			$mapping[] = (object)$map;
		}

		$this->cmd_trigger_perm_map_fixt = $mapping;
	}

	protected function _trigger($trigger) {
		return $this->_extract($trigger, 't');
	}

	protected function _perm($perm) {
		return $this->_extract($perm, 'p');
	}

	protected function _extract($fixt, $type) {
		if (is_array($fixt)) {
			$fixt = (object)$fixt;
		}

		$id      = "{$type}id";
		$version = "{$type}_version";

		return array(
			$id      => $fixt->{$id},
			$version => $fixt->{$version},
		);
	}

	public function testAbilityFetchTriggersValidTID() {
		$expected = array();
		$trigger  = $this->_trigger($this->cmd_trigger_perm_map_fixt[0]);
		foreach ($this->cmd_trigger_perm_map_fixt as $map) {
			if ($map->tid == $trigger['tid'] && $map->t_version == $trigger['t_version']) {
				$expected[$map->pid] = $map;
			}
		}

		$this->assertEquals($expected, $this->_mm->fetch($trigger));
	}

	public function testInabilityFetchTriggerInvalidTID() {
		$trigger = array(
			'tid'       => 1000,
			't_version' => 1,
		);
		$this->assertEmpty($this->_mm->fetch($trigger));
	}

	public function testAbilityCreateMapping() {
		$data     = array(
			'tid'       => 10,
			't_version' => 1,
			'pid'       => 10,
			'p_version' => 1,
			'status'    => 1,
		);
		$expected = array(
			$data['pid'] => (object)$data,
		);

		// Look for others that happen to match
		foreach ($this->cmd_trigger_perm_map_fixt as $map) {
			if ($map->tid == $data['tid'] && $map->t_version == $data['t_version'] && $map->status) {
				$expected[$map->pid] = $map;
			}
		}

		$this->assertTrue($this->_mm->insert($data));
		$this->assertEquals($expected, $this->_mm->fetch($this->_trigger($data)));
	}

	public function testInabilityCreateDuplicateMapping() {
		$mapping = $this->cmd_trigger_perm_map_fixt[0];

		$this->assertFalse($this->_mm->insert((array)$mapping));
	}

	public function testAbilityEditMappingValidIDs() {
		$current  = clone $this->cmd_trigger_perm_map_fixt[0];
		$expected = clone $current;
		$expected->p_version++;

		$this->assertTrue($this->_mm->edit((array)$expected, (array)$current));

		$mapping = $this->_mm->fetch($this->_trigger($expected));
		foreach ($mapping as $map) {
			if ($map->tid == $expected->tid && $map->pid == $expected->pid) {
				$this->assertEquals($expected, $map);
				break;
			}
		}
	}

	public function testInabilityEditMappingInvalidTID() {
		$current       = clone $this->cmd_trigger_perm_map_fixt[0];
		$expected      = clone $current;
		$expected->pid = 12;
		$current->tid  = 1000;
		$this->assertFalse($this->_mm->edit((array)$expected, (array)$current));
	}

	public function testInabilityEditMappingInvalidTriggerVersion() {
		$current            = clone $this->cmd_trigger_perm_map_fixt[0];
		$expected           = clone $current;
		$expected->pid      = 12;
		$current->t_version = 1000;
		$this->assertFalse($this->_mm->edit((array)$expected, (array)$current));
	}

	public function testInabilityEditMappingInvalidPID() {
		$current       = clone $this->cmd_trigger_perm_map_fixt[0];
		$expected      = clone $current;
		$expected->pid = 12;
		$current->pid  = 1000;
		$this->assertFalse($this->_mm->edit((array)$expected, (array)$current));
	}

	public function testInabilityEditMappingInvalidPermVersion() {
		$current            = clone $this->cmd_trigger_perm_map_fixt[0];
		$expected           = clone $current;
		$expected->pid      = 12;
		$current->p_version = 1000;
		$this->assertFalse($this->_mm->edit((array)$expected, (array)$current));
	}

	public function testAbilityDeleteMappingValidTID() {
		$deleted = $this->_trigger($this->cmd_trigger_perm_map_fixt[0]);

		$this->assertTrue($this->_mm->delete($deleted));
		$this->assertEmpty($this->_mm->fetch($deleted));
	}

	public function testInAbilityDeleteMappinginValidTID() {
		$deleted        = $this->_trigger($this->cmd_trigger_perm_map_fixt[0]);
		$deleted['tid'] = 1000;

		$this->assertFalse($this->_mm->delete($deleted));
	}

	public function testAbilityDeleteMappingValidPID() {
		$trigger = $this->_trigger($this->cmd_trigger_perm_map_fixt[0]);
		$perm    = $this->_perm($this->cmd_trigger_perm_map_fixt[0]);

		$expected = array();
		foreach ($this->cmd_trigger_perm_map_fixt as $mapping) {
			if ($trigger == $this->_trigger($mapping) && $perm != $this->_perm($mapping)) {
				$expected[$mapping->pid] = $mapping;
			}
		}

		$this->assertTrue($this->_mm->delete($perm));
		$this->assertEquals($expected, $this->_mm->fetch($trigger));
	}

	public function testInAbilityDeleteMappinginValidPID() {
		$perm        = $this->_perm($this->cmd_trigger_perm_map_fixt[0]);
		$perm['pid'] = 1000;

		$this->assertFalse($this->_mm->delete($perm));
	}
}
