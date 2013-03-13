<?php
	// obd demo
	require_once __DIR__ . "/vendor/autoload.php";
	use \obd\obd as obd;

	// Open serial connection
	$obd = new obd("/dev/pts/1", obd::BAUD_FAST);

	// Run a few commands
	$commands = array("AT I", "AT RV", "AT DP");
	foreach ($commands as $c)
	{
		$r = $obd->command($c);
		printf("%s -> %s\n", $c, $r);
	}
