<?php

namespace Zit;

class Diff
{
	public function compare(array $old, array $new)
	{
		$lines = [];

		$oldLen = count($old);
		$newLen = count($new);
		$maxLen = max($oldLen, $newLen);
		$oldOffset = 0;
		$newOffset = 0;

		for ($i = 0; $i < $maxLen - max($oldOffset, $newOffset); $i++) {
			$oldIdx = $i + $oldOffset;
			$newIdx = $i + $newOffset;
			$oldLine = $old[$oldIdx];
			$newLine = $new[$newIdx];
			if ($oldLine !== null && $newLine !== null && $oldLine !== $newLine) {
				for ($j = 0; $j < $maxLen - $i; $j++) {
					$oldDist = isset($new[$newIdx + $j]) ? array_search($new[$newIdx + $j], array_slice($old, $oldIdx + $j), true) : false;
					$newDist = isset($old[$oldIdx + $j]) ? array_search($old[$oldIdx + $j], array_slice($new, $newIdx + $j), true) : false;

					if ($oldDist !== false || $newDist !== false) {
						$oldDist += $j;
						$newDist += $j;

						for ($k = 0; $k < $oldDist; $k++) {
							$lines[] = [
								'old' => $old[$oldIdx + $k],
								'new' => null,
							];
						}

						for ($k = 0; $k < $newDist; $k++) {
							$lines[] = [
								'old' => null,
								'new' => $new[$newIdx + $k],
							];
						}

						$oldOffset += $oldDist - 1;
						$newOffset += $newDist - 1;

						break;
					}
				}

				if ($j === $maxLen - $i) {
					$lines[] = [
						'old' => $oldLine,
						'new' => $newLine,
					];
				}
			} else {
				$lines[] = [
					'old' => $oldLine,
					'new' => $newLine,
				];
			}
		}

		return $lines;
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