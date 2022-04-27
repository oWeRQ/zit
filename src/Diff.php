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
			$oldLine = $old[$oldIdx] ?? null;
			$newLine = $new[$newIdx] ?? null;
			if ($oldLine !== null && $newLine !== null && $oldLine !== $newLine) {
				for ($j = 0; $j < $maxLen - $i; $j++) {
					$oldDist = array_search($new[$newIdx + $j] ?? null, array_slice($old, $oldIdx + $j), true) + $j;
					$newDist = array_search($old[$oldIdx + $j] ?? null, array_slice($new, $newIdx + $j), true) + $j;

					if ($oldDist || $newDist) {
						if ($oldDist) {
							for ($k = 0; $k < $oldDist; $k++) {
								$lines[] = [
									'old' => $old[$oldIdx + $k] ?? null,
									'new' => null,
								];
							}
						}

						if ($newDist) {
							for ($k = 0; $k < $newDist; $k++) {
								$lines[] = [
									'old' => null,
									'new' => $new[$newIdx + $k] ?? null,
								];
							}
						}

						$oldOffset += $oldDist - 1;
						$newOffset += $newDist - 1;

						break;
					}
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

	public function print(array $lines)
	{
		foreach ($lines as $line) {
			$old = $line['old'];
			$new = $line['new'];
			if ($old === $new) {
				echo " $new\n";
			} else {
				if ($old !== null) {
					echo "-$old\n";
				}
				if ($new !== null) {
					echo "+$new\n";
				}
			}
		}
	}
}