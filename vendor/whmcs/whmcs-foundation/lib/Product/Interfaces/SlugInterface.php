<?php

namespace WHMCS\Product\Interfaces;

interface SlugInterface
{
    const INVALID_EMPTY = "slugInvalidEmpty";
    const INVALID_HYPHEN = "slugInvalidHyphen";
    const INVALID_NUMERIC = "slugInvalidFormat";

    public function validateSlugIsUnique($slug);

    public function validateSlugFormat($slug);

    public function autoGenerateUniqueSlug();

    public function getExistingSlugCheck($Builder, $slug);
}
