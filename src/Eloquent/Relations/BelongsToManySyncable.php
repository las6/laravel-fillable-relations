<?php
namespace LaravelFillableRelations\Eloquent\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BelongsToManySyncable extends BelongsToMany
{

 /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     *
     * @param  \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|array  $ids
     * @param  bool   $detaching
     * @return array
     */
    
    public function sync($data, $detaching = true)
    {

        $collection = new \Illuminate\Database\Eloquent\Collection;
        foreach ($data as $key => $value) {
            $collection->add($value);
        }
        $ids = $collection->modelKeys();

        
        // var_dump($ids, $collection);
        // die("ugh");
        $changes = [
            'attached' => [], 'detached' => [], 'updated' => [],
        ];



        // First we need to attach any of the associated models that are not currently
        // in this joining table. We'll spin through the given IDs, checking to see
        // if they exist in the array of current ones, and if not we will insert.
        $current = $this->newPivotQuery()->pluck(
            $this->relatedKey
        )->all();

        $detach = array_diff($current, array_keys(
            $records = $this->formatRecordsList((array) $this->parseIds($ids))
        ));


        var_dump($data, $records);
        die();


        // Next, we will take the differences of the currents and given IDs and detach
        // all of the entities that exist in the "current" array but are not in the
        // array of the new IDs given to the method which will complete the sync.
        if ($detaching && count($detach) > 0) {
            $this->detach($detach);

            $changes['detached'] = $this->castKeys($detach);
        }

        // Now we are finally ready to attach the new records. Note that we'll disable
        // touching until after the entire operation is complete so we don't fire a
        // ton of touch operations until we are totally done syncing the records.
        $changes = array_merge(
            $changes, $this->attachNew($records, $current, false)
        );

        // Once we have finished attaching or detaching the records, we will see if we
        // have done any attaching or detaching, and if we have we will touch these
        // relationships if they are configured to touch on any database updates.
        if (count($changes['attached']) ||
            count($changes['updated'])) {
            $this->touchIfTouching();
        }

        //update all relations?
        array_diff($current,$detach);

        var_dump($changes);
        die();
        return $changes;
    }
    /*
    public function sync($data, $deleting = true)
    {
        
        $changes = [
            'created' => [], 'deleted' => [], 'updated' => [],
        ];

        $relatedKeyName = $this->related->getKeyName();

        // First we need to attach any of the associated models that are not currently
        // in the child entity table. We'll spin through the given IDs, checking to see
        // if they exist in the array of current ones, and if not we will insert.
        $current = $this->newQuery()->pluck(
            $relatedKeyName
        )->all();
    
        // Separate the submitted data into "update" and "new"
        $updateRows = [];
        $newRows = [];
        foreach ($data as $row) {
            // We determine "updateable" rows as those whose $relatedKeyName (usually 'id') is set, not empty, and
            // match a related row in the database.

            if (!empty($row)) {
                if (!is_array($row)) {
                    $row = [ $relatedKeyName => $row ];
                }
            }

            if (isset($row[$relatedKeyName]) && !empty($row[$relatedKeyName]) && in_array($row[$relatedKeyName], $current)) {
                $id = $row[$relatedKeyName];
                $updateRows[$id] = $row;
            } else {
                // var_dump($row);
                // die();
                if (!empty($row) || is_array($row)) {
                    $newRows[] = $row;
                }
            }
        }

        // Next, we'll determine the rows in the database that aren't in the "update" list.
        // These rows will be scheduled for deletion.  Again, we determine based on the relatedKeyName (typically 'id').
        $updateIds = array_keys($updateRows);
        $deleteIds = [];
        foreach ($current as $currentId) {
            if (!in_array($currentId, $updateIds)) {
                $deleteIds[] = $currentId;
            }
        }


        // Update the updatable rows
        foreach ($updateRows as $id => $row) {
            $this->getRelated()->where($relatedKeyName, $id)
                 ->update($row);
        }
        
        $changes['updated'] = $this->castKeys($updateIds);

        // Insert the new rows
        $newIds = [];
        foreach ($newRows as $row) {
            $newModel = $this->create($row);
            $newIds[] = $newModel->$relatedKeyName;
        }

        $changes['created'][] = $this->castKeys($newIds);

        // Do deletion last, as if create fails, we don't want to run this. (maybe transaction would be better?)
        // Delete any non-matching rows
        if ($deleting && count($deleteIds) > 0) {
            $this->getRelated()->destroy($deleteIds);

            $changes['deleted'] = $this->castKeys($deleteIds);
        }
        return $changes;
    }
*/
}
?>