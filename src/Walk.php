<?php

namespace Chrissileinus\SNMP;

class Walk implements \ArrayAccess, \Stringable
{
  private array $container;

  use containerArrayAccess;
  use containerStringable;

  public function __debugInfo()
  {
    return $this->container;
  }

  private string $host;

  public function __construct(string $host)
  {
    // if (!ip2long($host)) throw new \Exception("Not a valid IP Address '{$host}'");

    $this->host = $host;
    $this->container = [];
  }

  private static function getValue($matches)
  {
    $value = trim($matches['value'], " \t\n\r\0\x0B\"");
    if ($value == intval($value)) return intval($value);
    if ($value == "true") return true;
    if ($value == "false") return false;
    return $value;
  }

  public function getInterfaces()
  {
    return $this->run(".1.3.6.1.2.1.2.2.1")->then(
      function ($data) {
        $matched = false;

        foreach (explode(PHP_EOL, $data) as $line) {
          if (preg_match('/(?<mib>.+)::(?<type>[^.]+)\.(?<counter>[^ ]+) (?<value>.*)/', $line, $matches)) {
            $value = self::getValue($matches);
            $matched = true;

            if (preg_match('/if(.+)/', $matches['type'], $m)) {
              $this->container['Interfaces'][$matches['counter']][$m[1]] = $value;
              continue;
            }
          }
        }

        if ($matched) $this->container['SysAddress'] = $this->host;
        return $this->container;
      }
    );
  }

  public function getSTP()
  {
    return $this->run(".1.3.6.1.2.1.17.2")->then(
      function ($data) {
        $matched = false;

        foreach (explode(PHP_EOL, $data) as $line) {
          if (preg_match('/(?<mib>.+)::(?<type>[^.]+)\.(?<counter>[^ ]+) (?<value>.*)/', $line, $matches)) {
            $value = self::getValue($matches);
            $matched = true;

            if (preg_match('/dot1dStpPort(.+)/', $matches['type'], $m)) {
              $this->container['STP']['Ports'][$matches['counter']][$m[1]] = $value;
              continue;
            }
            if (preg_match('/dot1dStp(.+)/', $matches['type'], $m)) {
              $this->container['STP'][$m[1]] = $value;
              continue;
            }
          }
        }

        if ($matched) $this->container['SysAddress'] = $this->host;
        return $this->container;
      }
    );
  }

  public function getLLDPlocal()
  {
    return $this->run(".1.0.8802.1.1.2.1.3")->then(
      function ($data) {
        $matched = false;

        foreach (explode(PHP_EOL, $data) as $line) {
          if (preg_match('/(?<mib>.+)::(?<type>[^.]+)\.(?<counter>[^ ]+) (?<value>.*)/', $line, $matches)) {
            $value = self::getValue($matches);
            $matched = true;

            if (preg_match('/lldpLocPort(.+)/', $matches['type'], $m)) {
              $this->container['Ports'][$matches['counter']][$m[1]] = $value;
              continue;
            }
            if (preg_match('/lldpLocSys(.+)/', $matches['type'], $m)) {
              $this->container['Sys' . $m[1]] = array_key_exists('Sys' . $m[1], $this->container) ? $this->container['Sys' . $m[1]] .= $value : $value;
            }
          }
        }

        if ($matched) $this->container['SysAddress'] = $this->host;
        return $this->container;
      }
    );
  }

  public function getLLDPremote()
  {
    return $this->run(".1.0.8802.1.1.2.1.4.1")->then(
      function ($data) {
        $matched = false;

        foreach (explode(PHP_EOL, $data) as $line) {
          if (preg_match('/(?<mib>.+)::(?<type>[^.]+)\.[\d]+\.(?<counter>\d+)\.(?<sub>.+) (?<value>.*)/', $line, $matches)) {
            $value = self::getValue($matches);
            $matched = true;

            if (preg_match('/lldpRemPort(.+)/', $matches['type'], $m)) {
              $this->container['Ports'][$matches['counter']]['Remote']['Port' . $m[1]] = $value;
            }
            if (preg_match('/lldpRemSys(.+)/', $matches['type'], $m)) {
              $this->container['Ports'][$matches['counter']]['Remote']['Sys' . $m[1]] = array_key_exists('Sys' . $m[1], $this->container['Ports'][$matches['counter']]['Remote']) ? $this->container['Ports'][$matches['counter']]['Remote']['Sys' . $m[1]] .= $value : $value;
            }
          }
        }

        if ($matched) $this->container['SysAddress'] = $this->host;

        return $this->run("-Oqn", ".1.0.8802.1.1.2.1.4.2.1.3")->then(
          function ($data) {

            foreach (explode(PHP_EOL, $data) as $line) {
              if (preg_match('/(?<counter>\d+)\.\d+\.1\.4\.(?<ipAddress>\d+\.\d+\.\d+\.\d+) .*/', $line, $m)) {
                $this->container['Ports'][$m['counter']]['Remote']['SysAddress'] = $m['ipAddress'];
              }
            }
            return $this->container;
          }
        );
      }
    );
  }

  /**
   * Runs command.
   *
   * @param  string ...$args
   * @return \React\Promise\PromiseInterface
   * @throws Exception
   */
  public function run(...$args): \React\Promise\PromiseInterface
  {
    $deferred = new \React\Promise\Deferred();

    $stdout = $this->runStream($this->host, ...$args);

    $result = "";

    $stdout->on('data', function ($chunk) use (&$result) {
      $result .= $chunk;
    });

    $stdout->on('close', function () use (&$result, $deferred) {
      $deferred->resolve($result);
    });

    return $deferred->promise();
  }

  /**
   * Runs command and stream the output.
   *
   * @param  string ...$args
   * @return \React\Stream\ReadableStreamInterface
   */
  public function runStream(...$args): \React\Stream\ReadableStreamInterface
  {
    if (!`which snmpwalk`) throw new \Exception("snmpwalk ist not installed", 1);

    $command = 'snmpwalk -v2c -Oq -c public ' . implode(' ', $args);

    $process = new \React\ChildProcess\Process($command);
    $process->start();

    return $process->stdout;
  }
}
