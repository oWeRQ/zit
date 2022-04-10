<?php

namespace Zit;

use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Zit
{
	protected $storeFile;
	protected $workDir;
	protected $zip;

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
		return new Zit("$dir/$file");
	}

	public function __construct($storeFile)
	{
		$this->storeFile = $storeFile;
		$this->workDir = dirname($storeFile);
		$this->zip = new ZipArchive;
		$this->zip->open($this->storeFile);
	}

	public function __destruct()
	{
		if ($this->zip->filename) {
			$this->zip->close();
		}
	}

	public function init()
	{
		$this->zip->open($this->storeFile, ZipArchive::CREATE);
		$this->zip->addEmptyDir('.zit');
	}

	protected function dir($path)
	{
		$path = rtrim($path, '/');

		return array_map(fn($name) => ($path === '.' ? $name : "$path/$name"), array_slice(scandir($path), 2));
	}

	public function addFiles($paths)
	{
		foreach ($paths as $path) {
			if (is_dir($path)) {
				$this->addFiles($this->dir($path));
			} elseif (!$this->isIgnoreFile($path)) {
				echo "add '$path'\n";
				$this->zip->addFile($path);
			}
		}
	}

	public function deleteFiles($files)
	{
		foreach ($files as $file) {
			$this->zip->deleteName($file);
		}
	}

	public function headTree()
	{
		return [];
	}

	public function indexTree()
	{
		$files = [];

		for ($i = 0; $i < $this->zip->numFiles; $i++) {
			$name = $this->zip->getNameIndex($i);
			if (substr($name, -1) !== '/' && !str_starts_with($name, '.zit/')) {
				$sha1 = sha1($this->zip->getFromName($name));
				$files[$name] = $sha1;
			}
		}

		ksort($files);
		return $files;
	}

	public function workTree()
	{
		$files = [];

		$trim = strlen($this->workDir) + 1;
		$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->workDir));
		foreach ($rii as $file) {
			$name = substr($file->getPathname(), $trim);
			if (!$file->isDir() && !$this->isIgnoreFile($name)) {
				$sha1 = sha1_file($file->getPathname());
				$files[$name] = $sha1;
			}
		}

		ksort($files);
		return $files;
	}

	public function status()
	{
		$deleted = [];
		$staged = [];
		$changed = [];
		$untracked = [];

		$headTree = $this->headTree();
		$indexTree = $this->indexTree();
		$workTree = $this->workTree();

		foreach ($headTree as $name => $sha1) {
			if (!array_key_exists($name, $indexTree)) {
				$deleted[] = $name;
			}
		}

		foreach ($indexTree as $name => $sha1) {
			if (!array_key_exists($name, $headTree) || $headTree[$name] !== $sha1) {
				$staged[] = $name;
			}
		}

		foreach ($workTree as $name => $sha1) {
			if (!array_key_exists($name, $indexTree)) {
				$untracked[] = $name;
			} elseif ($indexTree[$name] !== $sha1) {
				$changed[] = $name;
			}
		}

		return [
			'deleted' => $deleted,
			'staged' => $staged,
			'changed' => $changed,
			'untracked' => $untracked,
		];
	}

	protected function isIgnoreFile($name)
	{
		if (str_starts_with($name, '.git/'))
			return true;

		if (str_ends_with($name, '.zip'))
			return true;

		return false;
	}
}