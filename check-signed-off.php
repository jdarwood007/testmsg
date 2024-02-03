<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0
 */

// Debug stuff.
define('DEBUG_MODE', true);

try
{
	debugPrint("--- DEBUG MSGS START ---");

	// First, lets do a basic test.  This is non GPG signed commits.
	$signedoff = find_signed_off();

	// Now Try to test for the GPG if we don't have a message.
	if (empty($signedoff)) {
		debugPrint("Standard Sign off missing, checking GPG");
		$signedoff = find_gpg();
	}

	// Nothing yet?  Lets ask your parents.
	if (empty($signedoff)) {
		debugPrint("No sign off found, check parents.");
		$signedoff = find_signed_off_parents();
	}

	debugPrint("--- DEBUG MSGS END ---");

	// Nothing?  Well darn.
	if (empty($signedoff)) {
		fwrite(STDERR, 'Error: No valid Sign-off found.  Please Sign your commits.');
		exit(0);
	}

	debugPrint('Valid signed off found');
}
catch (Exception $e)
{
	fwrite(STDERR, $e->getMessage() . 'STDERR');
	fwrite(STDOUT, $e->getMessage()) . 'STDOUT';
	print($e->getMessage()) . 'PRINT';
	exit(0);
}

// Find a commit by Signed Off
function find_signed_off(string $commit = 'HEAD', array $childs = [], int $level = 0): bool
{
	if (empty($commit)) {
		debugPrint('Commit is empty');
		exit(0);
	}

	// Where we are at.
	debugPrint('Attempting to find signed off on commit ' . $commit);

	// To many recrusions here.
	if ($level > 10)
	{
		debugPrint('Recusion limit exceeded on find_signed_off');
		return false;
	}

	// What string tests should we look for?
	$stringTests = ['Signed-off-by:', 'Signed by'];

	// Get message data and clean it up, should only need the last line.
	$message = trim(shell_exec('git show -s --format=%B ' . $commit));
	$lines = explode("\n", trim(str_replace("\r", "\n", $message)));
	$lastLine = $lines[count($lines) - 1];

	// Debug info.
	debugPrint('Testing line "' . $lastLine . '"');

	// loop through each test and find one.
	$result = false;
	foreach ($stringTests as $testedString)
	{
		debugPrint('Testing "' . $testedString . '"');

		$result = stripos($lastLine, $testedString);

		// We got a result.
		if ($result !== false)
		{
			debugPrint('Found "' . $testedString . '"');
			break;
		}
	}

	// Debugger.
	$debugMsgs = [
		'raw body' => '"' . rtrim(shell_exec('git show -s --format=%B ' . $commit)) . '"',
		'body' => '"' . rtrim(shell_exec('git show -s --format=%b ' . $commit)) . '"',
		'commit notes' => '"' . rtrim(shell_exec('git show -s --format=%N ' . $commit)) . '"',
		'ref names' => '"' . rtrim(shell_exec('git show -s --format=%d ' . $commit)) . '"',
		'commit hash' => '"' . rtrim(shell_exec('git show -s --format=%H ' . $commit)) . '"',
		'tree hash' => '"' . rtrim(shell_exec('git show -s --format=%T ' . $commit)) . '"',
		'parent hash' => '"' . rtrim(shell_exec('git show -s --format=%P ' . $commit)) . '"',
		'result' => '"' . $result . '"',
		'testedString' => '"' . $testedString . '"',
	];
	debugPrint('Commit ' . $commit . ' at time ' . time() . ": " . rtrim(print_r($debugMsgs, true)));


	// No result and found a merge? Lets go deeper.
	if ($result === false && preg_match('~Merge ([A-Za-z0-9]{40}) into ([A-Za-z0-9]{40})~i', $lastLine, $merges))
	{
		debugPrint('Found Merge, attempting to get more parent commit: ' . $merges[1]);

		return find_signed_off($merges[1], array_merge(array($merges[1]), $childs), ++$level);
	}

	return $result !== false;
}

// Find a commit by GPG
function find_gpg(string $commit = 'HEAD', array $childs = []): bool
{
	if (empty($commit)) {
		debugPrint('Commit is empty');
		exit(0);
	}

	debugPrint('Attempting to Find GPG on commit ' . $commit);
	$result = false;

	// Get verify commit data.
	$message = trim(shell_exec('git verify-commit ' . $commit . ' --raw 2>&1') ?? '');

	// Should we actually test for gpg results?  Perhaps, but it seems doing that with travis may fail since it has no way to verify a GPG signature from GitHub.  GitHub should have prevented a bad GPG from making a commit to a authors repository and could be trusted in most cases it seems.
	if (strpos($message, 'GOODSIG') !== false && strpos($message, 'VALIDSIG') !== false) {
		debugPrint('We found a valid GPG signature');	
		$result = true;
	}

	// Debugger.
	$debugMsgs = [
		// Raw body.
		'verify-commit' => '"' . rtrim(shell_exec('git verify-commit ' . $commit . ' --raw -v 2>&1') ?? '') . '"',
		// Result.
		'result' => '"' . $result . '"',
		// Last tested string, or the correct string.
		'message' => '"' . $message . '"',
	];
	debugPrint('Commit ' . $commit . ' at time ' . time() . ": " . rtrim(print_r($debugMsgs, true)));

	return $result;
}

// Looks at all the parents, and tries to find a signed off by somewhere.
function find_signed_off_parents(string $commit = 'HEAD'): bool
{
	if (empty($commit)) {
		debugPrint('Commit is empty');
		exit(0);
	}

	$commit = trim($commit);
	$result = false;

	debugPrint('Attempting to find parents on commit ' . $commit);

	$parentsRaw = rtrim(shell_exec('git show -s --format=%P ' . $commit));
	$parents = explode(' ', $parentsRaw);

	// Test each one.
	foreach ($parents as $p)
	{
		$p = trim($p);
		debugPrint('Testing parent of ' . $commit . ' for signed off');

		// Basic tests.
		$result = find_signed_off($p);

		// No, maybe it has a GPG parent.
		if (empty($result)) {
			$result = find_gpg($p);
		}
	}

	// Lucked out.
	return $result;
}

// Print a debug line
function debugPrint($msg)
{
	if (DEBUG_MODE) {
		echo $msg, "\n";
	}
}