<?php

namespace Chrissileinus\SNMP;

class tools
{
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
