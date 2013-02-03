<?php

/**
 * @group Model
 */

class TriggerModelTest extends CIUnit_TestCase
{
	private $_tm;
	private $_deleted_trigger;
    private $_max_tid = 0;

	public function __construct($name = NULL, array $data = array(), $dataName = '')
	{
		parent::__construct($name, $data, $dataName);
	}

	public function setUp()
	{
		parent::tearDown();
		parent::setUp();

		$this->CI->load->model('Trigger_model');
		$this->_tm = $this->CI->Trigger_model;
		$this->dbfixt('cmd_trigger');

		$triggers = array();
		foreach($this->cmd_trigger_fixt as $trigger) {
			$triggers[$trigger['tid']][] = (object)$trigger;
            $this->_max_tid = ($trigger['tid'] > $this->_max_tid) ? $trigger['tid'] : $this->_max_tid;
		}

		$this->cmd_trigger_fixt = array();
		$index = 0;
		foreach($triggers as $versions) {
			$this->cmd_trigger_fixt[$index] = array_reverse($versions);
			if($this->cmd_trigger_fixt[$index][0]->status == 0) {
				$this->_deleted_trigger = $this->cmd_trigger_fixt[$index][0];
			}

			$index++;
		}
	}

	protected function _identify_different_field($triggerA, $triggerB) {
		foreach($triggerA as $field => $value) {
			if($value != $triggerB->{$field}) {
				return $field;
			}
		}

		return FALSE;
	}

	public function testAbilityFetchTriggerValidID() {
		$expected = $this->cmd_trigger_fixt[0][0];

		$this->assertEquals($expected, $this->_tm->fetch($expected->tid));
	}

	public function testInabilityFetchTriggerInvalidID() {
		$this->assertEmpty($this->_tm->fetch(10000));
	}

	public function testInabilityFetchDeletedTrigger() {
		$this->assertEmpty($this->_tm->fetch($this->_deleted_trigger->tid));
	}

	public function testAbilityCreateTrigger() {
		$data = array(
			'cat'     => 'General',
			'trigger' => 'NewTrigger',
			'alias'   => 'newAlias',
			'desc'    => 'Some description',
			'instr'   => 'Some instructions',
			'syntax'  => 'The syntax',
		);

		$expected = (object)array(
			'tid'     => count($this->cmd_trigger_fixt) + 1,
			'version' => 1,
			'cat'     => $data['cat'],
			'trigger' => $data['trigger'],
			'alias'   => $data['alias'],
			'desc'    => $data['desc'],
			'instr'   => $data['instr'],
			'syntax'  => $data['syntax'],
		);

		$this->assertEquals($expected, $this->_tm->insert($data));
	}

	public function testTriggerCannotBeEmpty() {
		$data = array(
			'cat'     => 'General',
			'trigger' => '',
			'alias'   => 'newAlias',
			'desc'    => 'Some description',
			'instr'   => 'Some instructions',
			'syntax'  => 'The syntax',
		);
		$this->assertFalse($this->_tm->insert($data));
	}

	public function testTriggerCategoryCannotBeEmpty() {
		$data = array(
			'cat'     => '',
			'trigger' => 'newTrigger',
			'alias'   => 'newAlias',
			'desc'    => 'Some description',
			'instr'   => 'Some instructions',
			'syntax'  => 'The syntax',
		);
		$this->assertFalse($this->_tm->insert($data));
	}

	public function testVersionOfNewTriggerIsValid() {
		$data = array(
			'cat'     => 'General',
			'trigger' => 'NewTrigger',
			'alias'   => 'newAlias',
			'desc'    => 'Some description',
			'instr'   => 'Some instructions',
			'syntax'  => 'The syntax',
		);

		$trigger = $this->_tm->insert($data);
		$this->assertEquals(1, $trigger->version);
	}

    public function testAbilityCloneTriggerValid() {
        $original = $this->cmd_trigger_fixt[0][0];
        $expected = clone $original;
        $expected->tid = $this->_max_tid+1;
        $expected->version = 1;

        $this->assertEquals($expected, $this->_tm->clone_trigger($original->tid, $original->version));
    }

    public function testInabilityCloneTriggerInvalidTid() {
        $original = $this->cmd_trigger_fixt[0][0];

        $this->assertFalse($this->_tm->clone_trigger(1000, $original->version));
    }

    public function testInabilityCloneTriggerInvalidVersion() {
        $original = $this->cmd_trigger_fixt[0][0];

        $this->assertFalse($this->_tm->clone_trigger($original->tid, 1000));
    }

	public function testAbilityEditTriggerValidID() {
		$original = $this->cmd_trigger_fixt[0][0];
		$data = array(
			'trigger' => 'edittedTrigger',
		);
		$expected = clone $original;
		$expected->version++;
		$expected->trigger = $data['trigger'];

		$this->assertEquals($expected, $this->_tm->edit($original->tid, $original->version, $data));
	}

	public function testInabilityEditTriggerInvalidID() {
		$data = array('trigger' => 'edittedTrigger');

		$this->assertFalse($this->_tm->edit(1000, 'trigger', $data['trigger']));
	}

