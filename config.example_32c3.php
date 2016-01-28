<?php
/**
 * rename to config.php
 */

// band could be 5ghz or 2ghz
$_cfg["networks"]["5ghz"][0] = array (
	"ssid" => "\\\"32C3\\\"",
	"key_mgmt" => "WPA-EAP",
	"eap" => "TTLS",
	"identity" => "\\\"noc-{$node_name}-5ghz\\\"",
	"password" => "\\\"test1337\\\"",
	"phase2" => "\\\"auth=PAP\\\"",
        "ca_cert" => "\\\"/root/wifi-probe/ca.pem\\\"",
        "altsubject_match" => "\\\"DNS:radius.c3noc.net\\\""
);

$_cfg["networks"]["2ghz"][0] = array (
        "ssid" => "\\\"32C3-legacy\\\"",
        "key_mgmt" => "WPA-EAP",
        "eap" => "TTLS",
        "identity" => "\\\"noc-{$node_name}-2ghz\\\"",
        "password" => "\\\"test1337\\\"",
        "phase2" => "\\\"auth=PAP\\\"",
        "ca_cert" => "\\\"/root/wifi-probe/ca.pem\\\"",
        "altsubject_match" => "\\\"DNS:radius.c3noc.net\\\""
);

$_cfg["interfaces"]["wlan0"]["type"] = "ath10k";
$_cfg["interfaces"]["wlan1"]["type"] = "ath9k";

$_cfg['country'] = "DE";
$_cfg['min_signal'] = -65;
$_cfg['socket_dir'] = "/tmp/";
$_cfg['log_dir'] = "/tmp/";
$_cfg['app_dir'] = "/root/wifi-probe/";
$_cfg['ip_mode'] = "dualstack"; // dualstack, ipv4-only, ipv6-only
$_cfg['ping_host_v4'] = "radius.c3noc.net";
$_cfg['ping_host_v6'] = "c3noc.net";
$_cfg['wget_band'] = "both";    // 2ghz, 5ghz or both
$_cfg['wget_url_v4'] = "http://wipkip.nikhef.nl/events/CCC/congress/31c3/h264-sd/31c3-6608-en-Premiere_We_love_surveillance_sd.mp4";
$_cfg['wget_url_v6'] = "http://wipkip.nikhef.nl/events/CCC/congress/31c3/h264-sd/31c3-6608-en-Premiere_We_love_surveillance_sd.mp4";
$_cfg['wget_rate_limit'] = "1.8M"; // Megabytes per second
$_cfg['wget_interval'] = 300; // in seconds, foreach BSSID

$_cfg['bssid_url'] = "https://radius.c3noc.net/probe/bssid_list"; 
$_cfg['bssid_get_interval'] = 900;
$_cfg['watchdog_timeout'] = 600;

// Graphite settings
$_cfg['graphite_ip'] = "94.45.226.42";
$_cfg['graphite_port'] = 2003;
$_cfg['graphite_send'] = true;
$_cfg['graphite_prefix'] = "32c3.wifi.probe.";
?>
