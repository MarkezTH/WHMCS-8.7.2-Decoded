<?php

namespace WHMCS\Language\Loader;

class DatabaseLoader extends \Symfony\Component\Translation\Loader\ArrayLoader implements \Symfony\Component\Translation\Loader\LoaderInterface
{
    public function load($resource, $locale, $domain = "dynamicMessages")
    {
        $dynamicMessages = [];
        \WHMCS\Language\DynamicTranslation::where("language", "=", $locale)->get(["related_type", "related_id", "translation"])->map(function (\WHMCS\Language\DynamicTranslation $translation) use($dynamicMessages) {
            $keyChunks = explode(".", $translation->relatedType);
            $thisTranslation = \WHMCS\Input\Sanitize::decode($translation->translation);
            if (end($keyChunks) !== "description") {
                $thisTranslation = strip_tags($thisTranslation);
            }
            $dynamicMessages[str_replace("{id}", $translation->relatedId, $translation->relatedType)] = $thisTranslation;
        });
        return parent::load($dynamicMessages, $locale, $domain);
    }
}
