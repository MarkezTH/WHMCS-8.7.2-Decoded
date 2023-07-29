<?php

namespace WHMCS\Language;

class ClientLanguage extends AbstractLanguage
{
    protected $globalVariable = "_LANG";

    public static function getDirectory()
    {
        return ROOTDIR . DIRECTORY_SEPARATOR . "lang";
    }

    public static function factory($systemLanguage = "", $sessionLanguage = "", $requestLanguage = "", $inClientArea = false)
    {
        $languageName = $systemLanguage;
        $fallback = $languageName;
        if ($sessionLanguage != "") {
            $languageName = $sessionLanguage;
        }
        $updateLanguagePref = false;
        if ($inClientArea && $requestLanguage != "") {
            $updateLanguagePref = true;
            $languageName = $requestLanguage;
        }
        $requestedLanguage = trim(strtolower($languageName));
        $languageName = self::getValidLanguageName($languageName, $fallback);
        if ($requestedLanguage != $languageName) {
            $updateLanguagePref = false;
        }
        $language = static::findOrCreate($languageName);
        if ($updateLanguagePref) {
            self::saveToSession($languageName);
            $user = \Auth::user();
            if ($user) {
                $user->language = $languageName;
                $user->save();
            }
        }
        return $language;
    }

    public function getLanguageFileFooter()
    {
        return "\n////////// End of " . $this->getName() . " language file.  Do not place any translation strings below this line!\n";
    }

    public static function getDefault()
    {
        return strtolower(\WHMCS\Config\Setting::getValue("Language"));
    }
}
