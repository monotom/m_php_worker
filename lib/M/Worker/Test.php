<?php

class M_Worker_Test
{
	public function run()
	{
		echo '<h2>M_Worker_Test::run(start)</h2>';
																
		$config = array('maxLoopSleepMs'=>4000, 		//4sec
						'minLoopSleepMs'=>1000,			//1sec
						'forkAfterNoSleepCycles'=> 2,	//
						'destroyAfterSleepCycles'=> 2,	//
						'delayCountingWorkerMs'=>300,	//
						'outputLogFile' => './M_WORKER.log');
		
		$job = new M_Worker_Job('testJob');  //jobs needs 1-6secs
		
		$worker = new M_Worker('testWorker', array($job), $config);
		
		$worker->run();
		
		echo 'M_Worker_Test::run(end)<hr>';
	}
}

?>