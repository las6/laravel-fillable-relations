<?php

namespace LaravelFillableRelations\Eloquent\Concerns;

/**
 * Mix this in to your model class to enable single table inheritance.
 */
trait SingleTableInheritance
{
    // the field that stores the subclass
    protected $subclassField = 'type';
    // must be overridden and set to true in subclasses
    protected $isSubclass = true;

    public function isSubclass()
    {
        return $this->isSubclass;
    }

    // if no subclass is defined, function as normal
    public function mapData(array $attributes)
    {
        if (! $this->subclassField) {
            return $this->newInstance();
        }

        return new $attributes[$this->subclassField];
    }

    // instead of using $this->newInstance(), call
    // newInstance() on the object from mapData
    public function newFromBuilder($attributes = array(), $connection = null)
    {
        $instance = $this->mapData((array) $attributes)->newInstance(array(), true);
        $instance->setRawAttributes((array) $attributes, true);
        return $instance;
    }

    public function newQuery($excludeDeleted = true)
    {
        // If using Laravel 4.0.x then use the following commented version of this command
        // $builder = new Builder($this->newBaseQueryBuilder());
        // newEloquentBuilder() was added in 4.1
        $builder = $this->newEloquentBuilder($this->newBaseQueryBuilder());

        // Once we have the query builders, we will set the model instances so the
        // builder can easily access any information it may need from the model
        // while it is constructing and executing various queries against it.
        $builder->setModel($this)->with($this->with);

        if ($excludeDeleted && $this->softDelete) {
            $builder->whereNull($this->getQualifiedDeletedAtColumn());
        }

        if ($this->subclassField && $this->isSubclass()) {
            $builder->where($this->subclassField, '=', get_class($this));
        }

        // fix for global scopes
        if (method_exists($this, 'getGlobalScopes')) {
            $blacklist = [
                "Illuminate\Database\Eloquent\SoftDeletingScope"
            ];
            $scopes = $this->getGlobalScopes();
            if (!empty($scopes)) {
                foreach ($scopes as $key => $scope) {
                    if (!in_array($scope, $blacklist)) {
                        $scope->apply($builder, $this);
                    }
                }
            }
        }

        return $builder;
    }

    // ensure that the subclass field is assigned on save
    public function save(array $options = array())
    {
        if ($this->subclassField) {
            $this->attributes[$this->subclassField] = get_class($this);
        }
        return parent::save($options);
    }
}
