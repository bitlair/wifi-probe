<?php
/**
 * wifi.probe.php
 */
if (isset($argv[3])) {
        $node_name = $argv[3];
}
else {
        $node_name = "test-node";
}

include("config.php");
include("common.php");

$_dev = $argv[1];
if ($_dev != "wlan0" && $_dev != "wlan1") {
	_l("Please supply a WLAN interface",true);
}

if (isset($argv[2]) && ($argv[2] == "2ghz" || $argv[2] == "5ghz")) {
	$_band = $argv[2];
	_l("Band restricted to {$_band}");
}
else {
	$_band = "all";
}

set_country_code();
kill_dhcp();
kill_rdisc6();

_l("Starting WiFi probe on {$_dev}");

// get wpa_cli status (will also start wpa_supplicant if not running on interface)
getStatus();

// flush & get status again
wpa_flush();
$status = getStatus();
_l("wpa_state = " . $status["wpa_state"]);

// do initial scan
if ($status["wpa_state"] == "DISCONNECTED" || $status["wpa_state"] == "INACTIVE" || $status["wpa_state"] == "COMPLETED" || $status["wpa_state"] == "SCANNING") {
	$networks = iw_scan();
}
else {
	_l("Unknown wpa_state, exitting",true);	
}

// add configured networks via wpa_cli
add_networks();

// wget interval
$t_wget_4 = array();
$t_wget_6 = array();

// get BSSID list
$_last_bssid_get = 0;
$_bssid_ap = get_bssid_list();


