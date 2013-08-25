#!/bin/bash
#
# ./createNsZone.sh wandaland.ru domainIp nsMasterIp nsSlaveIp

BIND_DIR="/etc/bind"
BIND_CONF="/etc/bind/named.conf"
BIND_CHECKCONF="/usr/sbin/named-checkconf"
BIND_ZONECONF="/etc/bind/named.conf.local"

SSH="/usr/bin/ssh"

if [ -z $1 ] || [ -z $2 ] || [ -z $3 ] || [ -z $4 ]; then
  echo "Usage: $0 [domain name] [domain ip] [ns1 ip] [ns2 ip]"
  exit 1
fi

domain=$1
ip=$2
MASTER_IP=$3
SLAVE_IP=$4

mkdir ${BIND_DIR}/zones
zone_file="${BIND_DIR}/zones/db.$domain"
nowdate=$(date +%Y%m%d)

(
cat << EOF
\$TTL    600
@       IN      SOA     ns1.${domain}. root.${domain}. (
                     ${nowdate}01         ; Serial
                         604800         ; Refresh
                          86400         ; Retry
                        2419200         ; Expire
                         604800 )       ; Negative Cache TTL
;

@               NS      ns1.$domain.
@               NS      ns2.$domain.

@               A       $ip
www             A       $ip

ns1             A       $MASTER_IP
ns2             A       $SLAVE_IP
EOF
) > $zone_file

if ! grep -q $domain $BIND_ZONECONF ;then
(
cat << EOF
zone "$domain" {
  type master;
  file "$zone_file";
};

EOF
) >> $BIND_ZONECONF
fi

if $BIND_CHECKCONF $BIND_CONF ;then
  /usr/sbin/service bind9 reload
fi

sed "s/MASTER_IP=\".*\"/MASTER_IP=\"$MASTER_IP\"/m" createZone.sh > tmp.sh
mv tmp.sh createZone.sh
sed "s/SLAVE_IP=\".*\"/SLAVE_IP=\"$SLAVE_IP\"/m" createZone.sh > tmp.sh
mv tmp.sh createZone.sh
sed "s/NS_ZONE=\".*\"/NS_ZONE=\"${domain}\"/m" createZone.sh > tmp.sh
mv tmp.sh createZone.sh

chmod +x createZone.sh
chmod +x renameZone.sh
chmod +x deleteZone.sh

# slave

if ! $SSH root@$SLAVE_IP "grep -q $domain $BIND_ZONECONF"; then

$SSH root@$SLAVE_IP "( cat << EOF
zone \"$domain\" {
  type slave;
  file \"zones/db.${domain}\";
  masters { ${MASTER_IP}; };
};

EOF
) >> $BIND_ZONECONF
"
fi

$SSH root@$SLAVE_IP "/usr/sbin/service bind9 reload"