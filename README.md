obd
===

ELM327 OBD-II class for vehicle self-diagnostics, written in PHP

Installation
============

To install using Composer, add `"mdlayher/obd": "dev-master"` to the `require` section of your `composer.json`.

Usage
=====

Connect an ELM327 OBD-II device using serial interface, and point the class at its device file location.  Optionally, a baud rate, preferred unit system, and verbosity may be specified.

```php
<?php
	// obd demo
	require_once __DIR__ . "/vendor/autoload.php";
	use \obd\obd as obd;

	// Open serial connection
	$obd = new obd("/dev/pts/1", obd::BAUD_FAST);
	$obd->connect();

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
```
