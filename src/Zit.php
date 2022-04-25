<?php

namespace Zit;

class Zit
{
	protected $store;
	protected $workCopy;
	protected $author;

	public function getStoreFile()
	{
		return $this->store->getStoreFile();
	}

	public function getWorkDir()
	{
		return $this->workCopy->getWorkDir();
	}

	public function getZip()
	{
		return $this->store->getZip();
	}

	public function __construct($store, $workCopy, $author = null)
	{
		$this->store = $store;
		$this->workCopy = $workCopy;
		$this->author = $author;
	}

	public function init()
	{
		$this->store->init();
	}

	public function addFiles($paths)
	{
		foreach ($paths as $path) {
			if (is_dir($path)) {
				$this->addFiles($this->workCopy->dir($path));
			} elseif (!$this->workCopy->isIgnoreFile($path)) {
				echo "add '$path'\n";
				$this->getZip()->addFile($path);
			}
		}
	}

	public function deleteFiles($files)
	{
		$this->store->indexDelete($files);
	}

	public function restoreFiles($names)
	{
		$headTree = $this->headTree();
		foreach ($names as $name) {
			if (array_key_exists($name, $headTree)) {
				echo "restore $name\n";
				$this->workCopy->write($name, $this->store->readObject($headTree[$name]));
			}
		}
	}

	public function readHead()
	{
		$ref = $this->headRef();
		if (!str_starts_with($ref, 'refs/'))
			return $ref;

		return $this->store->readZit($ref) ?: sha1('');
	}

	public function writeHead($hash)
	{
		$ref = $this->headRef();
		$this->store->writeZit($ref, $hash);
	}

	public function headRef()
	{
		return $this->store->readZit('HEAD') ?: 'refs/heads/master';
	}

	public function headCommit()
	{
		return $this->store->readJson($this->readHead());
	}

	public function headTree()
	{
		$commit = $this->headCommit();
		return $commit ? $this->store->readJson($commit['tree']) : [];
	}

	public function status()
	{
		$deleted = [];
		$staged = [];
		$changed = [];
		$untracked = [];

		$headTree = $this->headTree();
		$indexTree = $this->store->indexTree();
		$workTree = $this->workCopy->workTree();

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
			'commit' => $this->readHead(),
			'ref' => $this->headRef(),
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
			$current = $this->store->readJson($current['parents'][0]);
		}

		return $commits;
	}

	public function listBranches()
	{
		$branches = [];

		$dir = '.zit/refs/heads/';
		$trim = strlen($dir);
		for ($i = 0; $i < $this->getZip()->numFiles; $i++) {
			$name = $this->getZip()->getNameIndex($i);
			if (str_starts_with($name, $dir)) {
				$branches[substr($name, $trim)] = $this->store->read($name);
			}
		}

		return $branches;
	}

	public function commit($message)
	{
		$files = $this->store->indexTree();

		foreach ($files as $name => $hash) {
			$this->store->writeObject($hash, $this->store->readIndex($name));
		}

		$treeHash = $this->store->writeJson($files);
		$commitHash = $this->store->writeJson([
			'date' => date('c'),
			'author' => $this->author,
			'message' => $message,
			'tree' => $treeHash,
			'parents' => [$this->readHead()],
		]);
		$this->writeHead($commitHash);
		echo "commit $commitHash\n";
	}

	public function branch($branch)
	{
		$hash = $this->readHead();
		$ref = "refs/heads/$branch";
		$this->store->writeZit($ref, $hash);
		$this->store->writeZit('HEAD', $ref);
		echo "ref $ref\n";
	}

	public function switch($branch)
	{
		$ref = "refs/heads/$branch";
		$hash = $this->store->readZit($ref);
		$commit = $this->store->readJson($hash);
		$commitTree = $this->store->readJson($commit['tree']);
		$workTree = $this->workCopy->workTree();

		foreach ($commitTree as $name => $hash) {
			if (!array_key_exists($name, $workTree) || $workTree[$name] !== $hash) {
				echo "update '$name'\n";
				$this->workCopy->write($name, $this->store->readObject($hash));
			}
		}

		$this->store->writeZit('HEAD', $ref);
	}

	public function reset()
	{
		$headTree = $this->headTree();
		$indexTree = $this->store->indexTree();

		foreach ($headTree as $name => $hash) {
			if (!array_key_exists($name, $indexTree) || $indexTree[$name] !== $hash) {
				$this->store->writeIndex($name, $this->store->readObject($hash));
			}
		}

		foreach ($indexTree as $name => $hash) {
			if (!array_key_exists($name, $headTree)) {
				$this->getZip()->deleteName($name);
			}
		}
	}
}