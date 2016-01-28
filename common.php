<?php
/**
 * common.php
 */
//openlog("wifi-probe-".$argv[1], LOG_PID | LOG_PERROR, LOG_LOCAL0);

function _l($msg, $fatal = false) {
	global $bssid_clean, $ssid;
	echo @date("d-m-Y H:i:s") . " :: [{$ssid}][{$bssid_clean}] " . $msg . "\n";

	//syslog(LOG_INFO, "[{$ssid}][{$bssid_clean}] " . $msg . "\n");
	if ($fatal) {
		exit;
	}
}

function sendCommand($command) {
	_l("EXEC: {$command}");
	ob_start();
	passthru($command);
	$out = ob_get_contents();
	ob_end_clean();

	return $out;	
}

function iw_scan() {
	global $_dev;

	$out = sendCommand("iw dev {$_dev} scan");
	$t = explode("\n", $out);
	$networks = array();
	$net = array();

	foreach ($t as $line) {
		if (preg_match("/(BSS )(.+?)(\(on)/i",$line,$out)) {
			if (count($net) > 0) {
                                $networks[$ssid][$bssid] = $net;
                        }
			$bssid = str_replace(":", "", $out[2]);	
			$net["bssid"] = $out[2];
		}
		elseif (preg_match("/(SSID\: )(.*)/i",$line,$out)) {
			$ssid = $out[2];
		}
                elseif (preg_match("/(signal\: )(.+?)( dBm)/i",$line,$out)) {
                        $net["signal"] = $out[2];
                }
                elseif (preg_match("/(freq\: )(.*)/i",$line,$out)) {
                        $net["freq"] = $out[2];
                }



	}
	$networks[$ssid][$bssid] = $net;
	return $networks;	
}

function start_wpa_supplicant() {
	global $_dev, $_cfg;
	
	sendCommand("rm -rf {$_cfg["log_dir"]}{$dev}.log");
	sendCommand("wpa_supplicant -i {$_dev} -C {$_cfg["socket_dir"]}wpa_supplicant_{$_dev} -t -B -f {$_cfg["log_dir"]}{$_dev}.log");
}

function wpa_cli($command) {
	global $_dev;
	$out = sendCommand("wpa_cli -p /tmp/wpa_supplicant_{$_dev} {$command} 2>&1");
	return $out;
}

function getStatus($wait = false, $i = 0) {
	global $_dev;

	$t = wpa_cli("status");

	if (trim($t) == "" || preg_match("/(Failed to connect)/i",$t)) {
		_l("wpa_supplicant not running, trying to start...");
		start_wpa_supplicant();
		sleep(1);
		return getStatus();
	}
	else {
		$v = parseVars($t);

		if ($wait) {
			if ($v["wpa_state"] != "COMPLETED") {
				_l("wpa_state = {$v["wpa_state"]} - retrying");
				sleep(1);
				$i++;
				if ($i >= 20) return $v;
				else return getStatus(true, $i);
			}
			else {
				return $v;
			}
		}
		else {
			return $v;
		}
	}

}

function parseVars($in) {
	$vars = array();
	$in = explode("\n",$in);
	foreach ($in as $line) {
		$tmp = explode("=",$line);
		if (count($tmp) > 1) {
			$vars[$tmp[0]] = $tmp[1];
		}
	}
	return $vars;
}

function wpa_flush() {
	wpa_cli("flush");
	sleep(1);
}

function add_networks() {
	global $_cfg, $_band;
	
	if (isset($_cfg["networks"][$_band])) {
		foreach ($_cfg["networks"][$_band] as $index => $settings) {
			wpa_cli("add_network {$index}");
			foreach ($settings as $setting => $value) {
				wpa_cli("set_network {$index} {$setting} {$value}");
			}
		}
	}
}

function disable_network($id) {
	wpa_cli("disable {$id}");	
}

function enable_network($id) {
	wpa_cli("enable {$id}");
	sleep(1);
}

function set_bssid($id, $bssid) {
	wpa_cli("set_network {$id} bssid_whitelist {$bssid}");
}

function signal_poll() {
	return parseVars(wpa_cli("signal_poll"));
}

