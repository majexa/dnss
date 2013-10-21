<?php

class TestDns extends NgnTestCase {

  function test() {
    // how to install
    //
    // php run.php "(new DnsServer)->deleteZone('asd.ru')" NGN_ENV_PATH/dns-server/lib
    // addYamailSupport
    // removeYamailSupport
    $this->runCode("(new DnsServer)->createZone('asd.ru', '123.123.123.123')");
  }

  protected function runCode($code) {
    Cli::shell(Cli::formatRunCmd($code, "NGN_ENV_PATH/dns-server/lib"));
  }

}