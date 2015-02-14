<?php

class TestDns extends NgnTestCase {

  function test() {
    print `dnss createZone sample3-a123.ru 1.1.1.1`;
    $this->assertTrue(file_exists('/etc/bind/zones/db.sample3-a123.ru'));
    print `dnss deleteZone sample3-a123.ru`;
    $this->assertFalse(file_exists('/etc/bind/zones/db.sample3-a123.ru'));
    File::delete('/etc/bind/zones/db.sample.ru');
    print `dnss replaceZone asd.sample3-a123.ru 1.1.1.1`;
    $this->assertTrue((bool)preg_match('/@\s+A\s+1\.1\.1\.1/', file_get_contents('/etc/bind/zones/db.sample3-a123.ru')));
    $this->assertTrue((bool)preg_match('/asd\s+A\s+1\.1\.1\.1/', file_get_contents('/etc/bind/zones/db.sample3-a123.ru')));
    print `dnss deleteZone sample3-a123.ru`;
    $this->assertTrue(file_exists('/etc/bind/zones/db.sample3-a123.ru'));
    print `dnss deleteZone asd.sample3-a123.ru`;
    $this->assertTrue(file_exists('/etc/bind/zones/db.sample3-a123.ru'));
    $this->assertFalse((bool)preg_match('/asd\s+A\s+1\.1\.1\.1/', file_get_contents('/etc/bind/zones/db.sample3-a123.ru')));
    print `dnss deleteZone sample3-a123.ru`;
    $this->assertFalse(file_exists('/etc/bind/zones/db.sample3-a123.ru'));
  }

}