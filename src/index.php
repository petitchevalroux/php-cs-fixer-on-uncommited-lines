#!/bin/php
<?php

// Source file to parse
$sourceFile = $argv[1];
// Get uncommited lines number
$exec = exec('git blame '.escapeshellarg($sourceFile).' | egrep -oh \'^00000000.*?([0-9]+)\)\' | egrep -oh \'([0-9]+)\)$\' | sed -e \'s/)//g\'', $uncommitedLines);
// Generate a fixed copy of source file
$fixedFile = tempnam(sys_get_temp_dir(), '');
copy($sourceFile, $fixedFile);
exec('php-cs-fixer fix '.escapeshellarg($fixedFile));
// Compute diff between files
$diffCmd = 'diff -u '.escapeshellarg($sourceFile).' '.escapeshellarg($fixedFile);
$patch = shell_exec($diffCmd);
echo $diffCmd.":\n$patch\n";
// Blame start line at 1, unlike diff
foreach ($uncommitedLines as $k => $v) {
    --$uncommitedLines[$k];
}
$countUncommitedLines = count($uncommitedLines);
echo 'Uncommited lines: '.implode(',', $uncommitedLines)."\n";
// We got a diff
if (!empty($patch)) {
    $patches = preg_split('~^@@~m', $patch);
    $finalPatch = $patches[0];
    array_shift($patches);
    $countPatches = count($patches);
    $applyFinalPatch = false;
    // each chunk
    for ($j = 0; $j < $countPatches; ++$j) {
        $patch = $patches[$j];
        // If the chunk contains an uncommited line
        // add it to the final patch
        if (preg_match('~^\s*-([0-9]+),([0-9]+)~', $patch, $matches)) {
            $startPatch = $matches[1];
            $endPatch = $startPatch + $matches[2];
            for ($i = 0; $i < $countUncommitedLines; ++$i) {
                $line = $uncommitedLines[$i];
                if ($startPatch <= $line && $line <= $endPatch) {
                    $finalPatch .= '@@'.$patch;
                    $i = $countUncommitedLines;
                    $applyFinalPatch = true;
                }
            }
        }
    }
    echo "Final patch:\n$finalPatch";
    // Apply final patch on the sourceFile if needed
    if ($applyFinalPatch) {
        $patchFile = tempnam(sys_get_temp_dir(), '');
        file_put_contents($patchFile, $finalPatch);
        shell_exec('patch '.escapeshellarg($sourceFile).' < '.escapeshellarg($patchFile));
        unlink($patchFile);
    }
} else {
    echo "Empty diff\n";
}
unlink($fixedFile);
