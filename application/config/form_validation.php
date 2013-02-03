<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config = array(
	'login/index' => array(
		array(
			'field'	=> 'username',
			'label'	=> 'Username',
			'rules'	=> 'trim|required',
		),
		array(
			'field'	=> 'password',
			'label'	=> 'Password',
			'rules'	=> 'trim|required',
		),
	),
);