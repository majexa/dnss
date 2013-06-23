<?


$BIND_ZONECONF = "/etc/bind/named.conf.local";
if (empty($_SERVER['argv'][1])) {
  echo "Usage: $0 [domain]";
  return;
}
$domain = $_SERVER['argv'][1];
file_put_contents(preg_replace("/zone \"$domain\" \\{.*\\};/ms", '+++', file_get_contents($BIND_ZONECONF)));
