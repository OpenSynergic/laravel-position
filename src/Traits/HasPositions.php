<?php

namespace OpenSynergic\Position\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use OpenSynergic\Position\Contracts\Position;
use OpenSynergic\Position\Exceptions\PositionDoesNotExist;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Traits\HasRoles;

trait HasPositions
{
  use HasRoles;

  /** @var string */
  private $positionClass;

  public static function bootHasPositions()
  {
    static::deleting(function ($model) {
      if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
        return;
      }

      $model->positions()->detach();
    });
  }

  public function getPositionClass()
  {
    if (!isset($this->positionClass)) {
      $this->positionClass = app(config('position.model.position'));
    }

    return $this->positionClass;
  }

  public function positions(): BelongsToMany
  {
    return $this->morphToMany(
      config('position.model.position'),
      'model',
      'model_has_positions',
      'model_id',
      'position_id',
    );
  }

  /**
   * Scope the model query to certain position only.
   *
   * @param  string|int|array|\OpenSynergic\Position\Contracts\Position|\Illuminate\Support\Collection $positions
   */
  public function scopePosition(Builder $query, string|int|array|Position|Collection $positions): Builder
  {
    if ($positions instanceof Collection) {
      $positions = $positions->all();
    }


    $positions = array_map(function ($position) {
      if ($position instanceof Position) {
        return $position;
      }

      return $this->getStoredPosition($position);
    }, Arr::wrap($positions));

    return $query->whereHas('positions', function (Builder $subQuery) use ($positions) {
      $positionClass = $this->getPositionClass();
      $key = (new $positionClass())->getKeyName();
      $subQuery->whereIn('positions.' . $key, \array_column($positions, $key));
    });
  }


  /**
   * Assign the given role to the model.
   *
   * @param  array|string|int|\OpenSynergic\Position\Contracts\Position|\Illuminate\Support\Collection  ...$position
   * @return $this
   */
  public function assignPosition(...$position)
  {
    $positions = collect($position)
      ->flatten()
      ->reduce(function ($array, $position) {
        if (empty($position)) {
          return $array;
        }

        $position = $this->getStoredPosition($position);
        if (!$position instanceof Position) {
          return $array;
        }

        $array[$position->getKey()] = [];

        return $array;
      }, []);

    $model = $this->getModel();

    if ($model->exists) {
      $this->positions()->sync($positions, false);
      $model->load('positions');
    } else {
      $class = \get_class($model);

      $class::saved(
        function ($object) use ($positions, $model) {
          if ($model->getKey() != $object->getKey()) {
            return;
          }
          $model->positions()->sync($positions, false);
          $model->load('positions');
        }
      );
    }

    return $this;
  }

  /**
   * Revoke the given role from the model.
   *
   * @param  string|int|\OpenSynergic\Position\Contracts\Position  $role
   */
  public function removePosition(string|int|Position $position)
  {

    $this->positions()->detach($this->getStoredposition($position));

    $this->load('positions');

    return $this;
  }


  /**
   * Remove all current positions and set the given ones.
   *
   * @param  array|\OpenSynergic\Position\Contracts\Position|\Illuminate\Support\Collection|string|int  ...$positions
   * @return $this
   */
  public function syncPositions(...$positions)
  {
    $this->positions()->detach();

    return $this->assignPosition($positions);
  }

  /**
   * Determine if the model has (one of) the given role(s).
   *
   * @param  string|int|array|\OpenSynergic\Position\Contracts\Position|\Illuminate\Support\Collection  $positions
   */
  public function hasPosition(string|int|array|Position|Collection $positions): bool
  {
    $this->loadMissing('positions');

    if (is_string($positions) && false !== strpos($positions, '|')) {
      $positions = $this->convertPipeToArray($positions);
    }

    if (is_string($positions)) {
      return $this->positions->contains('name', $positions);
    }

    if (is_int($positions)) {
      $roleClass = $this->getRoleClass();
      $key = (new $roleClass())->getKeyName();

      return $this->positions->contains($key, $positions);
    }

    if ($positions instanceof Position) {
      return $this->positions->contains($positions->getKeyName(), $positions->getKey());
    }

    if (is_array($positions)) {
      foreach ($positions as $role) {
        if ($this->hasPositions($role)) {
          return true;
        }
      }

      return false;
    }

    return $positions->intersect($this->positions)->isNotEmpty();
  }

  /**
   * Determine if the model has any of the given role(s).
   *
   * Alias to hasRole() but without Guard controls
   *
   * @param  string|int|array|\OpenSynergic\Position\Contracts\Position|\Illuminate\Support\Collection  $roles
   */
  public function hasAnyPosition(string|int|array|Position|Collection ...$position): bool
  {
    return $this->hasPosition($position);
  }

  public function getPositionNames(): Collection
  {
    $this->loadMissing('positions');

    return $this->positions->pluck('name');
  }

  /**
   * @override
   * 
   * Determine if the model may perform the given permission.
   *
   * @param  string|int|\Spatie\Permission\Contracts\Permission  $permission
   * @param  string|null  $guardName
   *
   * @throws PermissionDoesNotExist
   */
  public function hasPermissionTo($permission, $guardName = null): bool
  {
    if ($this->getWildcardClass()) {
      return $this->hasWildcardPermission($permission, $guardName);
    }

    $permission = $this->filterPermission($permission, $guardName);

    return $this->hasDirectPermission($permission) || $this->hasPermissionViaRole($permission) || $this->hasPermissionViaPosition($permission);
  }

  public function hasPermissionViaPosition(Permission $permission)
  {
    foreach ($this->positions as $position) {
      if ($position->hasRole($permission->roles)) return true;
    }

    return false;
  }


  protected function getStoredPosition($position): Position
  {
    $column = is_numeric($position) ? 'id' : 'name';

    $model =  $this->getPositionClass()->where($column, $position)->first();

    if (!$model) {
      $exceptionMethod = is_numeric($position) ? 'withId' : 'named';
      throw PositionDoesNotExist::{$exceptionMethod}($position);
    }

    return $model;
  }
}
