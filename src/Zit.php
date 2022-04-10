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

	public function reload()
	{
		$this->zip->close();
		$this->zip->open($this->storeFile);
	}

	public function init()
	{
		$this->zip->open($this->storeFile, ZipArchive::CREATE);
		$this->zip->addEmptyDir('.zit');
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

	public function head()
	{
		$ref = $this->headRef();
		return $this->zip->getFromName(".zit/$ref") ?: sha1('');
	}

	public function headRef()
	{
		return $this->zip->getFromName('.zit/HEAD') ?: 'refs/heads/master';
	}

	public function headCommit()
	{
		return $this->readJson($this->head());
	}

	public function headTree()
	{
		$commit = $this->headCommit();
		return $commit ? $this->readJson($commit['tree']) : [];
	}

	public function indexTree()
	{
		$files = [];

		for ($i = 0; $i < $this->zip->numFiles; $i++) {
			$name = $this->zip->getNameIndex($i);
			if (substr($name, -1) !== '/' && !str_starts_with($name, '.zit/')) {
				$files[$name] = sha1($this->zip->getFromName($name));
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
				$files[$name] = sha1_file($file->getPathname());
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

		foreach ($headTree as $name => $hash) {
			if (!array_key_exists($name, $indexTree)) {
				$deleted[] = $name;
			}
		}

		foreach ($indexTree as $name => $hash) {
			if (!array_key_exists($name, $headTree) || $headTree[$name] !== $hash) {
				$staged[] = $name;
			}
		}

		foreach ($workTree as $name => $hash) {
			if (!array_key_exists($name, $indexTree)) {
				$untracked[] = $name;
			} elseif ($indexTree[$name] !== $hash) {
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

	public function log()
	{
		$commits = [];

		$current = $this->headCommit();
		while ($current) {
			$commits[] = $current;
			$current = $this->readJson($current['parents'][0]);
		}

		return $commits;
	}

	public function commit($message)
	{
		$files = $this->indexTree();

		foreach ($files as $name => $hash) {
			$this->copy($name, $this->objectPath($hash));
		}

		$treeHash = $this->storeJson($files);
		$commitHash = $this->storeJson([
			'date' => date('c'),
			'author' => $this->author(),
			'message' => $message,
			'tree' => $treeHash,
			'parents' => [$this->head()],
		]);
		$this->storeHead($commitHash);
		echo "commit $commitHash\n";
	}

	protected function storeHead($hash)
	{
		$ref = $this->headRef();
		$this->zip->addFromString(".zit/$ref", $hash);
	}

	protected function readJson($hash)
	{
		$json = $this->zip->getFromName($this->objectPath($hash)) ?: '{}';
		return json_decode($json, true);
	}

	protected function storeJson($data)
	{
		$json = json_encode($data);
		$hash = sha1($json);
		$this->zip->addFromString($this->objectPath($hash), $json);
		return $hash;
	}

	protected function copy($from, $to)
	{
		$this->zip->addFromString($to, $this->zip->getFromName($from));
	}

	protected function headPath($name)
	{
		return '.zit/refs/heads/'.$name;
	}

	protected function objectPath($hash)
	{
		$dir = '.zit/objects/'.substr($hash, 0, 2);
		$name = substr($hash, 2);
		return "$dir/$name";
	}

	protected function isIgnoreFile($name)
	{
		if (str_starts_with($name, '.git/'))
			return true;

		if (str_ends_with($name, '.zip'))
			return true;

		return false;
	}

	protected function dir($path)
	{
		$path = rtrim($path, '/');
		return array_map(fn($name) => ($path === '.' ? $name : "$path/$name"), array_slice(scandir($path), 2));
	}

	protected function author()
	{
		return getenv('USER').'@'.gethostname();
	}
}