// main loop
while (true) {
	// init graphite
	$graphite_fsock = initGraphite();

	// iterate through configured networks
	for ($i = 0; $i < count($_cfg["networks"][$_band]); $i++) {
		$ssid = str_replace("\\", "", str_replace("\"", "", $_cfg["networks"][$_band][$i]["ssid"]));

		// iterate through found BSSID's
		if (!is_array($networks[$ssid])) {
			_l("No BSSID found for SSID {$ssid}, skipping...");
			continue;
		}

		ksort($networks[$ssid]);

		foreach ($networks[$ssid] as $net) {
			
			watchdog_update();

			$bssid_clean = str_replace(":", "", $net["bssid"]);
			$ap_name = $_bssid_ap[$net["bssid"]];

			if (intval($net["signal"]) < $_cfg["min_signal"]) {
				_l("Skipping SSID {$ssid} on BSSID {$net["bssid"]} AP {$ap_name} @ {$net["freq"]} - signal {$net["signal"]} (signal too low)");
				continue;
			}

			if ($_band != "all" && (($_band == "2ghz" && intval($net["freq"]) > 2500) || ($_band == "5ghz" && intval($net["freq"]) < 4900))) {
				_l("Skipping SSID {$ssid} on BSSID {$net["bssid"]} AP {$ap_name} @ {$net["freq"]} - signal {$net["signal"]} (frequency not in band {$_band})");
				continue;
			}

			_l("Connecting to SSID {$ssid} on BSSID {$net["bssid"]} AP {$ap_name} @ {$net["freq"]} - signal {$net["signal"]}");
			set_bssid($i, $net["bssid"]);
			enable_network($i);

			// getStatus will wait until connected (or timed out)
			$status = getStatus(true);
			if ($status["wpa_state"] != "COMPLETED") {
				_l("FAILED wpa_state = {$status["wpa_state"]}, skipping...");
				sendGraphite("connection_failed", 1);
				sendGraphite("connection_success", 0);
				continue;
			}

			sendGraphite("connection_failed", 0);
			sendGraphite("connection_success", 1);
			_l("wpa_state = {$status["wpa_state"]}");

			// get signal
			$signal = signal_poll();
			sendGraphite("signal", $signal["RSSI"]);
			sendGraphite("noise", $signal["NOISE"]);
			sendGraphite("frequency", $signal["FREQUENCY"]);
			_l("Signal = {$signal["RSSI"]} - Noise = {$signal["NOISE"]}");		

			// get logs after connecting
			$logs = parse_logs($net["bssid"]);
			foreach ($logs as $field => $value) {
				sendGraphite("setup." . $field, $value);
			}

			_l("Assoc/auth/EAP stats: key_neg = {$logs["key_neg"]} ms, eap = {$logs["eap"]} ms, assoc = {$logs["assoc"]} ms, auth = {$logs["auth"]} ms");

			// get dhcp
			if ($_cfg['ip_mode'] == "dualstack" || $_cfg['ip_mode'] == "ipv4-only") {
				$dhcp = get_dhcp();

				if ($dhcp['ip'] == "") {
					_l("Got no DHCP, skipping");
					sendGraphite("dhcp_failed", 1);
					sendGraphite("dhcp_success", 0);
					$dhcp_failed = true;
					if ($_cfg['ip_mode'] == "ipv4-only") {
						continue;
					}
				}

	                        sendGraphite("dhcp_failed", 0);
        	                sendGraphite("dhcp_success", 1);
				sendGraphite("dhcp_time", $dhcp["dhcp_time"]);
				$dhcp_failed = false;
				_l("DHCP done, IP = {$dhcp["ip"]}, subnet = {$dhcp["subnet"]}, gateway = {$dhcp["router"]}, took {$dhcp["dhcp_time"]} ms");
			}

			// get IPv6
			if ($_cfg['ip_mode'] == "dualstack" || $_cfg['ip_mode'] == "ipv6-only") {  
				$rdisc6 = get_rdisc6();			
				if ($rdisc6["IPAddress"] == "") {
                                	_l("Got no Rdisc6, skipping");
                                	sendGraphite("rdisc6_failed", 1);
                                	sendGraphite("rdisc6_success", 0);
					$rdisc6_failed = true;
					if ($_cfg['ip_mode'] == "ipv6-only") {
                                		continue;
					}
				}

                        	sendGraphite("rdisc6_failed", 0);
                        	sendGraphite("rdisc6_success", 1);
                        	sendGraphite("rdisc6_time", $rdisc6["rdisc6_time"]);
				$rdisc6_failed = false;
				_l("Rdisc6 done, IP {$rdisc6["IPAddress"]}, Gateway = {$rdisc6["Gateway"]}, took {$rdisc6["rdisc6_time"]} ms");
			}

			// IPv4 tests
			if ( ($_cfg['ip_mode'] == "dualstack" || $_cfg['ip_mode'] == "ipv4-only") && !$dhcp_failed && ($_band == $_cfg['wget_band'] || $_cfg['wget_band'] == "both") ) { 
				// do wget test IPv4
				if (!isset($t_wget_4[$net["bssid"]]) || ( (time() - $t_wget_4[$net["bssid"]]) >= $_cfg["wget_interval"])) { 
					$wget = wget($dhcp["ip"], 4);
					$t_wget_4[$net["bssid"]] = time();
					sendGraphite("wget_speed_v4",$wget);
					_l("wget results: {$wget} Mbit/s");
				}

                        	// do ping tests IPv4                                                                                                                                             
                        	$ping_list = array($dhcp["router"], $_cfg["ping_host_v4"]);                                                                                                       
                                                                                                                                                                                          
                        	foreach ($ping_list as $host) {                                                                                                                                   
                                	$ping = ping($host, $dhcp["ip"]);                                                                                                                         
                                	$ping_log = "";                                                                                                                                           
                                	foreach ($ping as $metric => $value) {                                                                                                            
                                        	sendGraphite("ping_v4." . str_replace(".","_",$host) . "." . $metric, $value);                                                            
                                        	$ping_log .= $metric . " = " . $value . " ";                                                                                              
                                	}                                                                                                                                                 
                                	_l("Ping results: {$ping_log}");                                                                                                                  
                        	}

				kill_dhcp();                                                                                        
			}

                        // IPv6 tests
			if ( ($_cfg['ip_mode'] == "dualstack" || $_cfg['ip_mode'] == "ipv6-only") && !$rdisc6_failed && ($_band == $_cfg['wget_band'] || $_cfg['wget_band'] == "both") ) { 
                        	// do wget test IPv6
				if (!isset($t_wget_6[$net["bssid"]]) || ( (time() - $t_wget_6[$net["bssid"]]) >= $_cfg["wget_interval"])) {
                                	$wget = wget($rdisc6["IPAddress"], 6);
                                	$t_wget_6[$net["bssid"]] = time();
                                	sendGraphite("wget_speed_v6",$wget);
                                	_l("wget results: {$wget} Mbit/s");
                        	}

                        	// do ping tests IPv6                                                                                                                                             
                        	$ping_list = array($rdisc6["Gateway"] . "%" . $_dev, $_cfg["ping_host_v6"]);                                                                                                    
                                                                                                                                                                                          
                        	foreach ($ping_list as $host) {                                                                                                                                   
                                	$ping = ping($host, $rdisc6["IPAddress"], 6);                                                                                                             
                                	$ping_log = "";                                                                                                                                           
                                	foreach ($ping as $metric => $value) {                                                                                                            
                                        	sendGraphite("ping_v6." . str_replace(".","_",str_replace("%" . $_dev, "", $host)) . "." . $metric, $value);                                                            
                                        	$ping_log .= $metric . " = " . $value . " ";                                                                                              
                                	}                                                                                                                                                 
                                	_l("Ping results: {$ping_log}");                                                                                                                  
                        	}
			
				kill_rdisc6();                                                                                                          
			}

			_l("Done, disabling {$ssid} BSSID {$net["bssid"]}");
			disable_Network($i);
		}

	}
	$bssid_clean = "";

	_l("End of cycle, re-scanning...");
	$networks = iw_scan();

	// check for BSSID list
	$_bssid_ap = get_bssid_list();

        // close graphite                                                                                                                                        
        closeGraphite($graphite_fsock);                  

	sleep(5);
}
?>
