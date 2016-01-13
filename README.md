# WARNING
This project is in an alpha state; most code is "hacky" and is poorly documented. Please join #bitlair on irc.smurfnet.ch if you have any questions.

# WiFi-probe
This project is a collection of scripts that enables automated WiFi testing using the OpenWRT platform with wpa_supplicant. Test results are submitted to Graphite/Carbon.

A WiFi-probe acts as a WiFi-client, it will scan for nearby BSSID's for the configured ESSID, it will then sequently connect to these BSSID's and preform automated tests.

For a list of metrics WiFi-probe collects see: http://koopen.net/ccc/wifi-probe/probe_metrics.png

# Deployment at 32C3
At the 32C3 Congress we've deployed WiFi-probes using TP-Link Archer C7 v2. The WiFi-probes are connecting to the WiFi network using 802.1X EAP-TTLS PAP (other modes have not yet been tested). The following URL contains the packages we've used for these devices:

http://skelter.nikhef.nl/openwrt-bin-archer/ar71xx/

Shell scripts we've used for staging are included in this repo (do note: these scripts need to be modified before they can be used).

The WiFi-probes were connected to the wired network for management and the submission of test results. 
For IP connectivity tests on the WiFi-side seperate routing-tables were used, this way the OpenWRT device could hold multiple default gateways which did not conflict with each other.

For tests results from 32C3 see: http://koopen.net/ccc/wifi-probe/ (this also shows the combination of Graphite/Carbon metrics from the Aruba Graphite script: https://github.com/bitlair/aruba/)
