<?php

class M_Worker_Job
{
	protected $name = 'M_Worker_Job';
	protected $uid;
	
	public function __construct($name)
	{
		$this->name = $name;
		$this->uid = $this->uid = uniqid('M_Id');
	}
	
	public function getName()
	{
		return $this->name;
	}
	
	public function onJobInit(M_Worker $worker)
	{
		$worker->log('++M_Worker_Job(name='.$this->name.', uid='.$this->uid.')::onJobInit');
	}
	
	public function runJob(M_Worker $worker)
	{
		$sleep = rand(1, 10);
		$worker->log('++M_Worker_Job(name='.$this->name.', uid='.$this->uid.')::runJob(delay='.$sleep.')');
		sleep($sleep);
	}
	
	public function onJobDestroy(M_Worker $worker)
	{
		$worker->log('++M_Worker_Job(name='.$this->name.', uid='.$this->uid.')::onJobDestroy');
	}	
}