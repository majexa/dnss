<?php

/*
php run.php (new DnsServer)->addYamailSupport("cmf-nn.ru", "857121a2faaa") NGN_ENV_PATH/dns-server/lib
php run.php (new DnsServer)->createZone('sm.majexa.ru', '198.211.120.127') NGN_ENV_PATH/dns-server/lib
php run.php (new DnsServer)->updateZone('masted.ru') NGN_ENV_PATH/dns-server/lib
*/

class DnsServer {

  const BIND_DIR = '/etc/bind', BIND_CONF = '/etc/bind/named.conf', BIND_CHECKCONF = '/usr/sbin/named-checkconf', BIND_ZONECONF = '/etc/bind/named.conf.local';

  public $nsZone, $ip, $masterIp, $slaveIp;

  function __construct() {
    Arr::toObjProp(require dirname(__DIR__).'/config.php', $this);
  }

  protected function parseDomain($domain) {
    if (substr_count($domain, '.') > 1) {
      preg_match('/^(.*)\.(\w+\.\w+)$/', $domain, $m);
      return [$m[2], $m[1]];
    }
    else {
      return [$domain, false];
    }
  }

  protected $baseRecordsEnd = '; -- END OF BASE RECORD --';

  /**
   * base - создаются при создании файла и больше не меняются
   * dynamic - могут менять после создания
   * subdomains - записи, относящиеся к сабдоменам
   */
  protected function parseRecords($baseDomain) {
    $c = file_get_contents($this->zoneFile($baseDomain));
    if (!preg_match("/(.*)$this->baseRecordsEnd(.*)/ms", $c, $m)) throw new Exception("Zone file for domain '$baseDomain' was created manual and has unsupported syntax");
    $r['base'] = $m[1];
    if (!preg_match('/^@\s+A\s+([0-9.]+)$/m', $r['base'], $m2)) throw new Exception("Base A record '{$r['base']}' not found");
    $r['ip'] = $m2[1];
    $other = $m[2];
    if (trim($other)) {
      // domain $baseDomain has extra A records
      foreach (explode("\n", $other) as $v) {
        if (!trim($v)) continue;
        if (!preg_match('/([a-z0-9*\.-]+)\s+A\s+([0-9\.]+)/', $v, $m2)) continue;//throw new Exception("Parse error of record '$v'");
        $r['subDomains'][$m2[1]] = $m2[2];
      }
    }
    //die2($r['subDomains']);
    $this->parseSubRecord($r, 'mx', $other, '/^(\s+IN\s+MX\s+\d+\s+.*)$/m');
    $this->parseSubRecord($r, 'yamail', $other, '/^(.*\s+CNAME\s+mail.yandex.ru)/m');
    $regexp = '/(\d+)(\s*; Serial)/m';
    if (!preg_match($regexp, $r['base'], $m)) throw new Exception('Serial not found');
    $r['base'] = preg_replace($regexp, ($m[1] + 1).'$2', $r['base']);
    return $r;
  }

