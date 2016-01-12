#!/bin/ash
#
# Staging script 
#

cp files/firewall /etc/config/firewall
cp files/network /etc/config/network
#cp files/id_rsa ~/.ssh
#cp files/id_rsa.pub ~/.ssh
cp files/rt_tables /etc/iproute2
chmod 0700 ~/.ssh
chmod 0600 ~/.ssh/*

opkg update
opkg remove wpad-mini
for i in ca-certificates rdisc6 wget screen php5-cli zoneinfo-core oping lldpd kmod-ath9k kmod-ath10k iw wpa-supplicant wpa-cli php5-mod-openssl; do
	opkg install $i
done

uci set system.@system[0].log_file='/var/log/syslog'
#uci set system.@system[0].log_ip='1.1.1.1'
if [ "$(uci get lldpd.config.interface|grep eth0)" = "" ];then
	uci add_list lldpd.config.interface='eth0'
fi
if [ "$(uci get lldpd.config.interface|grep br-lan)" = "" ];then
	uci add_list lldpd.config.interface='br-lan'
fi
uci commit

id
