<?php
/**
 * @see       https://github.com/laminas/laminas-diactoros for the canonical source repository
 * @copyright https://github.com/laminas/laminas-diactoros/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-diactoros/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Laminas\Diactoros;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

use function fopen;
use function fwrite;
use function get_resource_type;
use function is_resource;
use function rewind;

class StreamFactory implements StreamFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createStream(string $content = '') : StreamInterface
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, $content);
        rewind($resource);

        return $this->createStreamFromResource($resource);
    }

    /**
     * {@inheritDoc}
     */
    public function createStreamFromFile(string $file, string $mode = 'r') : StreamInterface
    {
        return new Stream($file, $mode);
    }

    /**
     * {@inheritDoc}
     */
    public function createStreamFromResource($resource) : StreamInterface
    {
        if (! is_resource($resource) || 'stream' !== get_resource_type($resource)) {
            throw new Exception\InvalidArgumentException(
                'Invalid stream provided; must be a stream resource'
            );
        }
        return new Stream($resource);
    }
}
