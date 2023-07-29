<?php

namespace WHMCS\Language;

abstract class AbstractLanguage extends \Symfony\Component\Translation\Translator
{
    protected $globalVariable = "";
    protected $name = NULL;
    protected static $languageCache = NULL;
    protected static $locales = [];
    const FALLBACK_LANGUAGE = "english";
    const SESSION_LANGUAGE_NAME = "Language";

    public function __construct($name = "english", $fallback = self::FALLBACK_LANGUAGE, $languageDirectoryOverride = NULL)
    {
        $languageDirectory = is_null($languageDirectoryOverride) ? static::getDirectory() : $languageDirectoryOverride;
        $path = $languageDirectory . DIRECTORY_SEPARATOR . $name . ".php";
        $overridePath = $languageDirectory . DIRECTORY_SEPARATOR . "overrides" . DIRECTORY_SEPARATOR . $name . ".php";
        $fallbackPath = $languageDirectory . DIRECTORY_SEPARATOR . $fallback . ".php";
        $fallbackLocales = array_unique([$name, $fallback]);
        parent::__construct("override_" . $name);
        $this->setFallbackLocales($fallbackLocales);
        $this->addLoader("whmcs", new Loader\WhmcsLoader($this->globalVariable));
        $this->addLoader("dynamic", new Loader\DatabaseLoader());
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            $this->addResource("dynamic", NULL, $name, "dynamicMessages");
            $fallbackLanguage = \WHMCS\Config\Setting::getValue("Language");
            if ($fallbackLanguage != $name) {
                $this->addResource("dynamic", NULL, $fallbackLanguage, "dynamicMessages");
            }
        }
        if (file_exists($overridePath)) {
            $this->addResource("whmcs", $overridePath, "override_" . $name);
        }
        if (file_exists($path)) {
            $this->addResource("whmcs", $path, $name);
        }
        if ($fallbackPath != $path && file_exists($fallbackPath)) {
            $this->addResource("whmcs", $fallbackPath, $fallback);
        }
    }

    public static function getLanguages($languageDirectoryOverride = NULL)
    {
        $languages = [];
        $languageDirectory = is_null($languageDirectoryOverride) ? static::getDirectory() : $languageDirectoryOverride;
        if (!is_null($languagesCache) && isset($languagesCache[$languageDirectory])) {
            return $languagesCache[$languageDirectory];
        }
        $glob = glob($languageDirectory . DIRECTORY_SEPARATOR . "*.php");
        if ($glob === false) {
            throw new \WHMCS\Exception("Unable to read language directory.");
        }
        foreach ($glob as $languageFile) {
            $languageName = pathinfo($languageFile, PATHINFO_FILENAME);
            if (preg_match("/^[a-z0-9@_\\.\\-]*\$/i", $languageName) && $languageName != "index") {
                $languages[] = $languageName;
            }
        }
        if (count($languages) == 0) {
            throw new \WHMCS\Exception("Could not find any language files.");
        }
        $languagesCache[$languageDirectory] = $languages;
        return $languages;
    }

    public static function getValidLanguageName($language, $fallback = self::FALLBACK_LANGUAGE)
    {
        $language = strtolower(trim($language));
        $fallback = strtolower(trim($fallback));
        $englishFallback = strtolower(trim(self::FALLBACK_LANGUAGE));
        $languages = static::getLanguages();
        if (!in_array($language, $languages)) {
            if (in_array($englishFallback, $languages)) {
                $language = in_array($fallback, $languages) ? $fallback : $englishFallback;
            } else {
                $language = in_array($fallback, $languages) ? $fallback : $languages[0];
            }
        }
        return $language;
    }

    public static function getLocales()
    {
        $locales = self::$locales;
        $class = get_called_class();
        if (0 < count($locales)) {
            return $locales;
        }
        $transientData = new \WHMCS\TransientData();
        $cachedLocales = $transientData->retrieve($class . "Locales");
        if ($cachedLocales) {
            $cachedLocales = json_decode($cachedLocales, true);
            if ($cachedLocales["hash"] == md5(implode(",", static::getLanguages()))) {
                $locales = $cachedLocales["locales"];
                return $locales;
            }
        }
        foreach (static::getLanguages() as $language) {
            ${$language} = new $class($language);
            $locale = ${$language}->getLanguageLocale();
            list($languageCode, $countryCode) = explode("_", $locale, 2);
            $locales[] = ["locale" => $locale, "language" => $language, "languageCode" => $languageCode, "countryCode" => $countryCode, "localisedName" => upperCaseFirstLetter(\Punic\Language::getName($languageCode, $locale, true))];
        }
        $transientData->store($class . "Locales", json_encode(["hash" => md5(implode(",", static::getLanguages())), "locales" => $locales]), 86400);
        return $locales;
    }

    public function getLanguageLocale()
    {
        return $this->trans("locale");
    }

    public function getName()
    {
        return str_replace("override_", "", $this->getLocale());
    }

    public function getLocaleMetadata()
    {
        $locales = static::getLocales();
        $currentLanguage = static::getName();
        $activeLocale = static::defaultLocaleMetadata();
        foreach ($locales as $locale) {
            if ($locale["language"] == $currentLanguage) {
                $activeLocale = $locale;
                return $activeLocale;
            }
        }
    }

    protected function defaultLocaleMetadata()
    {
        $countryCode = "GB";
        $languageCode = "en";
        $locale = $languageCode . "_" . $countryCode;
        return ["locale" => $locale, "language" => "english", "languageCode" => $languageCode, "countryCode" => $countryCode, "localisedName" => upperCaseFirstLetter(\Punic\Language::getName($languageCode, $locale, true))];
    }

    public function toArray()
    {
        $return = [];
        $messages = [];
        $catalogue = $this->getCatalogue("override_" . $this->getName());
        if ($catalogue) {
            $messages = $catalogue->all();
            while ($catalogue = $catalogue->getFallbackCatalogue()) {
                $messages = array_replace_recursive($catalogue->all(), $messages);
            }
        }
        $messages = isset($messages["messages"]) ? $messages["messages"] : [];
        foreach ($messages as $key => $value) {
            $this->unFlatten($return, $key, $value);
        }
        return $return;
    }

    protected function unFlatten(&$messages, $key, $value)
    {
        if (strpos($key, ".") === false) {
            $messages[$key] = $value;
        } else {
            list($key, $remainder) = explode(".", $key, 2);
            if (!isset($messages[$key])) {
                $messages[$key] = [];
            }
            $this->unFlatten($messages[$key], $remainder, $value);
        }
    }

    public function getFile()
    {
        return static::getDirectory() . DIRECTORY_SEPARATOR . $this->getName() . ".php";
    }

    public function synchronizeFileWith(AbstractLanguage $language, $saveTo = NULL)
    {
        $localMessages = $this->getCatalogue("messages");
        if ($localMessages->all() == []) {
            $localMessages = $localMessages->getFallbackCatalogue()->all();
        }
        $languageMessages = $language->getCatalogue("messages");
        if ($languageMessages->all() == []) {
            $languageMessages = $languageMessages->getFallbackCatalogue("messages")->all();
        }
        foreach ($languageMessages["messages"] as $key => $message) {
            if (!isset($localMessages["messages"][$key])) {
                $localMessages["messages"][$key] = $message;
            }
        }
        $newFileContents = [];
        $localFileContents = file($this->getFile(), FILE_IGNORE_NEW_LINES);
        foreach (file($language->getFile(), FILE_IGNORE_NEW_LINES) as $lineNumber => $line) {
            if (strpos($line, "\$") === 0) {
                list($originalKey) = explode("=", $line, 2);
                $originalKey = trim($originalKey);
                $key = str_replace(["\$" . $this->globalVariable, "[", "]"], "", $originalKey);
                $key = str_replace(["''", "\"\""], ".", $key);
                $key = str_replace(["\"", "'"], "", $key);
                $translatedMessage = str_replace("\"", "\\\"", $localMessages["messages"][$key]);
                $translatedMessage = str_replace("\n", "\\n", $translatedMessage);
                $originalKey = str_replace("\"", "'", $originalKey);
                $newFileContents[] = $originalKey . " = \"" . $translatedMessage . "\";";
            } else {
                if (strpos($line, "//////////") !== 0) {
                    if (strpos($line, " *") === 0) {
                        $newFileContents[] = $localFileContents[$lineNumber];
                    } else {
                        $newFileContents[] = $line;
                    }
                }
            }
        }
        $saveTo = is_null($saveTo) ? $this->getFile() : $saveTo;
        file_put_contents($saveTo, implode("\n", $newFileContents) . $this->getLanguageFileFooter());
        return $this;
    }

    public function locateDuplicates()
    {
        _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F6C616E67756167652F61627374726163746C616E67756167652E7068703078376630316332633664616136_();
        $duplicateStruct = $easytoyou_error_decompile;
        ${$this->globalVariable} = [];
        $duplicates = [];
        $translationAssignmentSyntaxSet = file($this->getFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($translationAssignmentSyntaxSet as $line) {
            if (strpos($line, "\$" . $this->globalVariable) === false) {
            } else {
                $langKeys = self::extractEntryKeys($line);
                if (self::arrayKeysExistDeep(${$this->globalVariable}, $langKeys)) {
                    $dupeId = implode(".", $langKeys);
                    if (!isset($duplicates[$dupeId])) {
                        $duplicates[$dupeId] = clone $duplicateStruct;
                        $duplicates[$dupeId]->keyPath = $langKeys;
                    }
                    $duplicates[$dupeId]->entries[] = $this->translationEntry($langKeys, self::offsetGetDeep(${$this->globalVariable}, $langKeys));
                }
                eval($line);
            }
        }
        foreach ($duplicates as &$dupe) {
            $dupe->active = $this->translationEntry($dupe->keyPath, self::offsetGetDeep(${$this->globalVariable}, $dupe->keyPath));
        }
        unset($dupe);
        return $duplicates;
    }

    public function locateNumericKeys()
    {
        _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F6C616E67756167652F61627374726163746C616E67756167652E7068703078376630316332633665343836_();
        $numericStruct = $easytoyou_error_decompile;
        $numerics = [];
        $translationAssignmentSyntaxSet = file($this->getFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($translationAssignmentSyntaxSet as $lineNum => $line) {
            if (strpos($line, "\$" . $this->globalVariable) === false) {
            } else {
                explode("=", $line, 2);
                $variablePart = explode("=", $line, 2)[0];
                $langKeys = self::extractEntryKeys($variablePart);
                if (preg_match("/\\[\\d+\\]/", $variablePart) === 1) {
                    $detected = clone $numericStruct;
                    $detected->keyPath = implode(".", $langKeys);
                    $detected->entry = $line;
                    $detected->lineNumber = $lineNum;
                    $numerics[] = $detected;
                    unset($detected);
                }
            }
        }
        return $numerics;
    }

    public function translationEntry($keys, $value)
    {
        return vsprintf(sprintf("\$%s%s = \"%%s\";", $this->globalVariable, str_repeat("['%s']", count($keys))), array_merge($keys, [$value]));
    }

    public static function extractEntryKeys($translationAssignmentSyntax)
    {
        $matches = NULL;
        preg_match_all("/\\['?([^'\\[\\]]+)'?\\]/", $translationAssignmentSyntax, $matches, PREG_PATTERN_ORDER);
        if (isset($matches[1])) {
            return $matches[1];
        }
        return [];
    }

    public static function &offsetGetDeep(&$source, $keys)
    {
        $sourcePointer =& $source;
        $exists = true;
        foreach ($keys as $key) {
            if (isset($sourcePointer[$key])) {
                $sourcePointer =& $sourcePointer[$key];
            } else {
                $exists = false;
                if (!$exists) {
                    return $exists;
                }
                return $sourcePointer;
            }
        }
    }

    public static function arrayKeysExistDeep(&$source, $keys)
    {
        return self::offsetGetDeep($source, $keys) !== false;
    }

    public function getLanguageFileFooter()
    {
        return "\n";
    }

    protected static function findOrCreate($languageName)
    {
        $className = get_called_class();
        $scope = $className . "." . $languageName;
        if (empty(self::$languageCache[$scope])) {
            $language = new $className($languageName);
            self::$languageCache[$scope] = $language;
        } else {
            $language = self::$languageCache[$scope];
        }
        return $language;
    }

    public static function saveToSession($languageName)
    {
        \WHMCS\Session::set(self::SESSION_LANGUAGE_NAME, $languageName);
    }

    public static function getFromSession()
    {
        return \WHMCS\Session::get(self::SESSION_LANGUAGE_NAME);
    }
}
class _obfuscated_5C636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F4C616E67756167652F41627374726163744C616E67756167652E7068703078376630316332633664616136_
{
    public $keyPath = NULL;
    public $entries = [];
    public $active = NULL;
}
class _obfuscated_5C636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F4C616E67756167652F41627374726163744C616E67756167652E7068703078376630316332633665343836_
{
    public $keyPath = NULL;
    public $entry = NULL;
    public $lineNumber = NULL;
}
