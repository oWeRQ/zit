<?php

namespace Zit;

class Console
{
	protected $zit;

	public function __construct(Zit $zit) 
	{
		$this->zit = $zit;
	}

	protected function print($data)
	{
		echo json_encode($data,  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
	}

	public function usage()
	{
		echo "TODO: Usage\n";
	}

	public function init()
	{
		$this->zit->init();
	}

	public function add(...$files)
	{
		$this->zit->addFiles($files);
	}

	public function rm(...$files)
	{
		$this->zit->deleteFiles($files);
	}

	public function status()
	{
		$this->print($this->zit->status());
	}

	public function log()
	{
		$this->print($this->zit->log());
	}

	public function commit($message)
	{
		$this->zit->commit($message);
	}

	public function branch($branch = null)
	{
		if ($branch)
			$this->zit->branch($branch);
		else
			$this->print($this->zit->listBranches());
	}

	public function checkout($branch)
	{
		$this->zit->checkout($branch);
	}
}
