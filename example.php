<?php
	// obd demo
	require_once __DIR__ . "/vendor/autoload.php";
	use \obd\obd as obd;

	// Open serial connection
	$obd = new obd("/dev/pts/1", obd::BAUD_FAST);

	// Run a few basic commands
	$commands = array("AT I", "AT RV", "AT DP");
	printf("Basic commands:\n");
	foreach ($commands as $c)
	{
		printf("\t%s -> %s\n", $c, $obd->command($c));
	}

	// Call all parameter ID (PID) functions using English units
	printf("English:\n");
	foreach ($obd->pid as $key => $p)
	{
		printf("\t%s: %s\n", $key, $p());
	}

	// Switch to Metric and call a few functions
	$obd->set_units(obd::UNIT_METRIC);
	printf("Metric:\n");
	printf("\tfuel_pressure: %s\n", $obd->pid['fuel_pressure']());
	printf("\tspeed: %s\n", $obd->pid['speed']());
