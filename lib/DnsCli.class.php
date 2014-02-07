<?php

class DnsCli extends CliHelpArgs {

  protected $oneEntry = 'server';

  protected function prefix() {
    return 'dns';
  }

  protected function runner() {
    return 'dnss';
  }

}