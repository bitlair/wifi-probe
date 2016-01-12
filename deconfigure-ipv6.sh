#!/bin/sh
interface="$1"

if [ "$interface" = "" ]; then
        echo Syntax: $0 interface 1>&2
        exit
fi

# Remove non-link scope addresses
ip -6 addr list dev wlan0 scope global|grep inet6|awk '{ print $2 }'|while read ip; do
	ip -6 addr del $ip dev wlan0
	ip -6 route del $(echo $ip|cut -d: -f1-4)::/64 dev wlan0 table wlan0
done


# Cleanup routing table and rules
ip -6 route del default table $interface
while ip -6 rule del table $interface;do true;done &>/dev/null
