<?php	require 'ant.php';	Ant::init()	->setup(		array(			'view' => $_SERVER['DOCUMENT_ROOT'] . "/trunk/templates",			'cache' => $_SERVER['DOCUMENT_ROOT'] . "/trunk/cache",			'minify' => true		)	);	echo Ant::init()	->get('vars')	->assign(		array(			'range' => array()		)	)	->draw();?>