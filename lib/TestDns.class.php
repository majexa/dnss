<?php

class TestDns extends NgnTestCase {

  function test1() {
    `dns createNsZone`;
  }

  /*
  function test() {
    File::delete('/etc/bind/zones/db.sample.ru');
    print `dnss replaceZone asd.sample.ru 1.1.1.1`;
    $this->assertTrue((bool)preg_match('/@\s+A\s+1\.1\.1\.1/', file_get_contents('/etc/bind/zones/db.sample.ru')));
    $this->assertTrue((bool)preg_match('/asd\s+A\s+1\.1\.1\.1/', file_get_contents('/etc/bind/zones/db.sample.ru')));
    print `dnss deleteZone sample.ru`;
    $this->assertTrue(file_exists('/etc/bind/zones/db.sample.ru'));
    print `dnss deleteZone asd.sample.ru`;
    $this->assertTrue(file_exists('/etc/bind/zones/db.sample.ru'));
    $this->assertFalse((bool)preg_match('/asd\s+A\s+1\.1\.1\.1/', file_get_contents('/etc/bind/zones/db.sample.ru')));
    print `dnss deleteZone sample.ru`;
    $this->assertFalse(file_exists('/etc/bind/zones/db.sample.ru'));
  }
  */

}