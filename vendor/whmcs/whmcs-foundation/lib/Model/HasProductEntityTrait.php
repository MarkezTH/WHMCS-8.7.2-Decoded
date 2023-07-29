<?php

namespace WHMCS\Model;

trait HasProductEntityTrait
{
    protected static $productRelationTypes = NULL;

    public static function loadRelationClassMap()
    {
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(self::$productRelationTypes);
    }

    public function relationEntity()
    {
        return $this->morphTo("rel");
    }

    protected function getRelatedType($relation)
    {
        $relType = get_class($relation);
        foreach (self::$productRelationTypes as $type => $baseClass) {
            if ($relation instanceof $baseClass) {
                $relType = $type;
                return $relType;
            }
        }
    }

    public function setRelationEntityAttribute(AbstractModel $model)
    {
        $this->rel_id = $model->id;
        $this->rel_type = $this->getRelatedType($model);
    }

    public function scopeOfRelated($query, $relation)
    {
        $relType = $this->getRelatedType($relation);
        return $query->where("rel_type", $relType)->where("rel_id", $relation->id);
    }
}
