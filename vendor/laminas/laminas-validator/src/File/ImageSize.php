<?php
/**
 * @see       https://github.com/laminas/laminas-validator for the canonical source repository
 * @copyright https://github.com/laminas/laminas-validator/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-validator/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Validator\File;

use Laminas\Stdlib\ErrorHandler;
use Laminas\Validator\AbstractValidator;
use Laminas\Validator\Exception;

/**
 * Validator for the image size of an image file
 */
class ImageSize extends AbstractValidator
{
    use FileInformationTrait;

    /**
     * @const string Error constants
     */
    const WIDTH_TOO_BIG    = 'fileImageSizeWidthTooBig';
    const WIDTH_TOO_SMALL  = 'fileImageSizeWidthTooSmall';
    const HEIGHT_TOO_BIG   = 'fileImageSizeHeightTooBig';
    const HEIGHT_TOO_SMALL = 'fileImageSizeHeightTooSmall';
    const NOT_DETECTED     = 'fileImageSizeNotDetected';
    const NOT_READABLE     = 'fileImageSizeNotReadable';

    /**
     * @var array Error message template
     */
    protected $messageTemplates = [
        self::WIDTH_TOO_BIG    => "Maximum allowed width for image should be '%maxwidth%' but '%width%' detected",
        self::WIDTH_TOO_SMALL  => "Minimum expected width for image should be '%minwidth%' but '%width%' detected",
        self::HEIGHT_TOO_BIG   => "Maximum allowed height for image should be '%maxheight%' but '%height%' detected",
        self::HEIGHT_TOO_SMALL => "Minimum expected height for image should be '%minheight%' but '%height%' detected",
        self::NOT_DETECTED     => 'The size of image could not be detected',
        self::NOT_READABLE     => 'File is not readable or does not exist',
    ];

    /**
     * @var array Error message template variables
     */
    protected $messageVariables = [
        'minwidth'  => ['options' => 'minWidth'],
        'maxwidth'  => ['options' => 'maxWidth'],
        'minheight' => ['options' => 'minHeight'],
        'maxheight' => ['options' => 'maxHeight'],
        'width'     => 'width',
        'height'    => 'height',
    ];

    /**
     * Detected width
     *
     * @var int
     */
    protected $width;

    /**
     * Detected height
     *
     * @var int
     */
    protected $height;

    /**
     * Options for this validator
     *
     * @var array
     */
    protected $options = [
        'minWidth'  => null,  // Minimum image width
        'maxWidth'  => null,  // Maximum image width
        'minHeight' => null,  // Minimum image height
        'maxHeight' => null,  // Maximum image height
    ];

    /**
     * Sets validator options
     *
     * Accepts the following option keys:
     * - minheight
     * - minwidth
     * - maxheight
     * - maxwidth
     *
     * @param  array|\Traversable $options
     */
    public function __construct($options = null)
    {
        if (1 < func_num_args()) {
            if (! is_array($options)) {
                $options = ['minWidth' => $options];
            }

            $argv = func_get_args();
            array_shift($argv);
            $options['minHeight'] = array_shift($argv);
            if (! empty($argv)) {
                $options['maxWidth'] = array_shift($argv);
                if (! empty($argv)) {
                    $options['maxHeight'] = array_shift($argv);
                }
            }
        }

        parent::__construct($options);
    }

    /**
     * Returns the minimum allowed width
     *
     * @return int
     */
    public function getMinWidth()
    {
        return $this->options['minWidth'];
    }

    /**
     * Sets the minimum allowed width
     *
     * @param  int $minWidth
     * @throws Exception\InvalidArgumentException When minwidth is greater than maxwidth
     * @return $this Provides a fluid interface
     */
    public function setMinWidth($minWidth)
    {
        if (($this->getMaxWidth() !== null) && ($minWidth > $this->getMaxWidth())) {
            throw new Exception\InvalidArgumentException(
                'The minimum image width must be less than or equal to the '
                . " maximum image width, but {$minWidth} > {$this->getMaxWidth()}"
            );
        }

        $this->options['minWidth']  = (int) $minWidth;
        return $this;
    }

    /**
     * Returns the maximum allowed width
     *
     * @return int
     */
    public function getMaxWidth()
    {
        return $this->options['maxWidth'];
    }

    /**
     * Sets the maximum allowed width
     *
     * @param  int $maxWidth
     * @throws Exception\InvalidArgumentException When maxwidth is less than minwidth
     * @return $this Provides a fluid interface
     */
    public function setMaxWidth($maxWidth)
    {
        if (($this->getMinWidth() !== null) && ($maxWidth < $this->getMinWidth())) {
            throw new Exception\InvalidArgumentException(
                'The maximum image width must be greater than or equal to the '
                . "minimum image width, but {$maxWidth} < {$this->getMinWidth()}"
            );
        }

        $this->options['maxWidth']  = (int) $maxWidth;
        return $this;
    }

    /**
     * Returns the minimum allowed height
     *
     * @return int
     */
    public function getMinHeight()
    {
        return $this->options['minHeight'];
    }

