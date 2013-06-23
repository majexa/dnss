<?php

class BindDnsServer {

  function __construct() {
    $this->cfg = include __DIR__.'/config.php';
  }

  /**
   * @options domain, ip
   */
  function createZone() {
    //$file = $this->zoneFile($this->options['domain']);
    //$record = $this->zoneRecord($this->options['domain']);
    //file_put_contents($file, $record);
    $r = `grep -q {$this->options['domain']} {$this->cfg['bindConf']}`;
    die2($r);
  }

  /**
   * @options baseDomain, baseDomainIp, nsMasterIp, nsSlaveIp
   */
  function createNsZone() {

  }

  /**
   * @options oldDomain, newDomain
   */
  function renameZone() {

  }

  /**
   * @options domain
   */
  function deleteZone() {

  }

  protected function zoneFile($domain) {
    return "{$this->cfg['bindDir']}/zones/db.$domain";
  }

  protected function zoneRecord($ip) {
    $serial = time();
    return <<<RECORD
    \$TTL    600
@       IN      SOA     ns1.{$this->cfg['nsZone']}. root.{$this->cfg['nsZone']}. (
                     $serial         ; Serial
                         604800         ; Refresh
                          86400         ; Retry
                        2419200         ; Expire
                         604800 )       ; Negative Cache TTL
    ;

@               NS      ns1.{$this->cfg['nsZone']}.
@               NS      ns2.{$this->cfg['nsZone']}.

@               A       $ip
*               A       $ip
RECORD;
  }

}