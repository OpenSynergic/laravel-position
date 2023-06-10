<?php

namespace OpenSynergic\Position\Models;


use Illuminate\Database\Eloquent\Model;
use OpenSynergic\Position\Contracts\Position as PositionContract;
use Spatie\Permission\Traits\HasRoles;

class Position extends Model implements PositionContract
{
  use HasRoles;


  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'name',
    'description',
  ];
}
