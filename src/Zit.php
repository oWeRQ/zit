<?php

namespace Zit;

class Zit
{
	protected $store;
	protected $workCopy;

	public function output($message)
	{
		echo "$message\n";
	}

	public function __construct(Store $store, WorkCopy $workCopy)
	{
		$this->store = $store;
		$this->workCopy = $workCopy;
	}

	public function init()
	{
		$this->store->init();
	}

	public function addFiles($paths)
	{
		$indexTree = $this->store->indexTree();
		foreach ($paths as $path) {
			$path = $this->workCopy->normalizePath($path);
			foreach ($this->workCopy->workTree($path) as $name => $hash) {
				if (!array_key_exists($name, $indexTree) || $indexTree[$name] !== $hash) {
					$this->store->writeIndex($name, $this->workCopy->read($name));
					$this->output("add '$name'");
				}
			}
		}
	}

	public function deleteFiles($paths)
	{
		foreach ($paths as $path) {
			$name = $this->workCopy->normalizePath($path);
			$this->store->deleteIndex($name);
			$this->output("delete '$name'");
		}
	}

	public function restoreFiles($paths)
	{
		$headTree = $this->store->headTree();
		foreach ($paths as $path) {
			$name = $this->workCopy->normalizePath($path);
			if (array_key_exists($name, $headTree)) {
				$this->workCopy->write($name, $this->store->readObject($headTree[$name]));
				$this->output("restore '$name'");
			}
		}
	}

	public function status()
	{
		$deleted = [];
		$staged = [];
		$changed = [];
		$untracked = [];

		$headTree = $this->store->headTree();
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
			'commit' => $this->store->readHeadHash(),
			'branch' => $this->store->readHeadBranch(),
			'deleted' => $deleted,
			'staged' => $staged,
			'changed' => $changed,
			'untracked' => $untracked,
		];
	}

	public function log($hash = null)
	{
		$commits = [];

		if (!$hash) {
			$hash = $this->store->readHeadHash();
		}
		while ($hash) {
			$commit = $this->store->readJson($hash);
			if (!$commit)
				break;

			$commits[] = ['commit' => $hash] + $commit;
			$hash = $commit['parents'][0];
		}

		return $commits;
	}

	public function listBranches()
	{
		return $this->store->listBranches();
	}

	public function commit($message, $author)
	{
		$files = $this->store->indexTree();

		foreach ($files as $name => $hash) {
			$this->store->writeObject($hash, $this->store->readIndex($name));
		}

		$treeHash = $this->store->writeJson($files);
		$commitHash = $this->store->writeJson([
			'date' => date('c'),
			'author' => $author,
			'message' => $message,
			'tree' => $treeHash,
			'parents' => [$this->store->readHeadHash()],
		]);
		$this->store->writeHeadHash($commitHash);
		$this->output("commit $commitHash");
	}

	public function branch($branch)
	{
		$this->store->writeBranch($branch, $this->store->readHeadHash());
		$this->store->writeHeadBranch($branch);
	}

	public function switch($branch, $commitHash = null)
	{
		if (!$commitHash) {
			$commitHash = $this->store->readBranch($branch);
		}
		$commit = $this->store->readJson($commitHash);
		$commitTree = $this->store->readJson($commit['tree']);
		$workTree = $this->workCopy->workTree();

		foreach ($commitTree as $name => $hash) {
			if (!array_key_exists($name, $workTree) || $workTree[$name] !== $hash) {
				$this->workCopy->write($name, $this->store->readObject($hash));
				$this->output("update '$name'");
			}
		}

		$this->store->writeBranch($branch, $commitHash);
		$this->store->writeHeadBranch($branch);
	}

	public function merge($branch)
	{
		$headHash = $this->store->readHeadHash();
		$mergeHash = $this->store->readBranch($branch);
		$commonHash = $this->commonCommit($headHash, $mergeHash);
		$this->output("common commit $commonHash");

		$headTree = $this->store->headTree();
		$mergeTree = $this->store->commitTree($mergeHash);
		$commonTree = $this->store->commitTree($commonHash);

		$headChanged = array_diff_assoc($headTree, $commonTree);
		$mergeChanged = array_diff_assoc($mergeTree, $commonTree);
		$conflictNames = array_keys(array_intersect_key($headChanged, $mergeChanged));

		foreach ($conflictNames as $name) {
			$head = preg_split('/\n/', $this->store->readObject($headTree[$name]));
			$merge = preg_split('/\n/', $this->store->readObject($mergeTree[$name]));
			$common = preg_split('/\n/', $this->store->readObject($commonTree[$name]));
			$merged = implode("\n", (new Diff)->merge($common, $head, $merge));

			$this->output("@$name\n$merged");
		}
	}

	public function commonCommit($a, $b)
	{
		$logA = array_column($this->log($a), 'commit');
		$logB = array_column($this->log($b), 'commit');
		$logCommon = array_intersect($logA, $logB);
		return reset($logCommon);
	}

	public function reset()
	{
		$headTree = $this->store->headTree();
		$indexTree = $this->store->indexTree();

		foreach ($headTree as $name => $hash) {
			if (!array_key_exists($name, $indexTree) || $indexTree[$name] !== $hash) {
				$this->store->resetIndex($name, $hash);
				$this->output("reset index '$name'");
			}
		}

		foreach ($indexTree as $name => $hash) {
			if (!array_key_exists($name, $headTree)) {
				$this->store->deleteIndex($name);
				$this->output("delete index '$name'");
			}
		}
	}

	public function diff(array $paths)
	{
		$diff = [];

		if (count($paths) === 0) {
			$paths = ['.'];
		}

		$indexTree = $this->store->indexTree();
		foreach ($paths as $path) {
			$path = $this->workCopy->normalizePath($path);
			foreach ($this->workCopy->workTree($path) as $name => $hash) {
				if (array_key_exists($name, $indexTree) && $indexTree[$name] !== $hash) {
					$diff[] = [
						'name' => $name,
						'old' => $this->store->readObject($indexTree[$name]),
						'new' => $this->workCopy->read($name),
					];
				}
			}
		}

		return $diff;
	}
}