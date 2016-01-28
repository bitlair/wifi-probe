<?php
/**
 * watchdog.php
 */

include("config.php");
include("common.php");

foreach ($_cfg["interfaces"] as $int => $row) {
	$timer = intval(watchdog_get($int));

	if ($timer == 0 || (time() - $timer) > $_cfg['watchdog_timeout']) {
		echo "Killing screen for {$int}...\n";
		exec("screen -X -S wifi-probe-{$int} quit");

		echo "Starting screen for {$int}...\n";
		exec("./screen_" . $int . ".sh");
	}	
}
?>
