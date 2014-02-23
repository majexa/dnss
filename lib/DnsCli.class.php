<?php

class DnsCli extends CliHelpArgsSingle {

  function __construct($argv) {
    parent::__construct($argv, new DnsServer);
  }

  protected function _runner() {
    return 'dnss';
  }

}