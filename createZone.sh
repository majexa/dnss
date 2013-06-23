#!/bin/bash
#

NS_ZONE="majexa.ru"
MASTER_IP="62.76.189.79"
SLAVE_IP="62.76.177.56"

BIND_DIR="/etc/bind"
BIND_CONF="/etc/bind/named.conf"
BIND_CHECKCONF="/usr/sbin/named-checkconf"
BIND_ZONECONF="/etc/bind/named.conf.local"

SSH="/usr/bin/ssh"

domain=$1
ip=$2

if [ -z $domain ] || [ -z $ip ]; then
  echo "Usage: $0 [domain name] [ip address]"
  exit 1
fi

zone_file="${BIND_DIR}/zones/db.$domain"
nowdate=$(date +%Y%m%d)

# in here zone
(
cat << EOF
\$TTL    600
@       IN      SOA     ns1.${NS_ZONE}. root.${NS_ZONE}. (
                     ${nowdate}01         ; Serial
                         604800         ; Refresh
                          86400         ; Retry
                        2419200         ; Expire
                         604800 )       ; Negative Cache TTL
;

@               NS      ns1.${NS_ZONE}.
@               NS      ns2.${NS_ZONE}.

@               A       $ip
*               A       $ip
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
