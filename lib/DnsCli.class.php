<?php

class DnsCli extends CliAccessArgsSingle {

  function __construct($argv) {
    parent::__construct($argv, new DnsServer);
  }

  protected function _runner() {
    return 'dnss';
  }


}