function parse_logs($match_bssid) {
	global $_dev, $_cfg;

	$f = explode("\n", sendCommand("tail -n 200 {$_cfg["log_dir"]}{$_dev}.log"));
	$matched = false;

	for ($i = (count($f)-1); $i >= 0; $i--) {
		if (!$matched && preg_match("/(.*)(\: )({$_dev}\: )(CTRL-EVENT-CONNECTED - Connection to {$match_bssid} completed)/i", $f[$i], $out)) {
			$t_connected = floatval($out[1]);
			$matched = true;
		} 
		elseif ($matched) {
			if (preg_match("/(.*)(\: )({$_dev}\: )(WPA\: Key negotiation completed with {$match_bssid})/i", $f[$i], $out)) {
				$t_key_negotiated = floatval($out[1]);
			}
			elseif (preg_match("/(.*)(\: )({$_dev}\: )(CTRL-EVENT-EAP-SUCCESS)/i", $f[$i], $out)) {
				$t_eap_success = floatval($out[1]);
			}
			elseif (preg_match("/(.*)(\: )({$_dev}\: )(CTRL-EVENT-EAP-STARTED)/i", $f[$i], $out)) {
				$t_eap_start = floatval($out[1]);
			}
			elseif (preg_match("/(.*)(\: )({$_dev}\: )(Associated with {$match_bssid})/i", $f[$i], $out)) {
				$t_assoc_end = floatval($out[1]);
			}
                        elseif (preg_match("/(.*)(\: )({$_dev}\: )(Trying to associate with {$match_bssid})/i", $f[$i], $out)) {
                                $t_assoc_start = floatval($out[1]);
                        }
                        elseif (preg_match("/(.*)(\: )({$_dev}\: SME\: )(Trying to authenticate with {$match_bssid})/i", $f[$i], $out)) {
                                $t_auth_start = floatval($out[1]);
				break;
                        }
		}
	}
	
	return array (
		"total" => ($t_connected - $t_auth_start) * 1000,
		"key_neg" => ($t_key_negotiated - $t_eap_success) * 1000,
		"eap" => ($t_eap_success - $t_eap_start) * 1000,
		"assoc" => ($t_assoc_end - $t_assoc_start) * 1000,
		"auth" => ($t_assoc_start - $t_auth_start) * 1000
	);
}

function kill_dhcp() {
	global $_dev;
	sendCommand("pkill -f udhcpc.+{$_dev}");
}

function get_dhcp() {
	global $_dev, $_cfg;
	$start = microtime(TRUE);
	$tmp = parseVars(sendCommand("udhcpc -i {$_dev} -s {$_cfg["app_dir"]}udhcpc.script -b -S -R"));
	$end = microtime(TRUE);
	$tmp["dhcp_time"] = ($end - $start) * 1000;

	return $tmp;
}

function get_rdisc6() {
	global $_dev, $_cfg;
	$start = microtime(TRUE);
	$tmp = parseVars(sendCommand("{$_cfg["app_dir"]}configure-ipv6.sh {$_dev}"));
	$end = microtime(TRUE);
	$tmp["rdisc6_time"] = ($end - $start) * 1000;

	return $tmp;
}

function kill_rdisc6() {
	global $_cfg, $_dev;
	sendCommand("{$_cfg["app_dir"]}deconfigure-ipv6.sh {$_dev}");
}

function ping($host, $src, $ip_v = 4) {
	if (!preg_match("/(fe80\:\:)/i",$host)) {
		$src = " -I {$src}";
	}
	else {
		$src = "";
	}
	$tmp = explode("\n", sendCommand("oping -{$ip_v}{$src} -i 0.3 -c 30 {$host}"));
	$ping = array();

	for ($i = (count($tmp)-1); $i >= 0; $i--) {
		if (preg_match("/(rtt min)/i", $tmp[$i])) {
			$l = explode("/",$tmp[$i]);
			$ping["min"] = str_replace("sdev = ","",$l[3]);
			$ping["avg"] = $l[4];
			$ping["max"] = $l[5];
			$ping["sdev"] = str_replace(" ms","",$l[6]);
		}
		elseif (preg_match("/(.*)( packets transmitted, )(.*)( received, )(.*)(% packet loss, time )(.*)(ms)/i",$tmp[$i],$out)) {
			$ping["packets_sent"] = $out[1];
			$ping["packets_received"] = $out[3];
			$ping["packets_lost"] = $out[1] - $out[3];
			$ping["loss"] = $out[5];
			$ping["time"] = $out[7];
			break;
		}
	}

	return $ping;
}

