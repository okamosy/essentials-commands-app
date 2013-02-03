<?php

/**
 * @group Model
 */

class LogModelTest extends CIUnit_TestCase
{
	private $_lm;

	public function __construct($name = NULL, array $data = array(), $dataName = '')
	{
		parent::__construct($name, $data, $dataName);
	}
	
	public function setUp()
	{
		parent::tearDown();
		parent::setUp();
		
		$this->CI->load->model('Log_model');
		$this->_lm = $this->CI->Log_model;

		$this->dbfixt('cmd_log', 'cmd_user');

		$list = array();
		foreach($this->cmd_user_fixt as $user) {
			$list[$user['uid']] = (object)$user;
		}
		$this->cmd_user_fixt = $list;

		$list = array();
		foreach($this->cmd_log_fixt as $log) {
			$username = !empty($this->cmd_user_fixt[$log['uid']]) ? $this->cmd_user_fixt[$log['uid']]->username : 'Anonymous';
			$list[] = (object)array(
				'lid'       => $log['lid'],
				'username'  => $username,
				'timestamp' => $log['timestamp'],
				'event'     => $log['event'],
				'data'      => $log['data'],
			);
		}
		$this->cmd_log_fixt = array_reverse($list);
	}

	protected function _fetch_user() {
		return reset($this->cmd_user_fixt);
	}

	public function testAbilityFetchLogDefaultLimit() {
		$expected = array_slice($this->cmd_log_fixt, 0, 10);
		$this->assertEquals($expected, $this->_lm->fetch());
	}

	public function testAbilityFetchLogAll() {
		$this->assertEquals($this->cmd_log_fixt, $this->_lm->fetch_all());
	}

	public function testAbilityFetchLogLimited() {
		$start = 5;
		$expected = array_slice($this->cmd_log_fixt, $start);
		$this->assertEquals($expected, $this->_lm->fetch($start));
	}

	public function testAbilityFetchLogRangeValid() {
		$start = 2;
		$limit = 5;
		$expected = array_slice($this->cmd_log_fixt, $start, $limit);
		$this->assertEquals($expected, $this->_lm->fetch($start, $limit));
	}

	public function testAbilityFetchLogRangeInvalid() {
		$this->assertEmpty($this->_lm->fetch(100, 5));
	}

	public function testAbilityInsertLogValidEvent() {
		$expected = array_slice($this->cmd_log_fixt, 0, 9);
		$user = $this->_fetch_user();
		$event = array(
			'lid'       => count($this->cmd_log_fixt) + 1,
			'uid'       => $user->uid,
			'timestamp' => time(),
			'event'     => Log_model::EVENT_LOGIN,
			'data'      => 'Some test data',
		);

		$this->_lm->insert($event);

		$event['username'] = $user->username;
		unset($event['uid']);
		array_unshift($expected, (object)$event);

		$this->assertEquals($expected, $this->_lm->fetch());
	}

	public function testAbiltiyInsertLogInvalidEvent() {
		$expected = array_slice($this->cmd_log_fixt, 0, 9);
		$user = $this->_fetch_user();
		$event = array(
			'lid'       => count($this->cmd_log_fixt) + 1,
			'uid'       => $user->uid,
			'timestamp' => time(),
			'event'     => 'bad event',
			'data'      => 'Some test data',
		);

		$this->_lm->insert($event);

		$event['username'] = $user->username;
		$event['event'] = Log_model::EVENT_UNDEFINED;
		unset($event['uid']);
		array_unshift($expected, (object)$event);

		$this->assertEquals($expected, $this->_lm->fetch());
	}
}
