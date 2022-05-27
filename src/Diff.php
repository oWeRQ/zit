<?php

namespace Zit;

class Diff
{
	public function compare(array $old, array $new)
	{
		$lines = [];

		$oldLen = count($old);
		$newLen = count($new);
		$oldIdx = 0;
		$newIdx = 0;

		while ($oldIdx < $oldLen || $newIdx < $newLen) {
			if (!array_key_exists($oldIdx, $old) || !array_key_exists($newIdx, $new) || $old[$oldIdx] === $new[$newIdx]) {
				$lines[] = [
					'old' => @$old[$oldIdx++],
					'new' => @$new[$newIdx++],
				];
			} else {
				$maxOffset = max($oldLen - $oldIdx, $newLen - $newIdx);
				for ($offset = 1; $offset < $maxOffset; $offset++) {
					$oldOffset = array_search(@$new[$newIdx + $offset], array_slice($old, $oldIdx, $offset + 1), true);
					$newOffset = array_search(@$old[$oldIdx + $offset], array_slice($new, $newIdx, $offset + 1), true);
					if ($oldOffset !== false || $newOffset !== false) {
						if ($oldOffset === false) {
							$oldOffset = $offset;
						}

						if ($newOffset === false) {
							$newOffset = $offset;
						}

						for ($i = 0; $i < $oldOffset; $i++) {
							$lines[] = [
								'old' => $old[$oldIdx++],
								'new' => null,
							];
						}

						for ($i = 0; $i < $newOffset; $i++) {
							$lines[] = [
								'old' => null,
								'new' => $new[$newIdx++],
							];
						}

						break;
					}
				}

				if ($offset === $maxOffset) {
					while ($oldIdx < $oldLen) {
						$lines[] = [
							'old' => $old[$oldIdx++],
							'new' => null,
						];
					}

					while ($newIdx < $newLen) {
						$lines[] = [
							'old' => null,
							'new' => $new[$newIdx++],
						];
					}
				}
			}
		}

		return $lines;
	}

	public function apply(array $old, array $lines)
	{
		$new = [];

		$offset = 0;
		foreach ($lines as $i => $line) {
			$oldLine = $old[$i + $offset];
			if ($line['old'] === null || $line['old'] === $oldLine || $line['new'] === $oldLine) {
				if ($line['old'] === null) {
					$offset--;
				}

				if ($line['new'] !== null) {
					$new[] = $line['new'];
				}
			}
		}

		for ($j = $i; $j < count($old); $j++) {
			$new[] = $old[$j];
		}

		return $new;
	}

	public function merge(array $common, array $a, array $b)
	{
		$aDiff = $this->compare($common, $a);
		$bDiff = $this->compare($common, $b);
		return $this->apply($this->apply($common, $aDiff), $bDiff);
	}

	public function toString(array $lines)
	{
		$str = '';

		foreach ($lines as $line) {
			$old = $line['old'];
			$new = $line['new'];
			if ($old === $new) {
				$str .= " $new\n";
			} else {
				if ($old !== null) {
					$str .= "-$old\n";
				}
				if ($new !== null) {
					$str .= "+$new\n";
				}
			}
		}

		return $str;
	}

	public function print(array $lines)
	{
		echo $this->toString($lines);
	}
}