function wget($src, $ip_v = 4) {
	global $_cfg;

	$tmp = explode("\n", sendCommand("wget {$_cfg["wget_url_v" . $ip_v]} -{$ip_v} -O /dev/null --bind-address={$src} --report-speed=bits --limit-rate={$_cfg["wget_rate_limit"]} 2>&1"));

	for ($i = (count($tmp)-1); $i >= 0; $i--) {
		if (preg_match("/(\()(.*)( Mb\/s\))( - '\/dev\/null' saved)/i",$tmp[$i],$out)) {
			return $out[2];
			break;
		}
	}
}

function initGraphite() {
	global $_cfg;

	if ($_cfg['graphite_send']) {
		return fsockopen($_cfg['graphite_ip'], $_cfg['graphite_port']);
	}
}

function closeGraphite($sock) {
        global $_cfg;                             
                                 
        if ($_cfg['graphite_send']) {
                return fclose($sock);
        }
}        

function sendGraphite($field, $value) {
        global $_cfg, $graphite_fsock, $node_name, $bssid_clean, $ssid, $_band, $ap_name;

	if ($ap_name != "") $ap = $ap_name;
	else $ap = $bssid_clean;

        $send = $_cfg['graphite_prefix'] . $node_name . "." . $ssid . "." . $_band . "." . $ap . "." . $field . " " . $value . " " . time();

        if ($_cfg['graphite_send']) {
                 fwrite($graphite_fsock, $send . "\n", strlen($send . "\n"));
        }

        _l("Graphite send: {$send}");
}

function get_bssid_list() {
	global $_cfg, $_last_bssid_get, $_bssid_ap;

	if ($_last_bssid_get > (time() - $_cfg["bssid_get_interval"])) return $_bssid_ap;

	_l("Updating BSSID list");

	$list = explode("\n", file_get_contents($_cfg['bssid_url']));
	$bssid_ap = array();

	foreach ($list as $line) {
                $tmp = explode(";", $line);
		if (trim($tmp[1]) != "") {
			$bssid_ap[$tmp[0]] = $tmp[1];
		}
	}

	$_last_bssid_get = time();

	return $bssid_ap;
}

function set_country_code() {
	global $_cfg;

	sendCommand("iw reg set {$_cfg["country"]}");	
}

function watchdog_update() {
	global $_cfg, $_dev;

	file_put_contents($_cfg['log_dir'] . $_dev . "watchdog", time());
}

function watchdog_get($dev) {
	global $_cfg;

	return file_get_contents($_cfg['log_dir'] . $dev . "watchdog");
}

function ath10k_stats($dev, $compare = 0) {
	$stats = explode("\n", file_get_contents("/sys/kernel/debug/ieee80211/phy" . str_replace("wlan","",$dev) . "/ath10k/fw_stats"));
	$ret = array();
	$filter = array("peer_mac_address"=>1);
	$avg = array("channel_tx_power"=>1,"peer_rssi"=>1);
	$leave = array("peer_tx_rate"=>1,"peer_rx_rate"=>1);	

	foreach ($stats as $line) {
		if (preg_match("/([a-zA-Z.,() ]+)( [0-9]+)/i",$line,$out)) {
			$field = strtolower(str_replace(" ", "_", str_replace("(","", str_replace(")", "", str_replace(".", "", str_replace(",", "", trim($out[1])))))));
			$val = intval(trim($out[2]));
			if (!isset($filter[$field])) {
				if (is_array($compare)) {
					if (isset($avg[$field])) {
						$ret[$field] = round(($val + $compare[$field]) / 2, 0);
					}
					else if (isset($leave[$field])) {
						$ret[$field] = $val;
					}
					else {
						$ret[$field] = $val - $compare[$field];
					}
				}
				else {
					$ret[$field] = $val;
				}
			}
		}
	}

	return $ret;
	
}
?>
