<?php
/**
 * @see       https://github.com/laminas/laminas-validator for the canonical source repository
 * @copyright https://github.com/laminas/laminas-validator/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-validator/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Validator;

use DateTime;
use DateTimeImmutable;
use Traversable;

/**
 * Validates that a given value is a DateTime instance or can be converted into one.
 */
class Date extends AbstractValidator
{
    /**#@+
     * Validity constants
     * @var string
     */
    const INVALID        = 'dateInvalid';
    const INVALID_DATE   = 'dateInvalidDate';
    const FALSEFORMAT    = 'dateFalseFormat';
    /**#@-*/

    /**
     * Default format constant
     * @var string
     */
    const FORMAT_DEFAULT = 'Y-m-d';

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $messageTemplates = [
        self::INVALID      => 'Invalid type given. String, integer, array or DateTime expected',
        self::INVALID_DATE => 'The input does not appear to be a valid date',
        self::FALSEFORMAT  => "The input does not fit the date format '%format%'",
    ];

    /**
     * @var array
     */
    protected $messageVariables = [
        'format' => 'format',
    ];

    /**
     * @var string
     */
    protected $format = self::FORMAT_DEFAULT;

    /**
     * @var bool
     */
    protected $strict = false;

    /**
     * Sets validator options
     *
     * @param  string|array|Traversable $options OPTIONAL
     */
    public function __construct($options = [])
    {
        if ($options instanceof Traversable) {
            $options = iterator_to_array($options);
        } elseif (! is_array($options)) {
            $options = func_get_args();
            $temp['format'] = array_shift($options);
            $options = $temp;
        }

        parent::__construct($options);
    }

    /**
     * Returns the format option
     *
     * @return string|null
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Sets the format option
     *
     * Format cannot be null.  It will always default to 'Y-m-d', even
     * if null is provided.
     *
     * @param  string $format
     * @return $this provides a fluent interface
     * @todo   validate the format
     */
    public function setFormat($format = self::FORMAT_DEFAULT)
    {
        $this->format = empty($format) ? self::FORMAT_DEFAULT : $format;
        return $this;
    }

    public function setStrict(bool $strict) : self
    {
        $this->strict = $strict;
        return $this;
    }

    public function isStrict() : bool
    {
        return $this->strict;
    }

    /**
     * Returns true if $value is a DateTime instance or can be converted into one.
     *
     * @param  string|array|int|DateTime $value
     * @return bool
     */
    public function isValid($value)
    {
        $this->setValue($value);

        $date = $this->convertToDateTime($value);
        if (! $date) {
            $this->error(self::INVALID_DATE);
            return false;
        }

        if ($this->isStrict() && $date->format($this->getFormat()) !== $value) {
            $this->error(self::FALSEFORMAT);
            return false;
        }

        return true;
    }

    /**
     * Attempts to convert an int, string, or array to a DateTime object
     *
     * @param  string|int|array $param
     * @param  bool             $addErrors
     * @return bool|DateTime
     */
    protected function convertToDateTime($param, $addErrors = true)
    {
        if ($param instanceof DateTime || $param instanceof DateTimeImmutable) {
            return $param;
        }

        $type = gettype($param);
        if (! in_array($type, ['string', 'integer', 'double', 'array'])) {
            if ($addErrors) {
                $this->error(self::INVALID);
            }
            return false;
        }

        $convertMethod = 'convert' . ucfirst($type);
        return $this->{$convertMethod}($param, $addErrors);
    }

    /**
     * Attempts to convert an integer into a DateTime object
     *
     * @param  integer $value
     * @return bool|DateTime
     */
    protected function convertInteger($value)
    {
        return date_create("@$value");
    }

    /**
     * Attempts to convert an double into a DateTime object
     *
     * @param  double $value
     * @return bool|DateTime
     */
    protected function convertDouble($value)
    {
        return DateTime::createFromFormat('U', $value);
    }

    /**
     * Attempts to convert a string into a DateTime object
     *
     * @param  string $value
     * @param  bool   $addErrors
     * @return bool|DateTime
     */
    protected function convertString($value, $addErrors = true)
    {
        $date = DateTime::createFromFormat($this->format, $value);

        // Invalid dates can show up as warnings (ie. "2007-02-99")
        // and still return a DateTime object.
        $errors = DateTime::getLastErrors();
        if ($errors['warning_count'] > 0) {
            if ($addErrors) {
                $this->error(self::FALSEFORMAT);
            }
            return false;
        }

        return $date;
    }

    /**
     * Implodes the array into a string and proxies to {@link convertString()}.
     *
     * @param  array $value
     * @param  bool  $addErrors
     * @return bool|DateTime
     * @todo   enhance the implosion
     */
    protected function convertArray(array $value, $addErrors = true)
    {
        return $this->convertString(implode('-', $value), $addErrors);
    }
}