	public function testEdittedTriggerCannotBeEmpty() {
		$trigger = $this->cmd_trigger_fixt[0][0];
		$this->assertFalse($this->_tm->edit($trigger->tid, 'trigger', ''));
	}

	public function testEdittedTriggerCategoryCannotBeEmpty() {
		$trigger = $this->cmd_trigger_fixt[0][0];
		$this->assertFalse($this->_tm->edit($trigger->tid, 'cat', ''));
	}

	public function testAbilityEditAlias() {
		$original = $this->cmd_trigger_fixt[0][0];
		$data = array(
			'alias'   => 'edittedAlias',
		);
		$expected = clone $original;
		$expected->version++;
		$expected->alias = $data['alias'];

		$this->assertEquals($expected, $this->_tm->edit($original->tid, $original->version, $data));
	}

	public function testAbilityEditDescription() {
		$original = $this->cmd_trigger_fixt[0][0];
		$data = array(
			'desc'    => 'Editted Description'
		);
		$expected = clone $original;
		$expected->version++;
		$expected->desc = $data['desc'];

		$this->assertEquals($expected, $this->_tm->edit($original->tid, $original->version, $data));
	}

	public function testAbilityEditInstructions() {
		$original = $this->cmd_trigger_fixt[0][0];
		$data = array(
			'instr'   => 'Editted Instructions'
		);
		$expected = clone $original;
		$expected->version++;
		$expected->instr = $data['instr'];

		$this->assertEquals($expected, $this->_tm->edit($original->tid, $original->version, $data));
	}

	public function testAbilityEditSyntax() {
		$original = $this->cmd_trigger_fixt[0][0];
		$data = array(
			'syntax'  => 'Editted Syntax',
		);
		$expected = clone $original;
		$expected->version++;
		$expected->syntax = $data['syntax'];

		$this->assertEquals($expected, $this->_tm->edit($original->tid, $original->version, $data));
	}

	public function testInabilityEditStatus() {
		$trigger = $this->cmd_trigger_fixt[0][0];
		$data = array(
			'status'  => 1,
		);

		$this->assertFalse($this->_tm->edit($trigger->tid, $trigger->version, $data));
	}

	public function testInabilityEditInvalidField() {
		$trigger = $this->cmd_trigger_fixt[0][0];
		$data = array(
			'invalidField' => 'something',
		);

		$this->assertFalse($this->_tm->edit($trigger->tid, $trigger->version, $data));
	}

	public function testVersionOfEdittedTriggerIncrements() {
		$expected = $this->cmd_trigger_fixt[0][0];
		$data = array(
			'trigger' => 'edittedTrigger',
		);

		$result = $this->_tm->edit($expected->tid, $expected->version, $data);
		$this->assertEquals($expected->version+1, $result->version);
	}

	public function testAbilityDeleteTriggerValidID() {
		$trigger = $this->cmd_trigger_fixt[0][0];
		$this->assertEquals($trigger, $this->_tm->delete($trigger->tid));
		$this->assertEmpty($this->_tm->fetch($trigger->tid));
	}

	public function testInabilityDeleteTriggerInvalidID() {
		$this->assertFalse($this->_tm->delete(1000));
	}

	public function testAbilityRevertToEarlierVersionValidID() {
		$expected = $this->cmd_trigger_fixt[0][1];
		$revert_to = $expected->version;
		$expected->version = $this->cmd_trigger_fixt[0][0]->version+1;

		$this->assertEquals($expected, $this->_tm->revert($expected->tid, $revert_to));
	}

	public function testInabilityRevertToEarlierVersionInvalidID() {
		$this->assertFalse($this->_tm->revert(10000, 1));
	}

	public function testInabilityRevertToCurrentVersion() {
		$trigger = $this->cmd_trigger_fixt[0][0];
		$this->assertFalse($this->_tm->revert($trigger->tid, $trigger->version));
	}

	public function testInabilityRevertToInvalidVersion() {
		$trigger = $this->cmd_trigger_fixt[0][0];
		$this->assertFalse($this->_tm->revert($trigger->tid, 1000));
	}

	public function testRevertingVersionIncrementsVersion() {
		$revert_to = $this->cmd_trigger_fixt[0][1];

		$new_version = $this->_tm->revert($revert_to->tid, $revert_to->version);
		$this->assertEquals($this->cmd_trigger_fixt[0][0]->version+1, $new_version->version);
	}

	public function testAbilityRestoreDeletedTrigger() {
		$expected = $this->_deleted_trigger;
		$expected->status = 1;
		$expected->version++;

		$this->assertEquals($expected, $this->_tm->restore($expected->tid));
	}

	public function testInabilityRestoreNonDeletedTrigger() {
		$trigger = $this->cmd_trigger_fixt[0][0];
		$this->assertFalse($this->_tm->restore($trigger->tid));
	}

	public function testRestoringDeletedTriggerIncrementsVersion() {
		$expected = $this->_deleted_trigger;

		$result = $this->_tm->restore($expected->tid);
		$this->assertEquals($expected->version+1, $result->version);
	}
}
