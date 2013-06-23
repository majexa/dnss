BIND_ZONECONF="/etc/bind/named.conf.local"
domain=$1
if [ -z $domain ] ; then
  echo "Usage: $0 [domain]"
  exit 1
fi

sed "s/zone \"${domain}\" {.*};/123/ms" $BIND_ZONECONF
