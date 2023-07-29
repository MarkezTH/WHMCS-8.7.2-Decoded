<?php

namespace WHMCS\Product;

trait SlugTrait
{
    public function validateSlugFormat($slug)
    {
        if (is_numeric($slug)) {
            throw new \WHMCS\Exception\Validation\InvalidValue(Interfaces\SlugInterface::INVALID_NUMERIC);
        }
        if (empty($slug)) {
            throw new \WHMCS\Exception\Validation\InvalidValue(Interfaces\SlugInterface::INVALID_EMPTY);
        }
        if (substr($slug, 0, 1) === "-") {
            throw new \WHMCS\Exception\Validation\InvalidValue(Interfaces\SlugInterface::INVALID_HYPHEN);
        }
        return true;
    }

    public function autoGenerateUniqueSlug()
    {
        $name = \WHMCS\Input\Sanitize::decode($this->name);
        $name = preg_replace("/\\s*&\\s*/", " and ", $name);
        $slug = \Illuminate\Support\Str::slug(\voku\helper\ASCII::to_transliterate($name));
        try {
            $isValidFormat = $this->validateSlugFormat($slug);
        } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
            $isValidFormat = false;
        }
        if (empty($this->name) || $isValidFormat !== true) {
            return "";
        }
        $count = 0;
        $currentSuffix = "";
        $maxLoops = 1000;
        while (0 < $maxLoops-- && $this->getExistingSlugCheck($slug)->exists()) {
            if ($currentSuffix) {
                $slug = substr($slug, 0, strlen($currentSuffix) * -1);
            }
            $count++;
            $currentSuffix = "-" . $count;
            if ($slug) {
                $slug .= $currentSuffix;
            }
        }
        return $slug;
    }

    public function getExistingSlugCheck($slug)
    {
        throw new \WHMCS\Exception\Validation\Required("Missing Implementation");
    }

    public function validateSlugIsUnique($slug)
    {
        throw new \WHMCS\Exception\Validation\Required("Missing Implementation");
    }
}
