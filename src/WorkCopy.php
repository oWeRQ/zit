<?php

namespace Zit;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

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

	public function workTree()
	{
		$files = [];

		$trim = strlen($this->getWorkDir()) + 1;
		$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->getWorkDir()));
		foreach ($rii as $file) {
			$name = substr($file->getPathname(), $trim);
			if (!$file->isDir() && !$this->isIgnoreFile($name)) {
				$files[$name] = sha1_file($file->getPathname());
			}
		}

		ksort($files);
		return $files;
	}

	public function read($name)
	{
		return file_get_contents($name);
	}

	public function write($name, $content)
	{
		return file_put_contents($name, $content);
	}

	public function isIgnoreFile($name)
	{
		if (str_starts_with($name, '.git/'))
			return true;

		if (str_ends_with($name, '.zip'))
			return true;

		return false;
	}

	public function dir($path)
	{
		$path = rtrim($path, '/');
		return array_map(fn($name) => ($path === '.' ? $name : "$path/$name"), array_slice(scandir($path), 2));
	}
}