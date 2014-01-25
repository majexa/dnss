<?php

class TestDns extends NgnTestCase {

  function test() {
    /*
    $this->runCode("(new DnsServer)->createZone('asd.ru', '123.123.123.123')");
    $r = trim(`dig +short A asd.ru @ns1.majexa.ru`);
    $this->assertTrue($r == '123.123.123.123', "dig result is '$r'");
    $this->runCode("(new DnsServer)->deleteZone('asd.ru')");
    $r = trim(`dig +short A asd.ru @ns1.majexa.ru`);
    $this->assertTrue($r != '123.123.123.123', "dig result is '$r'");
    $this->runCode("(new DnsServer)->addYamailSupport('asd.ru')");
    */
  }

  protected function runCode($code) {
    print Cli::shell(Cli::addRunPaths($code, "NGN_ENV_PATH/dns-server/lib"));
  }

}