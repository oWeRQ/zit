<?php

namespace Zit;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class WorkCopy
{
	protected $storeFile;
	protected $workDir;
	protected $currentDir;

	public static function path($currentDir, $file = '.zit.zip')
	{
		$dir = $currentDir;
		while (!file_exists("$dir/$file")) {
			$dir = dirname($dir);
			if ($dir === '/') {
				$dir = $currentDir;
				break;
			}
		}
		return new static("$dir/$file", $currentDir);
	}

	public function __construct($storeFile, $currentDir = null)
	{
		$this->storeFile = $storeFile;
		$this->workDir = realpath(dirname($storeFile));
		$this->currentDir = $currentDir ?: $this->workDir;
	}

	public function getStoreFile()
	{
		return $this->storeFile;
	}

	public function workTree($name = null)
	{
		$path = $this->realpath($name);
		if (!is_dir($path)) {
			return [$name => sha1_file($path)];
		}

		$files = [];

		$trim = strlen($this->workDir) + 1;
		$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
		foreach ($rii as $file) {
			$name = substr($file->getPathname(), $trim);
			if (!$file->isDir() && !$this->isIgnoreFile($name)) {
				$files[$name] = sha1_file($file->getPathname());
			}
		}

		ksort($files);
		return $files;
	}

	public function normalizePath($path)
	{
		$realpath = realpath($this->currentDir.'/'.$path);
		if (str_starts_with($realpath, $this->workDir)) {
			return substr($realpath, strlen($this->workDir) + 1);
		}
	}

	public function realpath($name)
	{
		return $this->workDir.'/'.$name;
	}

	public function read($name)
	{
		return file_get_contents($this->realpath($name));
	}

	public function write($name, $content)
	{
		return file_put_contents($this->realpath($name), $content);
	}

	public function delete($name)
	{
		return unlink($this->realpath($name));
	}

	public function isIgnoreFile($name)
	{
		if (str_starts_with($name, '.git/'))
			return true;

		if (str_ends_with($name, '.zip'))
			return true;

		return false;
	}
}