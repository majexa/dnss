!#/bin/bash
apt-get -y install python-software-properties
apt-get update
add-apt-repository --yes ppa:ondrej/php5
apt-get -y install php5-cli

echo "Input slave server IP"
read slave
cd /etc/bind
conf="\tallow-transfer { 127.0.0.1; ${slave}; };\n\tallow-recursion { 127.0.0.1; };\n\tnotify yes;"
php -r "print preg_replace('/^};\n/m', \"${conf}\n};\", file_get_contents('named.conf.options'));"
rndc reload

ssh-keygen -f ~/.ssh/id_rsa -t rsa -N ''
cat ~/.ssh/id_rsa.pub | ssh ${slave} 'cat >> .ssh/authorized_keys'

ssh ${slave} << EOF
  apt-get -y install bind9 php5-cli
  cd /etc/bind
  conf="\tallow-transfer { 127.0.0.1; };\n\tallow-recursion { 127.0.0.1; };"
  php -r "file_put_contents(preg_replace('/^};\n/m', \"${conf}\n};\", file_get_contents('named.conf.options')));"
  rndc reload
EOF