  protected function parseSubRecord(&$r, $name, $otherRecords, $regexp) {
    if (preg_match($regexp, $otherRecords, $m)) {
      if (count($m) == 2) $r['dynamic'][$name] = str_replace("\n", '', $m[1]);
      elseif (count($m) > 2) $r['dynamic'][$name] = array_slice($m, 1);
      else throw new Exception('no group in regexp');
    }
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
@  NS  ns1.$this->nsZone.
@  NS  ns2.$this->nsZone.
@  A   $ip
*  A   $ip

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

  /**
   * @param string|array One or many domains
   * @param $ip
   * @param array $dynamic
   */
  function replaceZone($domain, $ip, array $dynamic = []) {
    $this->__createZone($domain, $ip, $dynamic, true);
    $this->lst();
  }

  function createZone($domain, $ip, array $dynamic = []) {
    $this->__createZone($domain, $ip, $dynamic, false);
  }

  protected function __createZone($domains, $ip, array $dynamic = [], $replace = false) {
    foreach ((array)$domains as $domain) $this->_createZone($domain, $ip, $dynamic, $replace);
    sys("rndc reload");
  }

  protected function _createZone($domain, $ip, array $dynamic = [], $replace = false) {
    list($baseDomain, $subDomain) = $this->parseDomain($domain);
    Misc::checkEmpty($baseDomain);
    $baseZoneExists = file_exists($this->zoneFile($baseDomain));
    if (!$replace and $baseZoneExists and !$subDomain) throw new Exception("Base zone for domain '$baseDomain' already exists. Use replace");
    if ($baseZoneExists) {
      $parsedRecords = $this->parseRecords($baseDomain);
      if ($subDomain) {
        if (isset($parsedRecords['subDomains'][$subDomain])) {
          if ($replace) {
            $parsedRecords['subDomains'][$subDomain] = $ip;
          } else {
            output("Zone for subdomain '$subDomain.$baseDomain' already exists");
            return;
          }
        } else {
          $parsedRecords['subDomains'][$subDomain] = $ip;
        }
      } else {
        $parsedRecords['base'] = $this->getBaseRecord($ip);
        $parsedRecords['ip'] = $ip;
      }
    }
    else {
      $parsedRecords['base'] = $this->getBaseRecord($ip);
      $parsedRecords['ip'] = $ip;
      if ($subDomain) $parsedRecords['subDomains'] = [$subDomain => $ip];
    }
    $this->_updateZone($baseDomain, $parsedRecords, $dynamic);
  }

  function updateZone($domain, array $dynamic = []) {
    list($baseDomain) = $this->parseDomain($domain);
    if (!file_exists($this->zoneFile($baseDomain))) throw new Exception("Zone for domain '$baseDomain' not exists");
    $parsedRecords = $this->parseRecords($baseDomain);
    $this->_updateZone($baseDomain, $parsedRecords, $dynamic);
    sys("rndc reload");
  }

  function updateZoneIp($domain, $ip) {
    list($baseDomain, $subDomain) = $this->parseDomain($domain);
    if ($subDomain) throw new Exception('$subDomain not realized');
    if (!file_exists($this->zoneFile($baseDomain))) throw new Exception("Zone for domain '$baseDomain' not exists");
    $parsedRecords = $this->parseRecords($baseDomain);
    $replacingRecords['base'] = $this->getBaseRecord($ip);
    $replacingRecords['ip'] = $ip;
    $parsedRecords = array_merge($parsedRecords, $replacingRecords);
    $this->_updateZone($baseDomain, $parsedRecords);
    sys("rndc reload");
  }

  protected function _updateZone($baseDomain, $parsedRecords, array $dynamic = []) {
    $parsedRecords['dynamic'] = [];
    $parsedRecords['dynamic']['mx'] = "@  IN  MX  10  mail.$baseDomain.";
    foreach ($dynamic as $k => $v) $parsedRecords['dynamic'][$k] = $v;
    output2("# record save:\n".$this->toString($parsedRecords));
    file_put_contents($this->zoneFile($baseDomain), $this->toString($parsedRecords));
    $this->addToZoneFile($baseDomain);
    // $this->addToSlave($baseDomain);
  }

  protected function addDynamicRecord($domain, $name, $record) {
    File::checkExists($this->zoneFile($domain));
    $parsedRecords = $this->parseRecords($domain);
    $parsedRecords['dynamic'][$name] = $record;
    file_put_contents($this->zoneFile($domain), $this->toString($parsedRecords));
  }

  protected function removeDynamicRecord($domain, $name) {
    File::checkExists($this->zoneFile($domain));
    $parsedRecords = $this->parseRecords($domain);
    unset($parsedRecords['dynamic'][$name]);
    file_put_contents($this->zoneFile($domain), $this->toString($parsedRecords));
  }

  function addYamailSupport($domain, $code) {
    $code = 'yamail-'.$code;
    $this->addDynamicRecord($domain, 'yamail', "$code.$domain.  CNAME  mail.yandex.ru.");
    $this->addDynamicRecord($domain, 'mx', '@  IN  MX  10  mx.yandex.ru');
    sys("rndc reload");
  }

  function removeYamailSupport($domain) {
    $this->removeDynamicRecord($domain, 'yamail');
  }

  protected function toString(array $parsedRecords, $strict = true) {
    $r = $parsedRecords['base'];
    if ($strict and !$r) throw new EmptyException("\$parsedRecords['base']");
    $r .= $this->baseRecordsEnd."\n";
    $r .= implode("\n", $parsedRecords['dynamic'])."\n";
    if (!empty($parsedRecords['subDomains'])) {
      foreach ($parsedRecords['subDomains'] as $subDomain => $ip) {
        $r .= "$subDomain  A  $ip\n";
      }
    }
    return $r;
  }

  protected function addToZoneFile($domain) {
    $zoneFile = $this->zoneFile($domain);
    if (strstr(file_get_contents(self::BIND_ZONECONF), $domain)) return;
    output("Adding domain $domain to zone confing file");
    file_put_contents(self::BIND_ZONECONF, file_get_contents(self::BIND_ZONECONF).<<<ZONE
zone "$domain" {
  type master;
  file "$zoneFile";
};

ZONE
    );
  }

  protected function addToSlave($domain) {
    output("Check if domain $domain exists on slave");
    if (!(bool)sys("ssh $this->slaveIp 'grep \"zone \\\"$domain\\\"\"' ".self::BIND_ZONECONF)) {
      output("Adding domain $domain to slave");
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

  function deleteZone($domains, $withSubdomains = false) {
    foreach ((array)$domains as $domain) $this->_deleteZone($domain, $withSubdomains);
    sys('rndc reload');
  }

  protected function _deleteZone($domain, $withSubdomains = false) {
    list($baseDomain, $subDomain) = $this->parseDomain($domain);
    $zoneFile = File::checkExists($this->zoneFile($baseDomain));
    if ($subDomain) {
      $parsedRecords = $this->parseRecords($baseDomain);
      if ($withSubdomains) {
        foreach (array_keys($parsedRecords['subDomains']) as $sub) {
          if (Misc::hasSuffix($subDomain, $sub)) unset($parsedRecords['subDomains'][$sub]);
        }
      } else {
        unset($parsedRecords['subDomains'][$subDomain]);
      }
      $this->_updateZone($baseDomain, $parsedRecords);
    }
    else {
      $r = $this->parseRecords($baseDomain);
      if ($withSubdomains or empty($r['subDomains'])) {
        unlink($zoneFile);
        $this->deleteFromZoneConf($baseDomain);
      }
    }
  }

  function deleteBaseZone($domain) {
    $zoneFile = File::checkExists($this->zoneFile($domain));
    unlink($zoneFile);
    $this->deleteFromZoneConf($domain);
  }

  protected function deleteFromZoneConf($domain) {
    file_put_contents(self::BIND_ZONECONF, trim(preg_replace("/zone \"$domain\" {.*};/Ums", '', file_get_contents(self::BIND_ZONECONF)))."\n");
  }

  protected function deleteSlaveNsZone($domain) {
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

  protected function phpCmd($server, $code) {
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

  const getZonesMethodFlat = 1;
  const getZonesMethodTree = 2;

  protected function getZones($method = self::getZonesMethodFlat) {
    $r = [];
    foreach (glob(self::BIND_DIR."/zones/db.*") as $file) {
      $domain = Misc::removePrefix('db.', basename($file));
      try {
        $parsed = $this->parseRecords($domain);
      } catch (Exception $e) {
        output("'$domain' zone has a problem. Need to cleanup");
        continue;
      }
      if ($method === self::getZonesMethodFlat) {
        $r[] = [
          'domain' => $domain,
          'ip' => $parsed['ip']
        ];
        if (isset($parsed['subDomains'])) {
          foreach ($parsed['subDomains'] as $subdomain => $ip) {
            $r[] = [
              'domain' => $subdomain.'.'.$domain,
              'ip' => $ip
            ];
          }
        }
      } else {
        $v = [
          'domain' => $domain,
          'ip' => $parsed['ip']
        ];
        if (isset($parsed['subDomains'])) {
          $v['subDomains'] = [];
          foreach ($parsed['subDomains'] as $subdomain => $ip) {
            $v['subDomains'][] = [
              'domain' => $subdomain.'.'.$domain,
              'ip' => $parsed['ip']
            ];
          }
        }
        $r[] = $v;
      }
    }
    return $r;
  }

  function lst() {
    foreach ($this->getZones() as $v) print str_pad($v['domain'], 30).O::get('CliColors')->getColoredString($v['ip'], 'darkGray')."\n";
  }

  function cleanup() {
    foreach (glob(self::BIND_DIR."/zones/db.*") as $file) {
      $domain = Misc::removePrefix('db.', basename($file));
      try {
        $this->parseRecords($domain);
      } catch (Exception $e) {
        unlink($file);
        output("$domain cleaned up");
        continue;
      }
    }
  }

  function createDoceanMailBotZoneAndServer($domain) {
    //create server
    //
  }

}
