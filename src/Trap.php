<?php

namespace Chrissileinus\SNMP;

class Trap implements \ArrayAccess, \Stringable
{
  private array $container;

  use containerArrayAccess;
  use containerStringable;

  public function __construct(array $input)
  {
    $this->container = [
      'time' => microtime(true),
      'sys' => [],
      'event' => []
    ];

    if (count($input) < 4) return;  // to small to be a SNMP Trap

    $this->getSys($input);
    $this->getEvent($input);
  }

  private function getSys(array $input)
  {
    preg_match('/UDP: \[([^\]]+)\]:/', $input[1], $matches);
    $this->container['sys']['ipAddress'] = $matches[1];

    $hostname = trim($input[0]);
    $hostname = $hostname != "<UNKNOWN>" ? $hostname : null;
    $hostname = $hostname ?: gethostbyaddr($this->container['sys']['ipAddress']);
    $hostname = $hostname ?: '';
    $this->container['sys']['hostname'] = $hostname;

    $this->container['sys']['uptime'] = trim(strstr($input[2], " "));
  }

  private function getEvent(array $input)
  {
    list($file, $eventType) = explode("::", trim(strstr($input[3], " ")));

    $this->container['event'] = [
      'type' => $eventType,
      'content' => [],
    ];

    if (count($input) > 4) {
      for ($m = 4; $m < count($input); $m++) {
        if (preg_match('/(.+)::(\w+).* (.+)/', $input[$m], $matches)) {
          if ($matches[1] == $file) {
            $this->container['event']['content'][$matches[2]] = $matches[3];
          } else {
            $this->container['event']['content'][$matches[1] . "::" . $matches[2]] = $matches[3];
          }
        }
      }
    }
  }
}
