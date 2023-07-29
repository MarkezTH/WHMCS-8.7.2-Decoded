<?php

namespace WHMCS\Model;

abstract class AbstractKeyValuePair extends AbstractModel
{
    protected $booleanValues = [];
    protected $nonEmptyValues = [];
    protected $semanticVersionValues = [];
    protected $commaSeparatedValues = [];
    protected $characterSeparatedValues = [];

    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->incrementing = false;
    }

    public function setAttribute($key, $value)
    {
        if ($key != $this->primaryKey && $key != static::UPDATED_AT && $key != static::CREATED_AT) {
            if (in_array($this->{$this->primaryKey}, $this->nonEmptyValues) && ($value == "" || is_null($value))) {
                $class = get_called_class();
                throw new \WHMCS\Exception\Model\EmptyValue("The \"" . $class . "\" key \"" . $this->{$this->primaryKey} . "\" value cannot not be empty.");
            }
            if (in_array($this->{$this->primaryKey}, $this->booleanValues)) {
                $value = $this->fromBoolean($value);
            } else {
                if (in_array($this->{$this->primaryKey}, $this->semanticVersionValues)) {
                    $value = $this->fromSemanticVersion($value);
                } else {
                    if (in_array($this->{$this->primaryKey}, $this->commaSeparatedValues)) {
                        $value = $this->fromArrayToCharacterSeparatedValue($value);
                    } else {
                        foreach ($this->characterSeparatedValues as $character => $columns) {
                            if (in_array($this->{$this->primaryKey}, $columns)) {
                                $value = $this->fromArrayToCharacterSeparatedValue($value, $character);
                            }
                        }
                    }
                }
            }
        }
        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if ($key != $this->primaryKey && $key != static::UPDATED_AT && $key != static::CREATED_AT) {
            if (in_array($this->{$this->primaryKey}, $this->booleanValues)) {
                $value = $this->asBoolean($value);
            } else {
                if (in_array($this->{$this->primaryKey}, $this->semanticVersionValues)) {
                    $value = $this->asSemanticVersion($value);
                } else {
                    if (in_array($this->{$this->primaryKey}, $this->commaSeparatedValues)) {
                        $value = $this->asArrayFromCharacterSeparatedValue((string) $value, ",");
                    } else {
                        foreach ($this->characterSeparatedValues as $character => $columns) {
                            if (in_array($this->{$this->primaryKey}, $columns)) {
                                $value = $this->asArrayFromCharacterSeparatedValue((string) $value, $character);
                            }
                        }
                    }
                }
            }
        }
        return $value;
    }

    public function isCommaSeparatedValue()
    {
        return in_array($this->{$this->primaryKey}, $this->commaSeparatedValues);
    }
}
