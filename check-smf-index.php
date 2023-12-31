<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

$ignoreIndexFiles = [
	'\.\/\.',
	'\./other',
	'\./vendor',
];

$contents = <<<END
<?php

// Try to handle it with the upper level index.php. (it should know what to do.)
if (file_exists(dirname(__DIR__) . '/index.php'))
	include (dirname(__DIR__) . '/index.php');
else
	exit;

?>
END;

$errors = false;

try
{
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator('.', RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST,
		RecursiveIteratorIterator::CATCH_GET_CHILD
	);

	foreach ($iter as $currentDirectory => $dir) {
		if (!$dir->isDir())
			continue;

		foreach ($ignoreIndexFiles as $if)
			if (preg_match('~' . $if . '~i', $currentDirectory))
				continue 2;

		if (!file_exists($currentDirectory . '/index.php'))
		{
			$errors = true;
			print('Index file missing in ' . $currentDirectory . "\r");
		}
		else if (file_get_contents($currentDirectory . '/index.php') != $contents)
		{
			$errors = true;
			print('Index content does not match in ' . $currentDirectory . "\r");
		}
	}

	exit(0);
}
catch (Exception $e)
{
	fwrite(STDERR, $e->getMessage() . 'STDERR');
	fwrite(STDOUT, $e->getMessage()) . 'STDOUT';
	print($e->getMessage()) . 'PRINT';
	exit(0);
}