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

    $this->getSys($input);
    $this->getEvent($input);
  }

  private function getSys(array $input)
  {
    $hostname = trim($input[0]);
    $this->container['sys']['hostname'] = $hostname != "<UNKNOWN>" ? $hostname : null;

    preg_match('/UDP: \[([^\]]+)\]:/', $input[1], $matches);
    $this->container['sys']['ip'] = $matches[1];

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
        if (preg_match('/(\S*)::(\S*) (.*)/', $input[$m], $matches)) {
          if ($matches[1] == $file) {
            $this->container['event']['content'][$matches[2]] = $matches[3];
          } else {
            $this->container['event']['content'][$matches[1] . "::" . $matches[2]] = $matches[3];
          }
        }
      }
    }
  }

  /**
   * upTimeToFloat
   *
   * @param  string $time example: \<days>:\<hours>:\<minutes>:\<seconds> '0:20:10:59.70'
   * @return float
   */
  public static function upTimeToFloat(string $time): float
  {
    preg_match('/(\d+):(\d+):(\d+):([\d.]+)/', $time, $matches);
    return (($matches[1] * 24 + $matches[2]) * 60 + $matches[3]) * 60 + $matches[4];
  }
}
