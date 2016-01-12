#!/bin/bash

# Get interface as first argument
interface="$1"

if [ "$interface" = "" ]; then
        echo Syntax: $0 interface 1>&2
        exit
fi


# Configure using DHCPv6
if [ "$managed_conf" = "1" ]; then
	echo TODO FIXME HELP I am not configuring with a managed configuration
	while read line; do
		# Outer echo is for stripping whitespace
		key=$(echo $(echo $line|cut -d: -f1))
		value=$(echo $(echo $line|cut -d: -f2-))
		echo $key=$value
		# Strip the last 3 chars
		key=$(echo $key|sed 's/...$//')

		case $key in
			"nameserver")
				nameservers+=" $value"
				;;
			default)
				echo TODO FIXME HELP I do not understand $key=$value
		esac

	done << EOF
$(dhcp6c -i $interface)
EOF
fi

# Grab additional information from DHCPv6
if [ "$other_conf" = 1 ]; then
	while read line; do
		# Outer echo is for stripping whitespace
		key=$(echo $(echo $line|cut -d: -f1))
		value=$(echo $(echo $line|cut -d: -f2-))
		echo TODO something with $key=$value

		# Strip the last 3 chars
		key=$(echo $key|sed 's/...$//')

		case $key in
			"nameserver")
				nameservers+=" $value"
				;;
			default)
				echo TODO FIXME HELP I do not understand $key=$value
		esac

	done << EOF
$(dhcp6c -i $interface)
EOF
fi

echo nameservers=$(echo $nameservers)
