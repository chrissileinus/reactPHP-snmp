<?php

namespace Chrissileinus\SNMP;

trait containerArrayAccess
{
  //  ArrayAccess implementation
  public function offsetSet($offset, $value): void
  {
  }

  public function offsetExists($offset): bool
  {
    return isset($this->container[$offset]);
  }

  public function offsetUnset($offset): void
  {
  }

  public function offsetGet($offset): mixed
  {
    return isset($this->container[$offset]) ? $this->container[$offset] : null;
  }
}
