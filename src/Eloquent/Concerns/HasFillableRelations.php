<?php

namespace LaravelFillableRelations\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Mix this in to your model class to enable fillable relations.
 * Usage:
 *     use Illuminate\Database\Eloquent\Model;
 *     use LaravelFillableRelations\Eloquent\Concerns\HasFillableRelations;
 *
 *     class Foo extends Model
 *     {
 *         use HasFillableRelations;
 *         protected $fillable_relations = ['bar'];
 *
 *         function bar()
 *         {
 *             return $this->hasOne(Bar::class);
 *         }
 *     }
 *
 *     $foo = new Foo(['bar' => ['id' => 42]]);
 *     // or perhaps:
 *     $foo = new Foo(['bar' => ['name' => "Ye Olde Pubbe"]]);
 *
 */
trait HasFillableRelations
{
    /**
     * The relations that should be mass assignable.
     *
     * @var array
     */
    // protected $fillable_relations = [];

    public function fillableRelations()
    {
        return isset($this->fillable_relations) ? $this->fillable_relations : [];
    }

    public function extractFillableNestedRelations($model, array $attributes)
    {
        $fillableRelationsData = [];
        foreach ($model->fillableRelations() as $relationName) {
            $val = array_pull($attributes, $relationName);

            //allows empty arrays!
            if (!empty($val) || is_array($val)) {
                $fillableRelationsData[$relationName] = $val;
            }
        }
        return [$fillableRelationsData, $attributes];
    }

    public function extractFillableRelations(array $attributes)
    {
        $fillableRelationsData = [];
        foreach ($this->fillableRelations() as $relationName) {
            $val = array_pull($attributes, $relationName);

            //allows empty arrays!
            if (!empty($val) || is_array($val)) {
                $fillableRelationsData[$relationName] = $val;
            }
        }
        return [$fillableRelationsData, $attributes];
    }

    public function fillRelations(array $fillableRelationsData, $depth = 1)
    {
        foreach ($fillableRelationsData as $relationName => $fillableData) {
            $camelCaseName = camel_case($relationName);
            $relation = $this->{$camelCaseName}();
            $klass = get_class($relation->getRelated());

            // BelongsTo : ASSOCIATE
            if ($relation instanceof BelongsTo) {
                $primaryKey = (new $klass)->getKeyName();

                if ($fillableData instanceof Model) {
                    $entity = $fillableData;
                } else {
                    if (is_array($fillableData)) {
                        // find with all object properties
                        $entity = $klass::findOrFail($fillableData[$primaryKey]);
                    } else {
                        $entity = $klass::findOrFail($fillableData);
                    }
                }
                $relation->associate($entity);

                // HasOne : UPDATE OR CREATE NEW
            } elseif ($relation instanceof HasOne) {
                $qualified_foreign_key = $relation->getQualifiedForeignKeyName();
                // $qualified_foreign_key = $klass::getForeignKey();
                // $qualified_foreign_key = $entity->getForeignKey();
                list($table, $foreign_key) = explode('.', $qualified_foreign_key);
                $qualified_local_key_name = $relation->getQualifiedParentKeyName();
                list($table, $local_key) = explode('.', $qualified_local_key_name);

                if ($fillableData instanceof Model) {
                    $entity = $fillableData;
                } else {
                    // $entity = $klass::firstOrNew($fillableData);
                    $params = [];
                    $params[$foreign_key] = $this->{$local_key};
                    $entity = $klass::firstOrNew($params);
                    unset($fillableData[$this->{$local_key}]);
                    $entity->fill($fillableData);
                }
                $entity->{$foreign_key} = $this->{$local_key};
                $entity->save();

                // HasMany : UPDATE OR CREATE NEW
            } elseif ($relation instanceof HasMany) {
                if (!$this->exists) {
                    $this->save();
                }

                $straight_data = [];
                $relation_data = [];

                $primaryKey = (new $klass)->getKeyName();

                //separate normal fields, sync them.
                foreach ($fillableData as $key => $value) {
                    $model = new $klass;
                    list($fillableNestedRelationsData, $attributes) = $this->extractFillableNestedRelations($model, $value);
                    $straight_data[] = $attributes;
                    $relation_data[] = $fillableNestedRelationsData;
                }

                //Update & Sync the straight data
                $tmp = $relation->sync($straight_data);

                foreach ($fillableData as $key => $value) {
                    $model = false;
                    $id = false;
                    if (!empty($value[$primaryKey])) {
                        $id = $value[$primaryKey];
                    } else {
                        $t = array_shift($tmp['created']);
                        if (!empty($t[0])) {
                            $id = $t[0];
                        }
                    }

                    if (!empty($id)) {
                        $model = $klass::find($id);
                    }

                    if (!$model) {
                        $model = $klass::newModelInstance();
                    }

                    $model->fill($value);
                    list($fillableNestedRelationsData, $attributes) = $this->extractFillableNestedRelations($model, $value);

                    if (!empty($fillableNestedRelationsData)) {
                        if (!empty($model->id)) {
                            $model->fillRelations($fillableNestedRelationsData, $depth+1);
                        }
                    }
                }



                // BelongsToMany : ATTACH & DETACH
            // note: does not allow duplicates!
            } elseif ($relation instanceof BelongsToMany) {
                if (!$this->exists) {
                    $this->save();
                }

                $primaryKey = (new $klass)->getKeyName();
                $allowDeepModification = (new $klass)->allowDeepModification;
                $newData = [];

                foreach ($fillableData as $key => $row) {
                    if ($row instanceof Model) {
                        $entity = $row;
                    } else {
                        if (is_integer($row)) {
                            $entity = $klass::findOrFail($row);
                        } else {
                            if (!empty($row[$primaryKey])) {
                                $entity = $klass::findOrFail($row[$primaryKey]);
                                if ($allowDeepModification) {
                                    $entity->fill($row);
                                    $entity->save();
                                }
                            } else {
                                if ($allowDeepModification) {
                                    $entity = $klass::newModelInstance();
                                    $entity->fill($row);
                                    $entity->save();
                                }
                            }
                        }
                    }

                    if (!empty($row['pivot'])) {
                        $newData[ $entity->$primaryKey ] = $row['pivot'];
                    } else {
                        if (!empty($entity)) {
                            $newData[ $entity->$primaryKey ] = [];
                        }
                    }
                    // $newData[] = $entity->$primaryKey;
                }

                //sync relations from the data (delete, add, update)
                $relation->sync($newData);
            } else {
                throw new RuntimeException("Unknown or unfillable relation type $relationName");
            }
        }
    }

    public function fillWithRelations(array $attributes)
    {
        //data,
        //filter out relations
        //save
        //save relations -->
        // filter out subrelations
        //save

        list($fillableRelationsData, $attributes) = $this->extractFillableRelations($attributes);
        parent::fill($attributes);
        $this->fillRelations($fillableRelationsData);

        return $this;
    }


    public static function create(array $attributes = [])
    {
        list($fillableRelationsData, $attributes) = (new static)->extractFillableRelations($attributes);
        $model = new static($attributes);
        $model->save();
        $model->fillRelations($fillableRelationsData);
        return $model;
    }
}
