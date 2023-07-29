<?php

namespace WHMCS\Model;

class AbstractModel extends \Illuminate\Database\Eloquent\Model implements Contracts\ModelInterface
{
    use Traits\DateTimeTrait;
    public $unique = [];
    public $guardedForUpdate = [];
    protected $columnMap = [];
    protected $booleans = [];
    protected $strings = [];
    protected $ints = [];
    protected $fillable = [];
    protected $semanticVersions = [];
    protected $commaSeparated = [];
    protected $characterSeparated = [];
    protected static $tableColumnCache = [];
    protected $rules = NULL;
    protected $errors = [];
    protected $customValidationMessages = [];

    public static function boot()
    {
        parent::boot();
        self::observe(new Observer());
    }

    public function clearColumnCache()
    {
        if (isset(static::$tableColumnCache[$this->table])) {
            unset(static::$tableColumnCache[$this->table]);
        }
        return $this;
    }

    protected function hasColumn($column)
    {
        if (!isset(static::$tableColumnCache[$this->table])) {
            static::$tableColumnCache[$this->table] = ["columns" => [], "not-exists" => []];
        }
        if (in_array($column, static::$tableColumnCache[$this->table]["columns"])) {
            return true;
        }
        if (in_array($column, static::$tableColumnCache[$this->table]["not-exists"])) {
            return false;
        }
        static::$tableColumnCache[$this->table]["columns"] = array_map("strtolower", \WHMCS\Database\Capsule::schema()->getColumnListing($this->table));
        if (in_array($column, static::$tableColumnCache[$this->table]["columns"])) {
            return true;
        }
        static::$tableColumnCache[$this->table]["not-exists"][] = $column;
        return false;
    }

    public function getAttribute($key)
    {
        $originalKey = $key;
        $isColumnMapped = array_key_exists($key, $this->columnMap);
        if ($isColumnMapped) {
            $key = $this->columnMap[$key];
        }
        if ($isColumnMapped && in_array($originalKey, $this->getDates())) {
            $dateValue = parent::getAttribute($key);
            if (in_array($dateValue, ["0000-00-00 00:00:00", "0000-00-00"]) || empty($dateValue) || $dateValue instanceof \WHMCS\Carbon && $dateValue->year < 0) {
                $value = \WHMCS\Carbon::createFromTimestamp(0, "UTC");
            } else {
                $value = $this->asDateTime($dateValue);
            }
        } else {
            $value = parent::getAttribute($key);
        }
        if (is_null($value)) {
            $value = parent::getAttribute($this->snakeCase($key));
        }
        if (is_null($value)) {
            $value = parent::getAttribute(strtolower($key));
        }
        if ($isColumnMapped && $this->hasGetMutator($originalKey)) {
            $value = $this->mutateAttribute($originalKey, $value);
        }
        $isBoolean = $this->isBooleanColumn($originalKey) || $this->isBooleanColumn($key);
        $isSemanticVersion = $this->isSemanticVersionColumn($originalKey) || $this->isSemanticVersionColumn($key);
        $isCommaSeparated = $this->isCommaSeparatedColumn($originalKey) || $this->isCommaSeparatedColumn($key);
        if ($isBoolean) {
            $value = $this->asBoolean($value);
        } else {
            if ($isSemanticVersion) {
                $value = $this->asSemanticVersion($value);
            } else {
                if ($isCommaSeparated) {
                    $value = $this->asArrayFromCharacterSeparatedValue((string) $value, ",");
                } else {
                    foreach ($this->characterSeparated as $character => $columns) {
                        if (in_array($originalKey, $columns) || in_array($key, $columns)) {
                            $value = $this->asArrayFromCharacterSeparatedValue((string) $value, $character);
                        }
                    }
                }
            }
        }
        return $value;
    }

    private function snakeCase($value)
    {
        return (string) \Illuminate\Support\Str::of($value)->snake();
    }

