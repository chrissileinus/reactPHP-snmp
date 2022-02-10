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


  /**
   * Get LLDP from every switch, follow the tree through the hole accessable network and collect states on the walk.
   *
   * @param  string                          $ipAddress	from Switch where to start the Walk
   * @param  callable|null                   $callback	to run on every result
   * @param  array                           $result		
   * @param  bool                            $withInterfaces	if true also collect all interfaces
   * @return \React\Promise\PromiseInterface
   */
  public static function NetWalk(
    string $ipAddress,
    callable $callback = null,
    array &$result = [],
    bool $withInterfaces = false
  ): \React\Promise\PromiseInterface {
    $result[$ipAddress] = new Walk($ipAddress);

    $jobs = [];
    $jobs[] = $result[$ipAddress]->getLLDPlocal();
    $jobs[] = $result[$ipAddress]->getLLDPremote();
    $jobs[] = $result[$ipAddress]->getSTP();
    if ($withInterfaces) $jobs[] = $result[$ipAddress]->getInterfaces();

    return \React\Promise\all($jobs)->then(function () use ($ipAddress, &$result, $callback, $withInterfaces) {
      if (!isset($result[$ipAddress]['Ports'])) return;
      if (is_callable($callback)) $callback($result[$ipAddress]);

      $jobs = [];
      foreach ($result[$ipAddress]['Ports'] as $entry) {
        if (
          array_key_exists('Remote', $entry) &&
          array_key_exists('SysAddress', $entry['Remote']) &&
          !array_key_exists($entry['Remote']['SysAddress'], $result)
        ) {
          $jobs[] = self::NetWalk(
            ipAddress: $entry['Remote']['SysAddress'],
            result: $result,
            callback: $callback,
            withInterfaces: $withInterfaces
          );
        }
      }
      return \React\Promise\all($jobs)->then(function () use (&$result) {
        foreach ($result as $address => $entry) {
          // if (!isset($entry['SysAddress'])) unset($result[$address]);
        }
        return $result;
      });
    });
  }
}
