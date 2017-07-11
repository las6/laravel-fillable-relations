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

    public function extractFillableRelations(array $attributes)
    {
        $fillableRelationsData = [];
        foreach ($this->fillableRelations() as $relationName) {
            $val = array_pull($attributes, $relationName);
            if ($val) {
                $fillableRelationsData[$relationName] = $val;
            }
        }
        return [$fillableRelationsData, $attributes];
    }

    public function fillRelations(array $fillableRelationsData)
    {

        // global $dirtttty;
        // $dirtttty++;
        // var_dump($fillableRelationsData,$dirtttty);
        // if ($dirtttty>6) die();
        
        foreach ($fillableRelationsData as $relationName => $fillableData) {
            $camelCaseName = camel_case($relationName);
            $relation = $this->{$camelCaseName}();
            $klass = get_class($relation->getRelated());
            if ($relation instanceof BelongsTo) {
                if ($fillableData instanceof Model) {
                    $entity = $fillableData;
                } else {
                    // find with all object properties
                    // $entity = $klass::where($fillableData)->firstOrFail();


                    if (is_array($fillableData)) {
                        // find with all object properties
                        $entity = $klass::where($fillableData)->firstOrFail();
                    } else {                            
                        $entity = $klass::findOrFail($fillableData);
                    }

                }
                $relation->associate($entity);
            } elseif ($relation instanceof HasOne) {
                if ($fillableData instanceof Model) {
                    $entity = $fillableData;
                } else {
                    // find or create with all object properties
                    $entity = $klass::firstOrCreate($fillableData);
                }
                $qualified_foreign_key = $relation->getForeignKey();
                list($table, $foreign_key) = explode('.', $qualified_foreign_key);
                $qualified_local_key_name = $relation->getQualifiedParentKeyName();
                list($table, $local_key) = explode('.', $qualified_local_key_name);
                $this->{$local_key} = $entity->{$foreign_key};
            } elseif ($relation instanceof HasMany) {
                if (!$this->exists) {
                    $this->save();
                }
                                        
                
                $entities = [];
                foreach ($fillableData as $row) {
                    if ($row instanceof Model) {
                        $entities[] = $row;
                        // $relation->save($entity);
                    } else {
                        // $entity = new $klass($row);
                        $entity = false;
                        if (is_array($row)) {
                            if (!empty($row['id'])) {
                            
                                if ($entity = $klass::findOrFail($row['id'])) {
                                    // $entity->update($row);
                                    $entities[] = $entity;
                                }

                            } 
                            // if (empty($entity)) {
                            //     $relation->create($row);
                            // }
                        } else {                            
                            $entities[] = $klass::findOrFail($row);
                            // $relation->save($entity);
                        }
                    }
                }

                var_dump($entities,'ughj');
                $relation->saveMany($entities);
                die();
                $relation->delete();

                // $relation->delete();

            } elseif ($relation instanceof BelongsToMany) {
                if (!$this->exists) {
                    $this->save();
                }
                $relation->detach();
                foreach ($fillableData as $row) {
                    if ($row instanceof Model) {
                        $entity = $row;
                    } else {

                        if (is_array($row)) {
                            // find with all object properties
                            $entity = $klass::where($row)->firstOrFail();
                        } else {                            
                            $entity = $klass::findOrFail($row);
                        }
                    }
                    $relation->attach($entity);
                }
            } else {
                throw new RuntimeException("Unknown or unfillable relation type $relationName");
            }
        }
    }

    public function fill(array $attributes)
    {
        list($fillableRelationsData, $attributes) = $this->extractFillableRelations($attributes);
        parent::fill($attributes);
        $this->fillRelations($fillableRelationsData);        

        return $this;
    }

    public static function create(array $attributes = [])
    {
        list($fillableRelationsData, $attributes) = (new static)->extractFillableRelations($attributes);
        $model = new static($attributes);
        $model->fillRelations($fillableRelationsData);
        $model->save();
        return $model;
    }
}
