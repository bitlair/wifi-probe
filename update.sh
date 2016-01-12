#!/bin/bash
if [ "x$1" = "x" ]; then
	echo "Usage: $0 <IP>" 2>&1
	exit 1
fi

ssh root@$1 <<EOF
###### Fix DNS ######
cat <<INNEREOF >/etc/config/dhcp
config dnsmasq
	option resolvfile '/tmp/resolv.conf.auto'
INNEREOF
/etc/init.d/dnsmasq restart

###### Fix OPKG ######
cat <<INNEREOF >/etc/opkg/distfeeds.conf
# This file intentionally left blank
INNEREOF
cat <<INNEREOF >/etc/opkg/customfeeds.conf
src/gz wilco_base http://skelter.nikhef.nl/openwrt-bin-archer/ar71xx/packages/base
src/gz wilco_packages http://skelter.nikhef.nl/openwrt-bin-archer/ar71xx/packages/packages
src/gz wilco_oldpackages http://skelter.nikhef.nl/openwrt-bin-archer/ar71xx/packages/oldpackages
INNEREOF
opkg update
opkg install git
opkg install openssh-client

###### Fix SSH ######
mkdir -p ~/.ssh
chmod 0700 ~/.ssh
cat <<INNEREOF >~/.ssh/known_hosts
 TODO
INNEREOF
cat <<INNEREOF >~/.ssh/id_rsa
 TODO
INNEREOF
chmod 0600 ~/.ssh/id_rsa

[ -d wifi-probe ] || git clone ssh://git@githost.fixme:/wifi-probe.git
cd wifi-probe
git pull
./inner_update.sh
EOF
