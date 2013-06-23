BIND_ZONECONF="/etc/bind/named.conf.local"
oldDomain=$1
newDomain=$2
if [ -z $oldDomain ] || [ -z $newDomain ]; then
  echo "Usage: $0 [old domain] [new domain]"
  exit 1
fi
mv /etc/bind/zones/db.$oldDomain /etc/bind/zones/db.$newDomain
sed "s/\"${oldDomain}\"/\"${newDomain}\"/m" $BIND_ZONECONF > tmp
mv tmp $BIND_ZONECONF
sed "s/db.${oldDomain}\"/db.${newDomain}/m" $BIND_ZONECONF > tmp
mv tmp $BIND_ZONECONF
