<?php
/**
 * @see       https://github.com/laminas/laminas-validator for the canonical source repository
 * @copyright https://github.com/laminas/laminas-validator/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-validator/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Validator\File;

use Laminas\Stdlib\ArrayUtils;
use Traversable;

/**
 * Validator which checks if the file is an image
 */
class IsImage extends MimeType
{
    /**
     * @const string Error constants
     */
    const FALSE_TYPE   = 'fileIsImageFalseType';
    const NOT_DETECTED = 'fileIsImageNotDetected';
    const NOT_READABLE = 'fileIsImageNotReadable';

    /**
     * @var array Error message templates
     */
    protected $messageTemplates = [
        self::FALSE_TYPE   => "File is no image, '%type%' detected",
        self::NOT_DETECTED => 'The mimetype could not be detected from the file',
        self::NOT_READABLE => 'File is not readable or does not exist',
    ];

    /**
     * Sets validator options
     *
     * @param array|Traversable|string $options
     */
    public function __construct($options = [])
    {
        // http://www.iana.org/assignments/media-types/media-types.xhtml#image
        $default = [
            'application/cdf',
            'application/dicom',
            'application/fractals',
            'application/postscript',
            'application/vnd.hp-hpgl',
            'application/vnd.oasis.opendocument.graphics',
            'application/x-cdf',
            'application/x-cmu-raster',
            'application/x-ima',
            'application/x-inventor',
            'application/x-koan',
            'application/x-portable-anymap',
            'application/x-world-x-3dmf',
            'image/bmp',
            'image/c',
            'image/cgm',
            'image/fif',
            'image/gif',
            'image/jpeg',
            'image/jpm',
            'image/jpx',
            'image/jp2',
            'image/naplps',
            'image/pjpeg',
            'image/png',
            'image/svg',
            'image/svg+xml',
            'image/tiff',
            'image/vnd.adobe.photoshop',
            'image/vnd.djvu',
            'image/vnd.fpx',
            'image/vnd.net-fpx',
            'image/webp',
            'image/x-cmu-raster',
            'image/x-cmx',
            'image/x-coreldraw',
            'image/x-cpi',
            'image/x-emf',
            'image/x-ico',
            'image/x-icon',
            'image/x-jg',
            'image/x-ms-bmp',
            'image/x-niff',
            'image/x-pict',
            'image/x-pcx',
            'image/x-png',
            'image/x-portable-anymap',
            'image/x-portable-bitmap',
            'image/x-portable-greymap',
            'image/x-portable-pixmap',
            'image/x-quicktime',
            'image/x-rgb',
            'image/x-tiff',
            'image/x-unknown',
            'image/x-windows-bmp',
            'image/x-xpmi',
        ];

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if ($options === null) {
            $options = [];
        }

        parent::__construct($options);

        if (! $this->getMimeType()) {
            $this->setMimeType($default);
        }
    }
}
