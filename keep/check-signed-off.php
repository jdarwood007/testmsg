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

/*
 * Enable debugging of the script.
 */
define('DEBUG_MODE', false);

/*
 * All the strings we search for.
 */
define('SIGN_OFF_STRINGS', ['Signed-off-by:', 'Signed by']);

try {
	writeDebug('# Check Sign off');

	$result = parseCommit('HEAD');

	writeDebug('---', '### Result');

	if ($result !== '') {
		writeDebug('Found a valid sign off in commit: ' . $result);
	} else {
		fwrite(STDERR, 'No valid sign off was found in your commit, please sign off commits.');
	}

	// Send debugging information out.
	if ((bool) DEBUG_MODE === true) {
		writeDebug('---', '### Debugging information', '```');
		print_r($GLOBALS['debugger']);
		writeDebug('```');
	}

	exit(0);
} catch (Exception $e) {
	fwrite(STDERR, $e->getMessage());

	// This is a fatal error, exit so CI stops.
	exit(1);
}

function parseCommit(string $hash, int $level = 0): string
{
	global $debugger;
	static $regex = '';

	$hash = trim($hash ?? '');

	// No hash found, abort.
	if (strlen($hash) === 0) {
		writeDebug('## ERROR', 'Received invalid git hash');

		return '';
	}
		writeDebug('## Checking: ' . $hash);


	// Build the regex up.
	if (empty($regex)) {
		$regex = '~(' . implode('|', array_map('preg_quote', (array) SIGN_OFF_STRINGS)) . ')~i';
	}

	// For the debugger.
	$debugger[$hash] ??= [];
	$data = &$debugger[$hash];
	$data = [
		'msg' => gitCmd('log --graph --abbrev-commit --decorate --first-parent --no-merges -n1 --show-signature ' . $hash),
		'hash' => gitCmd('show -s --format=%H ' . $hash),
		'tree' => gitCmd('show -s --format=%T ' . $hash),
		'parent' => gitCmd('show -s --format=%P ' . $hash),
	];

	// First, try just the message.
	$data['lines'] = explode("\n", trim(str_replace("\r", "\n", $data['msg'])));
	$data['lastline'] = $data['lines'][count($data['lines']) - 1];

	if (preg_match($regex, $data['lastline'], $match)) {
		writeDebug('*Found sign off in message*');
		$data['matched'] = $match[1];

		return $hash;
	}

	// See if it contains a GPG signing.
	if (preg_match('~^\| gpg:\s+using([^\r\n]+)~im', $data['msg'], $match)) {
		writeDebug('*Found GPG header*');
		$data['matched'] = $match[1];

		return $hash;
	}

	/*
	 * DO NOT CHECK MERGES
	 * Merges may contain commits not from the author.
	 * Merges may be a main branch merge or rebase.
	 *
	 * DO NOT CHECK PARENTS
	 * The last commit by the author (non merge) is
	 * the only commit that needs checked.
	 *
	 * This code is left in here for experimental purposes.
	*/

	/*
	// Do we have a merge?
	if (preg_match('~Merge ([A-Za-z0-9]{40}) into ([A-Za-z0-9]{40})~i', $data['lastline'], $merge)) {
		writeDebug('*Merge Header found, checking*');

		// Recrusion threshold.
		if ($level > 10) {
			writeDebug('*Recurusion limit reached and we still have more merges to check*');
			return '';
		}

		$data['merge'] = $merge;

		$result = parseCommit($merge[1], $level + 1);

		if ($result !== '') {
			writeDebug('*Merge check successfull*');
			return $result;
		}
	}

	// Seek parents.
	if ($data['parent'] !== '') {
		writeDebug('*Checking parents*');
		$data['parents'] = explode(' ', $data['parent']);

		$result = '';
		foreach ($data['parents'] as $parent_hash) {
			writeDebug('*Parsing parent commit ' . $parent_hash . '*');
			$result = parseCommit($parent_hash, $level + 1);

			if ($result !== '') {
				writeDebug('*Parent check successfull*');
				return $result;
			}
		}
	}
	*/

	writeDebug('*Compelted checks, no sign offs found*');

	return '';
}

/*
 * Handles writing a debug message only when we have it enabled
 */
function writeDebug(...$msg): void
{
	if ((bool) DEBUG_MODE === true) {
		echo implode("\n", array_map('strval', (array) $msg)), "\n";
	}
}

/*
 * Shows just the stub
 */
function getStub(string $commit): string
{
	return substr($commit, 0, 7);
}

/*
 * Clean up our exec process.
 */
function gitCmd(string $command, bool $no_pager = true): string
{
	return trim(shell_exec('git ' . ($no_pager ? ' --no-pager ' : '') . $command . ' 2>&1') ?? '');
}
