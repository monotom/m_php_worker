<?php

ignore_user_abort(true);

ini_set('max_execution_time', 0);

ob_implicit_flush(true);
			


class M_Worker
{	
	public $config = array(
						'maxLoopSleepMs'=>60000, 			//60sec
						'minLoopSleepMs'=>1000,				//1sec
						'loopSleepAdjustmentRate'=>2,		//
						'forkAfterNoSleepCycles'=>10,		//
						'destroyAfterSleepCycles'=> 10,		//
						'minWorker'=>1,						//TDOD start min worker on start
						'maxWorker'=>10,					//
						'delayCountingWorkerMs'=>300,		//
						'outputLogFile' => './M_WORKER.log'	);		
	
	protected $stopped = true;	
	
	protected $workerName='M_Worker';
	protected $uid;
	protected $jobQueue = array();
	protected $isMainWorker = false;	
	protected $runningWorker = 1; 
	protected $lastTimeCountingWorker = 0;
	
	protected $noSleepCycles = 0;
	protected $sleepCycles = 0;
	protected $actualLoopSleep = 100;
	
	protected $mainWorkerLockFileHandle;
	
	protected $workerLogFileHandle;		
	
	public function __construct($workerName, array $jobs, array $config = null)
	{
		$this->uid = uniqid('M_Id');
		$this->workerName = $workerName;
		
		if($config)
			$this->config = array_merge($this->config, $config);
			
		foreach($jobs as $job)
			$this->addJob($job);
	}
	
	public function run()
	{			
		$this->triggerInit();				
		while(!$this->stopped)
		{			
			$lastRunStartTime = microtime(true);		
			
			foreach($this->jobQueue as $job)
				$job->runJob($this);	

			$lastRunDuration = (int)((microtime(true) - $lastRunStartTime) * 1000);
						
			$this->calibrateAndDoLoopSleep($lastRunDuration);
			
			$this->forkOrDestroyIfNeeded();		
		}		
		$this->triggerShoutDown();
	}	
	
	protected function triggerInit()
	{			
		$this->actualLoopSleep = $this->getConfigValue('maxLoopSleepMs') / 2;
		
		$this->stopped = false;
		
		if($this->getConfigValue('outputLogFile'))
			$this->workerLogFileHandle = fopen($this->getConfigValue('outputLogFile'), 'a');

		$this->isMainWorker = $this->getMainLock();
		
		if($this->isMainWorker)
		{						
			$this->log('CREATE::isMainWorker');
			
			ignore_user_abort(false);
						
			$this->initWorkerCounter();
			
			$this->runningWorker = 1;
		}
		else 
		{	
			header("Connection: close"); 
							
			$this->log('CREATE::isChildWorker');	
			
			$this->runningWorker = $this->countRunningWorker();
			
			$this->incrementWorkerCounter();
		}	
				
		foreach($this->jobQueue as $job)
			$job->onJobInit($this);			
	}
	
	protected function triggerShoutdown()
	{		
		$this->log('DESTROY::triggerShoutdown');

		foreach($this->jobQueue as $job)
			$job->onJobDestroy($this);
									
		if(!$this->isMainWorker)
			$this->decrementWorkerCounter();			
			
		if(is_resource($this->workerLogFileHandle))
			fclose($this->workerLogFileHandle);
					
		$this->releaseMainLock();
	}
				
	protected function calibrateAndDoLoopSleep($lastRunDuration)
	{		
		if($lastRunDuration > $this->actualLoopSleep)
		{	
			$this->sleepCycles = 0;		
			++$this->noSleepCycles;
						
			$this->actualLoopSleep = max( $this->actualLoopSleep / $this->getConfigValue('loopSleepAdjustmentRate'),
										  $this->getConfigValue('minLoopSleepMs'));
						
			$this->log('last run needed more time then actualLoopSleep(new='.$this->actualLoopSleep.')');
		}
		else 
		{
			$this->noSleepCycles = 0;
			++$this->sleepCycles;
			
			$this->actualLoopSleep = min( $this->actualLoopSleep * $this->getConfigValue('loopSleepAdjustmentRate'),
										  $this->getConfigValue('maxLoopSleepMs'));

			$timeToSleep = (int)($this->actualLoopSleep - $lastRunDuration);
			$this->log('last run was faster then actualLoopSleep, timeToSleep='.$timeToSleep);
			
			usleep($timeToSleep);
		}	
	}	
	
