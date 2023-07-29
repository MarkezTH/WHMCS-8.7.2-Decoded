<?php

namespace WHMCS\Language;

class TranslatorBridge implements \Illuminate\Contracts\Translation\Translator
{
    private $language = NULL;

    public function __construct(AbstractLanguage $language)
    {
        $this->language = $language;
    }

    public function get($key, $replace = [], $locale = NULL)
    {
        return $this->language->trans($key, $replace);
    }

    public function choice($key, $number, $replace = [], $locale = NULL)
    {
        return $this->get($key, $replace, $locale);
    }

    public function getLocale()
    {
        return $this->language->getLocale();
    }

    public function setLocale($locale)
    {
        throw new \WHMCS\Exception("Not implemented");
    }
}
