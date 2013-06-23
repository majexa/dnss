#apt-get -y install python-software-properties
#apt-get update
#add-apt-repository --yes ppa:ondrej/php5
#apt-get install php5-cli

#echo "Input slave server IP"
#read slaveIp
#cd /etc/bind
#conf="\tallow-transfer { 127.0.0.1; ${slaveIp}; };\n\tallow-recursion { 127.0.0.1; };\n\tnotify yes;"
#php -r "print preg_replace('/^};\n/m', \"${conf}\n};\", file_get_contents('named.conf.options'));"
rndc reload

#ssh-keygen
#slaveIp=37.139.3.229
#scp /root/.ssh/id_rsa.pub ${slaveIp}:/root/.ssh/authorized_keys

ssh ${slaveIp}
apt-get install bind9 php5-cli
cd /etc/bind
conf="\tallow-transfer { 127.0.0.1; };\n\tallow-recursion { 127.0.0.1; };"
php -r "print preg_replace('/^};\n/m', \"${conf}\n};\", file_get_contents('named.conf.options'));"
rndc reload
exit