    /**
     * Sets the minimum allowed height
     *
     * @param  int $minHeight
     * @throws Exception\InvalidArgumentException When minheight is greater than maxheight
     * @return $this Provides a fluid interface
     */
    public function setMinHeight($minHeight)
    {
        if (($this->getMaxHeight() !== null) && ($minHeight > $this->getMaxHeight())) {
            throw new Exception\InvalidArgumentException(
                'The minimum image height must be less than or equal to the '
                . " maximum image height, but {$minHeight} > {$this->getMaxHeight()}"
            );
        }

        $this->options['minHeight']  = (int) $minHeight;
        return $this;
    }

    /**
     * Returns the maximum allowed height
     *
     * @return int
     */
    public function getMaxHeight()
    {
        return $this->options['maxHeight'];
    }

    /**
     * Sets the maximum allowed height
     *
     * @param  int $maxHeight
     * @throws Exception\InvalidArgumentException When maxheight is less than minheight
     * @return $this Provides a fluid interface
     */
    public function setMaxHeight($maxHeight)
    {
        if (($this->getMinHeight() !== null) && ($maxHeight < $this->getMinHeight())) {
            throw new Exception\InvalidArgumentException(
                'The maximum image height must be greater than or equal to the '
                . "minimum image height, but {$maxHeight} < {$this->getMinHeight()}"
            );
        }

        $this->options['maxHeight']  = (int) $maxHeight;
        return $this;
    }

    /**
     * Returns the set minimum image sizes
     *
     * @return array
     */
    public function getImageMin()
    {
        return ['minWidth' => $this->getMinWidth(), 'minHeight' => $this->getMinHeight()];
    }

    /**
     * Returns the set maximum image sizes
     *
     * @return array
     */
    public function getImageMax()
    {
        return ['maxWidth' => $this->getMaxWidth(), 'maxHeight' => $this->getMaxHeight()];
    }

    /**
     * Returns the set image width sizes
     *
     * @return array
     */
    public function getImageWidth()
    {
        return ['minWidth' => $this->getMinWidth(), 'maxWidth' => $this->getMaxWidth()];
    }

    /**
     * Returns the set image height sizes
     *
     * @return array
     */
    public function getImageHeight()
    {
        return ['minHeight' => $this->getMinHeight(), 'maxHeight' => $this->getMaxHeight()];
    }

    /**
     * Sets the minimum image size
     *
     * @param  array $options                 The minimum image dimensions
     * @return $this Provides a fluent interface
     */
    public function setImageMin($options)
    {
        $this->setOptions($options);
        return $this;
    }

    /**
     * Sets the maximum image size
     *
     * @param  array|\Traversable $options The maximum image dimensions
     * @return $this Provides a fluent interface
     */
    public function setImageMax($options)
    {
        $this->setOptions($options);
        return $this;
    }

    /**
     * Sets the minimum and maximum image width
     *
     * @param  array $options               The image width dimensions
     * @return $this Provides a fluent interface
     */
    public function setImageWidth($options)
    {
        $this->setImageMin($options);
        $this->setImageMax($options);

        return $this;
    }

    /**
     * Sets the minimum and maximum image height
     *
     * @param  array $options               The image height dimensions
     * @return $this Provides a fluent interface
     */
    public function setImageHeight($options)
    {
        $this->setImageMin($options);
        $this->setImageMax($options);

        return $this;
    }

    /**
     * Returns true if and only if the image size of $value is at least min and
     * not bigger than max
     *
     * @param  string|array $value Real file to check for image size
     * @param  array        $file  File data from \Laminas\File\Transfer\Transfer (optional)
     * @return bool
     */
    public function isValid($value, $file = null)
    {
        $fileInfo = $this->getFileInfo($value, $file);

        $this->setValue($fileInfo['filename']);

        // Is file readable ?
        if (empty($fileInfo['file']) || false === is_readable($fileInfo['file'])) {
            $this->error(self::NOT_READABLE);
            return false;
        }

        ErrorHandler::start();
        $size = getimagesize($fileInfo['file']);
        ErrorHandler::stop();

        if (empty($size) || ($size[0] === 0) || ($size[1] === 0)) {
            $this->error(self::NOT_DETECTED);
            return false;
        }

        $this->width  = $size[0];
        $this->height = $size[1];
        if ($this->width < $this->getMinWidth()) {
            $this->error(self::WIDTH_TOO_SMALL);
        }

        if (($this->getMaxWidth() !== null) && ($this->getMaxWidth() < $this->width)) {
            $this->error(self::WIDTH_TOO_BIG);
        }

        if ($this->height < $this->getMinHeight()) {
            $this->error(self::HEIGHT_TOO_SMALL);
        }

        if (($this->getMaxHeight() !== null) && ($this->getMaxHeight() < $this->height)) {
            $this->error(self::HEIGHT_TOO_BIG);
        }

        if ($this->getMessages()) {
            return false;
        }

        return true;
    }
}
