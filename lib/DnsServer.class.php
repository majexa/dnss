<?php

class DnsServer {

  const BIND_DIR = '/etc/bind', BIND_CONF = '/etc/bind/named.conf', BIND_CHECKCONF = '/usr/sbin/named-checkconf', BIND_ZONECONF = '/etc/bind/named.conf.local';
  
  public $nsZone, $ip, $masterIp, $slaveIp;

  function __construct() {
    Arr::toObjProp(include dirname(__DIR__).'/config.php', $this);
  }

  protected function parseDomain($domain) {
    if (substr_count($domain, '.') > 1) {
      preg_match('/(.*)\.(\w+\.\w+)/', $domain, $m);
      return [$m[2], $m[1]];
    }
    else {
      return [$domain, false];
    }
  }

  protected $baseRecordsEnd = '; -- END OF BASE RECORD --';

  protected function parseRecords($baseDomain) {
    $c = file_get_contents($this->zoneFile($baseDomain));
    if (!preg_match("/(.*)$this->baseRecordsEnd(.*)/ms", $c, $m)) throw new Exception("Zone file for domain '$baseDomain' was created manual and has unsupported syntax");
    if (trim($m[2])) {
      // domain $baseDomain has extra A records
      foreach (explode("\n", $m[2]) as $v) {
        if (!trim($v)) continue;
        if (!preg_match('/([a-z0-9*\.-]+)\s+A\s+([0-9\.]+)/', $v, $m2)) throw new Exception("Parse error of record '$v'");
        $subDomains[$m2[1]] = $m2[2];
      }
    }
    $m[1] = preg_replace('/(\d+)(\s+; Serial)/m', date('ymds').'$2', $m[1]);
    return [$m[1], $subDomains];
  }

  protected function zoneFile($baseDomain) {
    return self::BIND_DIR."/zones/db.$baseDomain";
  }

  protected function getCommonRecord() {
    $now = date('ymds');
    return <<<TEXT
\$TTL  600
@  IN  SOA  ns1.$this->nsZone. root.$this->nsZone. (
  {$now}01  ; Serial
  604800    ; Refresh
  86400     ; Retry
  2419200   ; Expire
  604800 )  ; Negative Cache TTL

TEXT;
  }

  protected function getBaseRecord($ip) {
    return $this->getCommonRecord().<<<TEXT
@               NS      ns1.$this->nsZone.
@               NS      ns2.$this->nsZone.
@               A       $ip
*               A       $ip

TEXT;
  }

  protected function addSubDomainRecords($records, $subDomains) {
    $records .= $this->baseRecordsEnd."\n";
    if ($subDomains) foreach ($subDomains as $subDomain => $ip) $records .= "$subDomain  A  $ip\n";
    return $records;
  }

  function checkZone($domain) {
    print sys(self::BIND_CHECKCONF.' '.$this->zoneFile($domain));
  }

  function createZone($domain, $ip) {
    list($baseDomain, $subDomain) = $this->parseDomain($domain);
    $zoneFile = $this->zoneFile($baseDomain);
    if (file_exists($zoneFile)) {
      list($records, $subDomains) = $this->parseRecords($baseDomain);
    }
    else {
      $records = $this->getBaseRecord($ip);
    }
    if ($subDomain) $subDomains[$subDomain] = $ip;
    else $records .= "                MX 10   mail.$domain.\n";
    file_put_contents($zoneFile, $this->addSubDomainRecords($records, $subDomains));
    $this->addToZoneFile($baseDomain);
    sys("rndc reload");
    $this->addToSlave($baseDomain);
  }

  protected function addToZoneFile($domain) {
    $zoneFile = $this->zoneFile($domain);
    if (strstr(file_get_contents(self::BIND_ZONECONF), $domain)) return;
    file_put_contents(self::BIND_ZONECONF, file_get_contents(self::BIND_ZONECONF).<<<ZONE
zone "$domain" {
  type master;
  file "$zoneFile";
};

ZONE
    );
  }

  protected function addToSlave($domain) {
    if (!(bool)sys("ssh $this->slaveIp 'grep \"zone \\\"$domain\\\"\"' ".self::BIND_ZONECONF)) {
      $cmd = Cli::formatPutFileCommand(<<<ZONE
zone "$domain" {
  type slave;
  file "zones/db.$domain";
  masters { $this->masterIp; };
};

ZONE
        , self::BIND_ZONECONF, true);
      sys("ssh $this->slaveIp $cmd");
    }
    sys("ssh $this->slaveIp 'rndc reload'");
  }

  function deleteZone($domain) {
    list($baseDomain, $subDomain) = $this->parseDomain($domain);
    $zoneFile = File::checkExists($this->zoneFile($baseDomain));
    if ($subDomain) {
      list($records, $subDomains) = $this->parseRecords($baseDomain);
      unset($subDomains[$subDomain]);
      file_put_contents($zoneFile, trim($this->addSubDomainRecords($records, $subDomains))."\n");
    }
    else {
      list(, $subDomains) = $this->parseRecords($baseDomain);
      if (!$subDomains) {
        unlink($zoneFile);
        $this->deleteFromZoneConf($baseDomain);
      }
    }
    sys('rndc reload');
    $this->deleteSlaveNsZone($domain);
  }

  function deleteBaseZone($domain) {
    $zoneFile = File::checkExists($this->zoneFile($domain));
    unlink($zoneFile);
    $this->deleteFromZoneConf($domain);
  }

  protected function deleteFromZoneConf($domain) {
    file_put_contents(self::BIND_ZONECONF, trim(preg_replace("/zone \"$domain\" {.*};/Ums", '', file_get_contents(self::BIND_ZONECONF)))."\n");
  }

  function deleteSlaveNsZone($domain) {
    $conf = self::BIND_ZONECONF;
    $domain = str_replace('.', '\\.', $domain);
    $this->phpCmd($this->slaveIp, <<<CODE
file_put_contents('$conf', preg_replace('/zone "$domain" {.*\\n};/smU', '', file_get_contents('$conf')));
CODE
    );
    sys("ssh $this->slaveIp 'rndc reload'");
  }

  protected function getNsRecord() {
    return $this->getCommonRecord().<<<TEXT
@               NS      ns1.$this->nsZone.
@               NS      ns2.$this->nsZone.
@               A       $this->ip
www             A       $this->ip
ns1             A       $this->masterIp
ns2             A       $this->slaveIp
$this->baseRecordsEnd

TEXT;
  }

  function phpCmd($server, $code) {
    file_put_contents('/tmp/temp.php', "<?php\n\n".$code);
    sys("ssh $server 'mkdir -p /root/temp'");
    sys("ssh 'rm -f $server/root/temp/temp.php'");
    sys("scp /tmp/temp.php $server:/root/temp/temp.php");
    sys("ssh $server 'php /root/temp/temp.php'");
    sys("rm /tmp/temp.php");
    sys("ssh $server 'rm /root/temp/temp.php'");
  }

  function createNsZone() {
    Dir::make(self::BIND_DIR.'/zones');
    file_put_contents($this->zoneFile($this->nsZone), $this->getNsRecord());
    $this->addToZoneFile($this->nsZone);
  }

}
