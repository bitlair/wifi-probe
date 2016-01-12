#!/bin/sh

managed_conf=""
other_conf=""
prefix=""
prefix_onlink=""
prefix_autonomous=""
gateway=""
nameservers=""

# Get interface as first argument
interface="$1"

if [ "$interface" = "" ]; then
	echo Syntax: $0 interface 1>&2
	exit
fi

mac_to_eui64 () {
	eui64=$(echo $1 |(IFS=: read a b c d e f;
		printf "%02x%02x:%02xff:fe%02x:%02x%02x" $((0x$a ^ 2)) 0x$b 0x$c 0x$d 0x$e 0x$f
	))
}


while read line; do
	# Outer echo is for stripping whitespace
	key=$(echo $(echo $line|cut -d: -f1))
	value=$(echo $(echo $line|cut -d: -f2-))

	if [ "$value" = "Yes" ];then
		bool_value=1
	else
		bool_value=0
	fi

	case $key in
		"Stateful address conf.")
			managed_conf=$bool_value
		        ;;
		"Stateful other conf.")
			other_conf=$bool_value
			;;
		"Prefix")
			prefix=$value
			;;
		"On-link")
			prefix_onlink=$bool_value
			;;
		"Autonomous address conf.")
			prefix_autonomous=$bool_value
			;;
		"Recursive DNS server")
			nameservers="$nameservers $value"
			;;
		"from fe80")
			gateway=fe80:$value
	esac	
done << EOF
$(rdisc6 -1 $interface)
EOF
mac_address=$(ip link list dev $interface|awk '($1 == "link/ether") { print $2 }')
mac_to_eui64 $mac_address

echo Managed=$managed_conf
echo Other=$other_conf
echo Onlink=$prefix_onlink
echo Autonomous=$prefix_autonomous
echo Prefix=$prefix
echo Gateway=$gateway


[ "$prefix" = "" ] || ([ "$managed_conf" = "1" ] && [ "$other_conf" = "1" ]) || ([ "$prefix_autonomous" = "0" ] && [ "$prefix_managed" = "0" ]) && (
	echo "Invalid flag combination (no prefix or managed+other or no autonomous+no managed)."
	exit 1
)

if [ "$(echo $prefix|grep -o .....$)" != "::/64" ]; then
	echo Invalid prefix length. Only /64 works.
	exit 1
fi

# We can configure using SLAAC
if [ "$prefix_autonomous" = "1" ]; then 
	ip=$(echo -n $prefix|sed 's/....$//';echo $eui64)
	echo IPAddress=$ip
	ip -6 addr add $ip/64 dev $interface
	ip -6 route del $prefix dev $interface table main
	ip -6 route add $prefix dev $interface table $interface
	ip -6 route add default via $gateway dev $interface table $interface
	while ip -6 rule del table $interface;do true;done &>/dev/null
	ip -6 rule add from $ip table $interface
fi	


echo nameservers=$(echo $nameservers)
