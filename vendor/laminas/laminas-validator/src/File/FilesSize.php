<?php
/**
 * @see       https://github.com/laminas/laminas-validator for the canonical source repository
 * @copyright https://github.com/laminas/laminas-validator/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-validator/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Validator\File;

use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\ErrorHandler;
use Laminas\Validator\Exception;
use Traversable;

/**
 * Validator for the size of all files which will be validated in sum
 *
 */
class FilesSize extends Size
{
    /**
     * @const string Error constants
     */
    const TOO_BIG      = 'fileFilesSizeTooBig';
    const TOO_SMALL    = 'fileFilesSizeTooSmall';
    const NOT_READABLE = 'fileFilesSizeNotReadable';

    /**
     * @var array Error message templates
     */
    protected $messageTemplates = [
        self::TOO_BIG      => "All files in sum should have a maximum size of '%max%' but '%size%' were detected",
        self::TOO_SMALL    => "All files in sum should have a minimum size of '%min%' but '%size%' were detected",
        self::NOT_READABLE => 'One or more files can not be read',
    ];

    /**
     * Internal file array
     *
     * @var array
     */
    protected $files;

    /**
     * Sets validator options
     *
     * Min limits the used disk space for all files, when used with max=null it is the maximum file size
     * It also accepts an array with the keys 'min' and 'max'
     *
     * @param  int|array|Traversable $options Options for this validator
     * @throws \Laminas\Validator\Exception\InvalidArgumentException
     */
    public function __construct($options = null)
    {
        $this->files = [];
        $this->setSize(0);

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (is_scalar($options)) {
            $options = ['max' => $options];
        } elseif (! is_array($options)) {
            throw new Exception\InvalidArgumentException('Invalid options to validator provided');
        }

        if (1 < func_num_args()) {
            $argv = func_get_args();
            array_shift($argv);
            $options['max'] = array_shift($argv);
            if (! empty($argv)) {
                $options['useByteString'] = array_shift($argv);
            }
        }

        parent::__construct($options);
    }

    /**
     * Returns true if and only if the disk usage of all files is at least min and
     * not bigger than max (when max is not null).
     *
     * @param  string|array $value Real file to check for size
     * @param  array        $file  File data from \Laminas\File\Transfer\Transfer
     * @return bool
     */
    public function isValid($value, $file = null)
    {
        if (is_string($value)) {
            $value = [$value];
        } elseif (is_array($value) && isset($value['tmp_name'])) {
            $value = [$value];
        }

        $min  = $this->getMin(true);
        $max  = $this->getMax(true);
        $size = $this->getSize();
        foreach ($value as $files) {
            if (is_array($files)) {
                if (! isset($files['tmp_name']) || ! isset($files['name'])) {
                    throw new Exception\InvalidArgumentException(
                        'Value array must be in $_FILES format'
                    );
                }
                $file  = $files;
                $files = $files['tmp_name'];
            }

            // Is file readable ?
            if (empty($files) || false === is_readable($files)) {
                $this->throwError($file, self::NOT_READABLE);
                continue;
            }

            if (! isset($this->files[$files])) {
                $this->files[$files] = $files;
            } else {
                // file already counted... do not count twice
                continue;
            }

            // limited to 2GB files
            ErrorHandler::start();
            $size += filesize($files);
            ErrorHandler::stop();
            $this->size = $size;
            if (($max !== null) && ($max < $size)) {
                if ($this->getByteString()) {
                    $this->options['max'] = $this->toByteString($max);
                    $this->size           = $this->toByteString($size);
                    $this->throwError($file, self::TOO_BIG);
                    $this->options['max'] = $max;
                    $this->size           = $size;
                } else {
                    $this->throwError($file, self::TOO_BIG);
                }
            }
        }

        // Check that aggregate files are >= minimum size
        if (($min !== null) && ($size < $min)) {
            if ($this->getByteString()) {
                $this->options['min'] = $this->toByteString($min);
                $this->size           = $this->toByteString($size);
                $this->throwError($file, self::TOO_SMALL);
                $this->options['min'] = $min;
                $this->size           = $size;
            } else {
                $this->throwError($file, self::TOO_SMALL);
            }
        }

        if ($this->getMessages()) {
            return false;
        }

        return true;
    }

    /**
     * Throws an error of the given type
     *
     * @param  string $file
     * @param  string $errorType
     * @return false
     */
    protected function throwError($file, $errorType)
    {
        if ($file !== null) {
            if (is_array($file)) {
                if (array_key_exists('name', $file)) {
                    $this->value = $file['name'];
                }
            } elseif (is_string($file)) {
                $this->value = $file;
            }
        }

        $this->error($errorType);
        return false;
    }
}
