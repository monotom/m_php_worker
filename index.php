<?php

	error_reporting(E_ALL);
	
	include './lib/M/Loader.php';
	
	M_Loader::startAutoLoader(array('./lib/'));
		
	$test = new M_Worker_Test();
	
	$test->run();