<?php

namespace OpenSynergic\Position\Exceptions;

use InvalidArgumentException;

class PositionDoesNotExist extends InvalidArgumentException
{
  public static function named(string $name)
  {
    return new static("There is no position named `{$name}`.");
  }

  public static function withId(int $id)
  {
    return new static("There is no position with id `{$id}`.");
  }
}
