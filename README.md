obd
===

OBD-II class for vehicle self-diagnostics, written in PHP

Usage
====

Connect an OBD-II device using serial interface, and point the class at its device file location.  Optionally, a baud rate and verbosity may be specified.

```php
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
```
