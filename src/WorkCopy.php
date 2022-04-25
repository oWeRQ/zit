<?php

namespace Zit;

class WorkCopy
{
	protected $storeFile;
	protected $workDir;

	public static function path($path, $file = '.zit.zip')
	{
		$dir = $path;
		while (!file_exists("$dir/$file")) {
			$dir = dirname($dir);
			if ($dir === '/') {
				$dir = $path;
				break;
			}
		}
		return new static("$dir/$file");
	}

	public function __construct($storeFile)
	{
		$this->storeFile = $storeFile;
		$this->workDir = dirname($storeFile);
	}

	public function getStoreFile()
	{
		return $this->storeFile;
	}

	public function getWorkDir()
	{
		return $this->workDir;
	}
}