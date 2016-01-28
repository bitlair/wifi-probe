#!/bin/ash

cp files/firewall /etc/config/firewall
cp files/network /etc/config/network
cp files/id_rsa ~/.ssh
cp files/id_rsa.pub ~/.ssh
cp files/rt_tables /etc/iproute2
cp files/cron_watchdog /etc/crontabs/root
chmod 0700 ~/.ssh
chmod 0600 ~/.ssh/*

opkg update
opkg remove wpad-mini
for i in ca-certificates rdisc6 wget screen php5-cli zoneinfo-core oping lldpd kmod-ath9k kmod-ath10k iw wpa-supplicant wpa-cli php5-mod-openssl ip-full; do
	opkg install $i
done

#uci set system.@system[0].log_file='/var/log/syslog'
#uci set system.@system[0].log_ip='1.1.1.1'
uci delete system.@system[0].log_file
uci delete system.@system[0].log_ip

if [ "$(uci get lldpd.config.interface|grep eth0)" = "" ];then
	uci add_list lldpd.config.interface='eth0'
fi
if [ "$(uci get lldpd.config.interface|grep br-lan)" = "" ];then
	uci add_list lldpd.config.interface='br-lan'
fi

# Get rid of stupid ULA prefix.
uci set network.globals.ula_prefix=


uci commit

rm /sbin/ip; ln -s /usr/bin/ip /sbin/ip

/etc/init.d/cron start
/etc/init.d/cron enable

id
