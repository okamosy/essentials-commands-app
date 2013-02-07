<?php

/**
 * @group Model
 */

class Release_Trigger_MapModelTest extends CIUnit_TestCase {
	private $_mm;

	public function __construct($name = NULL, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);
	}

	public function setUp() {
		parent::tearDown();
		parent::setUp();

		$this->CI->load->model('Release_Trigger_Map_model');
		$this->_mm = $this->CI->Release_Trigger_Map_model;
		$this->dbfixt('cmd_release_trigger_map');

		$mapping = array();
		foreach ($this->cmd_release_trigger_map_fixt as $map) {
			$mapping[] = (object)$map;
		}

		$this->cmd_release_trigger_map_fixt = $mapping;
	}

	public function testAbilityFetchTriggersValidRID() {
		$expected = array();
		$rid      = $this->cmd_release_trigger_map_fixt[0]->rid;
		foreach ($this->cmd_release_trigger_map_fixt as $map) {
			if ($map->rid == $rid && $map->status) {
				$expected[$map->tid] = $map;
			}
		}

		$this->assertEquals($expected, $this->_mm->fetch($rid));
	}

	public function testInabilityFetchTriggerInvalidRID() {
		$this->assertEmpty($this->_mm->fetch(1000));
	}

	public function testAbilityCreateMapping() {
		$data            = (object)array(
			'rid'       => 10,
			'tid'       => 10,
			't_version' => 1,
		);
		$mapping         = clone $data;
		$mapping->status = 1;

		$expected = array(
			$mapping->tid => $mapping,

		);

		$this->assertTrue($this->_mm->insert($data->rid, $data->tid, $data->t_version));
		$this->assertEquals($expected, $this->_mm->fetch($data->rid));
	}

	public function testInabilityCreateDuplicateMapping() {
		$mapping = $this->cmd_release_trigger_map_fixt[0];

		$this->assertFalse($this->_mm->insert($mapping->rid, $mapping->tid, $mapping->t_version));
	}

	public function testAbilityEditMappingValidIDs() {
		$expected = $this->cmd_release_trigger_map_fixt[0];
		$expected->t_version++;

		$this->assertTrue($this->_mm->edit($expected->rid, $expected->tid, $expected->t_version));

		$mapping = $this->_mm->fetch($expected->rid);
		foreach ($mapping as $map) {
			if ($map->rid == $expected->rid && $map->tid == $expected->tid) {
				$this->assertEquals($expected, $map);
				break;
			}
		}
	}

	public function testInabilityEditMappingInvalidRID() {
		$expected = $this->cmd_release_trigger_map_fixt[0];
		$this->assertFalse($this->_mm->edit(1000, $expected->tid, 10));
	}

	public function testInabilityEditMappingInvalidTID() {
		$expected = $this->cmd_release_trigger_map_fixt[0];
		$this->assertFalse($this->_mm->edit($expected->rid, 1000, 10));
	}

	public function testAbilityDeleteMappingValidRID() {
		$deleted  = $this->cmd_release_trigger_map_fixt[0];
		$expected = array();
		foreach ($this->cmd_release_trigger_map_fixt as $mapping) {
			if ($mapping->rid == $deleted->rid && $mapping->tid != $deleted->tid && $mapping->status) {
				$expected[$mapping->tid] = $mapping;
			}
		}

		$this->assertTrue($this->_mm->delete((array)$deleted));
		$this->assertEquals($expected, $this->_mm->fetch($deleted->rid));
	}

	public function testInabilityDeleteMappingInvalidRID() {
		$deleted      = $this->cmd_release_trigger_map_fixt[0];
		$unknown      = clone $deleted;
		$unknown->rid = 1000;
		$expected     = array();
		foreach ($this->cmd_release_trigger_map_fixt as $mapping) {
			if ($mapping->rid == $deleted->rid && $mapping->status) {
				$expected[$mapping->tid] = $mapping;
			}
		}

		$this->assertFalse($this->_mm->delete((array)$unknown));
		$this->assertEquals($expected, $this->_mm->fetch($deleted->rid));
	}

	public function testInabilityDeleteMappingInvalidTID() {
		$deleted      = clone $this->cmd_release_trigger_map_fixt[0];
		$deleted->tid = 1000;
		$expected     = array();
		foreach ($this->cmd_release_trigger_map_fixt as $mapping) {
			if ($mapping->rid == $deleted->rid && $mapping->status) {
				$expected[$mapping->tid] = $mapping;
			}
		}

		$this->assertFalse($this->_mm->delete((array)$deleted));
		$this->assertEquals($expected, $this->_mm->fetch($deleted->rid));
	}
}