    public function isAttributeSet($key)
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]) || $this->hasGetMutator($key) && !is_null($this->getAttributeValue($key));
    }

    public function setAttribute($key, $value)
    {
        $originalKey = $key;
        if (!$this->isAttributeSet($key)) {
            if ($this->isAttributeSet($this->snakeCase($key)) || $this->hasColumn($this->snakeCase($key))) {
                $key = $this->snakeCase($key);
            } else {
                if (array_key_exists($key, $this->columnMap)) {
                    $key = $this->columnMap[$key];
                } else {
                    $key = strtolower($key);
                }
            }
        }
        if (in_array($originalKey, $this->booleans)) {
            $value = $this->fromBoolean($value);
        } else {
            if (in_array($originalKey, $this->strings)) {
                $value = $this->fromString($value);
            } else {
                if (in_array($originalKey, $this->ints)) {
                    $value = $this->fromInt($value);
                } else {
                    if (in_array($originalKey, $this->semanticVersions)) {
                        $value = $this->fromSemanticVersion($value);
                    } else {
                        if (in_array($originalKey, $this->commaSeparated)) {
                            $value = $this->fromArrayToCharacterSeparatedValue($value);
                        } else {
                            foreach ($this->characterSeparated as $character => $columns) {
                                if (in_array($originalKey, $columns)) {
                                    $value = $this->fromArrayToCharacterSeparatedValue($value, $character);
                                }
                            }
                        }
                    }
                }
            }
        }
        return parent::setAttribute($key, $value);
    }

    public function getRawAttribute($key = NULL, $default = NULL)
    {
        return \Illuminate\Support\Arr::get($this->attributes, $key, $default);
    }

    public function fromBoolean($value)
    {
        return (int) (bool) $value;
    }

    public function fromString($value)
    {
        return (string) $value;
    }

    public function fromInt($value)
    {
        return (int) $value;
    }

    public function asBoolean($value)
    {
        return (bool) $value;
    }

    public function asArrayFromCharacterSeparatedValue($data, $character)
    {
        return array_values(array_filter(array_map(function ($item) {
            $item = trim($item);
            return strlen($item) ? $item : NULL;
        }, explode($character, trim($data, $character)))));
    }

    public function fromArrayToCharacterSeparatedValue($list = ",", $character)
    {
        if (!count(array_filter($list, function ($item) {
            return $item !== "";
        }))) {
            return "";
        }
        $data = implode($character, array_map("trim", $list));
        $data = str_replace($character . $character, $character, $data);
        $data = $character . trim($data, $character) . $character;
        return $data;
    }

    public function fromSemanticVersion(\WHMCS\Version\SemanticVersion $version)
    {
        return $version->getCanonical();
    }

    public function asSemanticVersion($version)
    {
        return new \WHMCS\Version\SemanticVersion($version);
    }

    public static function convertBoolean($value)
    {
        if (!$value || is_string($value) && ($value == "off" || $value == "")) {
            return false;
        }
        return true;
    }

    public static function convertBooleanColumn($column)
    {
        $class = get_called_class();
        $object = new $class();
        $table = $object->getTable();
        \WHMCS\Database\Capsule::table($table)->where($column, "off")->update([$column => ""]);
        \WHMCS\Database\Capsule::table($table)->where($column, "!=", "")->where($column, "!=", "0")->update([$column => 1]);
        \WHMCS\Database\Capsule::table($table)->where($column, "")->update([$column => 0]);
        \WHMCS\Database\Capsule::connection()->getPdo()->exec("alter table `" . $table . "` change `" . $column . "` `" . $column . "` tinyint(1) not null");
    }

    public static function convertUnixTimestampIntegerToTimestampColumn($column)
    {
        $class = get_called_class();
        $object = new $class();
        $tableName = $object->getTable();
        $tempColumn = $column . "_temp";
        \WHMCS\Database\Capsule::schema()->table($tableName, function ($table) use($tempColumn) {
            $table->timestamp($tempColumn);
        });
        $pdo = \WHMCS\Database\Capsule::connection()->getPdo();
        $statement = $pdo->prepare("update `" . $tableName . "` set `" . $tempColumn . "` = FROM_UNIXTIME(" . $column . ")");
        $statement->execute();
        \WHMCS\Database\Capsule::schema()->table($tableName, function ($table) use($column) {
            $table->dropColumn($column);
        });
        \WHMCS\Database\Capsule::schema()->table($tableName, function () use($tableName, $column, $tempColumn) {
            $pdo = \WHMCS\Database\Capsule::connection()->getPdo();
            $statement = $pdo->prepare("alter table `" . $tableName . "`" . " change `" . $tempColumn . "`" . " `" . $column . "` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'");
            $statement->execute();
        });
    }

    protected function isBooleanColumn($column)
    {
        return in_array($column, $this->booleans);
    }

    protected function isSemanticVersionColumn($column)
    {
        return in_array($column, $this->semanticVersions);
    }

    protected function isCommaSeparatedColumn($column)
    {
        return in_array($column, $this->commaSeparated);
    }

    protected function decryptValue($cipherText, $key)
    {
        return \WHMCS\Database\Capsule::connection()->selectOne("select AES_DECRYPT(?, ?) as decrypted", [$cipherText, $key])->decrypted;
    }

    protected function encryptValue($text, $key)
    {
        return \WHMCS\Database\Capsule::connection()->selectOne("select AES_ENCRYPT(?, ?) as encrypted", [$text, $key])->encrypted;
    }

    protected function aesEncryptValue($text, $key)
    {
        $encryption = new \WHMCS\Security\Encryption\Aes();
        $encryption->setKey($key);
        return $encryption->encrypt($text);
    }

    protected function aesDecryptValue($text, $key)
    {
        $encryption = new \WHMCS\Security\Encryption\Aes();
        $encryption->setKey($key);
        return $encryption->decrypt($text);
    }

    protected function isAesDecryptable($text)
    {
        if ($text === "" || is_null($text)) {
            return false;
        }
        if (strlen($text) % 2 !== 0) {
            return false;
        }
        if (!preg_match("/^[a-f\\d]+\$/i", $text)) {
            return false;
        }
        return true;
    }

    protected function decrypt($value)
    {
        return decrypt($value);
    }

    protected function encrypt($value)
    {
        return encrypt($value);
    }

    public function toArrayUsingColumnMapNames()
    {
        $data = $this->toArray();
        if (0 < count($this->columnMap)) {
            $keys = array_keys($data);
            foreach ($this->columnMap as $mappedName => $dbFieldName) {
                if (array_key_exists($dbFieldName, $data) && !in_array($dbFieldName, $this->hidden)) {
                    $keys[array_search($dbFieldName, $keys)] = $mappedName;
                }
            }
            $keys = array_map(function ($item) {
                return in_array($item, ["created_at", "updated_at"]) ? \Illuminate\Support\Str::camel($item) : $item;
            }, $keys);
            $data = array_combine($keys, $data);
        }
        return $data;
    }

    public function validate()
    {
        $translator = defined("ADMINAREA") ? \AdminLang::self() : \Lang::self();
        $validator = new \Illuminate\Validation\Validator(new \WHMCS\Language\TranslatorBridge($translator), $this->attributes, $this->rules, $this->customValidationMessages);
        if ($validator->passes()) {
            return true;
        }
        $this->errors = $validator->messages();
        return false;
    }

    public function setCustomValidationMessages($messages)
    {
        $this->customValidationMessages = $messages;
        return $this;
    }

    public function errors()
    {
        return $this->errors;
    }

    public function getCustomFieldValuesAttribute()
    {
        $customFieldValues = $this->getRelationValue("customFieldValues");
        if (!$this instanceof \WHMCS\CustomField) {
            $customFieldType = $this->getCustomFieldType();
            $customFieldRelId = $this->getCustomFieldRelId();
            if (!is_null($customFieldType) && !is_null($customFieldRelId)) {
                $customFieldValues = $customFieldValues->filter(function (\WHMCS\CustomField\CustomFieldValue $customFieldValue) use($customFieldType, $customFieldRelId) {
                    return $customFieldValue->customField->type == $customFieldType && $customFieldValue->customField->relatedId == $customFieldRelId;
                });
            } else {
                throw new \WHMCS\Exception("A model that supports custom fields must implement getCustomFieldRelId() and getCustomFieldType() methods");
            }
        }
        return $customFieldValues;
    }

    protected function getCustomFieldType()
    {
        return NULL;
    }

    protected function getCustomFieldRelId()
    {
        return NULL;
    }

    public function getColumn($attribute)
    {
        if (!isset($this->columnMap[$attribute])) {
            throw new \OutOfBoundsException(sprintf("Column does not exist for attribute '%s'", $attribute));
        }
        return $this->columnMap[$attribute];
    }

    public function qualifyAttribute($attribute)
    {
        return $this->qualifyColumn($this->getColumn($attribute));
    }

    public function qualifyAttributes($attributesMap)
    {
        $qualifiedMap = [];
        foreach ($attributesMap as $attribute => $value) {
            $qualifiedMap[$this->qualifyAttribute($attribute)] = $value;
        }
        return $qualifiedMap;
    }
}
