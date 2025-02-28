<?php
/**
 * @see       https://github.com/laminas/laminas-validator for the canonical source repository
 * @copyright https://github.com/laminas/laminas-validator/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-validator/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Validator;

use Laminas\Stdlib\ArrayUtils;
use Traversable;

class LessThan extends AbstractValidator
{
    const NOT_LESS           = 'notLessThan';
    const NOT_LESS_INCLUSIVE = 'notLessThanInclusive';

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $messageTemplates = [
        self::NOT_LESS           => "The input is not less than '%max%'",
        self::NOT_LESS_INCLUSIVE => "The input is not less or equal than '%max%'",
    ];

    /**
     * Additional variables available for validation failure messages
     *
     * @var array
     */
    protected $messageVariables = [
        'max' => 'max',
    ];

    /**
     * Maximum value
     *
     * @var mixed
     */
    protected $max;

    /**
     * Whether to do inclusive comparisons, allowing equivalence to max
     *
     * If false, then strict comparisons are done, and the value may equal
     * the max option
     *
     * @var bool
     */
    protected $inclusive;

    /**
     * Sets validator options
     *
     * @param  array|Traversable $options
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($options = null)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }
        if (! is_array($options)) {
            $options = func_get_args();
            $temp['max'] = array_shift($options);

            if (! empty($options)) {
                $temp['inclusive'] = array_shift($options);
            }

            $options = $temp;
        }

        if (! array_key_exists('max', $options)) {
            throw new Exception\InvalidArgumentException("Missing option 'max'");
        }

        if (! array_key_exists('inclusive', $options)) {
            $options['inclusive'] = false;
        }

        $this->setMax($options['max'])
             ->setInclusive($options['inclusive']);

        parent::__construct($options);
    }

    /**
     * Returns the max option
     *
     * @return mixed
     */
    public function getMax()
    {
        return $this->max;
    }

    /**
     * Sets the max option
     *
     * @param  mixed $max
     * @return $this Provides a fluent interface
     */
    public function setMax($max)
    {
        $this->max = $max;
        return $this;
    }

    /**
     * Returns the inclusive option
     *
     * @return bool
     */
    public function getInclusive()
    {
        return $this->inclusive;
    }

    /**
     * Sets the inclusive option
     *
     * @param  bool $inclusive
     * @return $this Provides a fluent interface
     */
    public function setInclusive($inclusive)
    {
        $this->inclusive = $inclusive;
        return $this;
    }

    /**
     * Returns true if and only if $value is less than max option, inclusively
     * when the inclusive option is true
     *
     * @param  mixed $value
     * @return bool
     */
    public function isValid($value)
    {
        $this->setValue($value);

        if ($this->inclusive) {
            if ($value > $this->max) {
                $this->error(self::NOT_LESS_INCLUSIVE);
                return false;
            }
        } else {
            if ($value >= $this->max) {
                $this->error(self::NOT_LESS);
                return false;
            }
        }

        return true;
    }
}
