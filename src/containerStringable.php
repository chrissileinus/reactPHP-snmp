<?php

namespace Chrissileinus\SNMP;

trait containerStringable
{
  //  Stringable implementation
  public function __toString(): string
  {
    return yaml_emit($this->container);
  }
}