	protected function forkOrDestroyIfNeeded()
	{
		if($this->noSleepCycles > $this->getConfigValue('forkAfterNoSleepCycles')
		&& $this->countRunningWorker() < $this->getConfigValue('maxWorker'))
		{					
			$this->log('create child from mainworker actualWorkerCount='.$this->countRunningWorker());	
			$this->fork();
		}
		elseif(!$this->isMainWorker
		&&(     
			   $this->sleepCycles > $this->getConfigValue('destroyAfterSleepCycles')
			|| $this->getMainLock() ))
		{			
			$this->log('stop child worker actualWorkerCount='.$this->countRunningWorker());
			$this->releaseMainLock();
			$this->stopped = true;
		}						
	}

	protected function getMainLock()
	{
		if(!is_resource($this->mainWorkerLockFileHandle))
		{
			$this->mainWorkerLockFileHandle = 
							fopen('./M_Worker_'.ucfirst($this->workerName).'.lock', 'r');	
		}
		return is_resource($this->mainWorkerLockFileHandle) 
			   && @flock($this->mainWorkerLockFileHandle, LOCK_EX | LOCK_NB);
	}
	
	protected function releaseMainLock()
	{
		if(is_resource($this->mainWorkerLockFileHandle))
			fclose($this->mainWorkerLockFileHandle);		
	}
	
	protected function getCounterFileName()
	{
		return './M_Worker_'.ucfirst($this->workerName).'.cnt';
	}
	
	protected function initWorkerCounter()
	{
		file_put_contents($this->getCounterFileName(), 1);	
		$this->lastTimeCountingWorker = microtime(true);
		$this->runningWorker = 1;		
	}
	
	protected function incrementWorkerCounter()
	{			
		$this->runningWorker = (int)file_get_contents($this->getCounterFileName());
		file_put_contents($this->getCounterFileName(), ++$this->runningWorker);				
	}
	
	protected function decrementWorkerCounter()
	{
		$this->runningWorker = (int)file_get_contents($this->getCounterFileName());	
		file_put_contents($this->getCounterFileName(), --$this->runningWorker);			
	}
		
	public function countRunningWorker()
	{
		if($this->lastTimeCountingWorker + ($this->getConfigValue('delayCountingWorkerMs')/1000) < microtime(true))
		{	
			$this->runningWorker = (int)file_get_contents($this->getCounterFileName());					
			$this->lastTimeCountingWorker = microtime(true);
		}		
		return $this->runningWorker; 
	}
	
	protected function fork()
	{
		$url = 'http://'.$_SERVER['HTTP_HOST'].':'.$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
		$this->log('forking(url='.$url.')');
		$fp = fopen($url, 'r');
		
		if(is_resource($fp))
			fclose($fp);
	}
	
	public function getConfigValue($name, $default = null)
	{		
		return (isset($this->config[$name])) ? $this->config[$name] : $default; 
	}
	
	public function log($message, $logToFile = true)
	{
		$msg = date('d.m H:i:s').' | M_Worker(pid='.getmypid().', workerName='.$this->workerName.', uid='.$this->uid.')'."\n".$message."\n \n";
		
		echo str_pad(nl2br($msg),4096);
		
		if(is_resource($this->workerLogFileHandle) && $logToFile)
			fwrite($this->workerLogFileHandle, $msg);		
	}
	
	public function addJob(M_Worker_Job $job)
	{			
		$this->jobQueue[$job->getName()] = $job;
	}
}
	