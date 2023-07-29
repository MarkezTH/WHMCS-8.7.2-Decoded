<?php

namespace WHMCS\Apps\Category;

class Collection
{
    public $categories = [];

    public function __construct()
    {
        foreach ((new \WHMCS\Apps\Feed())->categories() as $values) {
            $this->categories[$values["slug"]] = new Model($values);
        }
    }

    public function all()
    {
        return $this->categories;
    }

    public function first()
    {
        foreach ($this->categories as $category) {
            return $category;
        }
        return NULL;
    }

    public function homeFeatured()
    {
        $categoriesToReturn = [];
        foreach ($this->all() as $slug => $category) {
            if ($category->includeInHomeFeatured()) {
                $categoriesToReturn[$slug] = $category;
            }
        }
        return $categoriesToReturn;
    }

    public function getCategoryBySlug($slug)
    {
        return isset($this->categories[$slug]) ? $this->categories[$slug] : NULL;
    }

    public function getAllFeaturedKeys()
    {
        $allFeaturedAppKeys = [];
        foreach ($this->categories as $category) {
            $allFeaturedAppKeys = array_merge($allFeaturedAppKeys, $category->getFeaturedAppKeys());
        }
        return $allFeaturedAppKeys;
    }
}
