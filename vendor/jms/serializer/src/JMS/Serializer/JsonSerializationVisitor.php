<?php
namespace JMS\Serializer;

use JMS\Serializer\Exception\InvalidArgumentException;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Naming\AdvancedNamingStrategyInterface;

class JsonSerializationVisitor extends GenericSerializationVisitor
{
    private $options = 0;

    private $navigator;
    private $root;
    private $dataStack;
    private $data;

    public function setNavigator(GraphNavigator $navigator)
    {
        $this->navigator = $navigator;
        $this->root = null;
        $this->dataStack = new \SplStack;
    }

    /**
     * @return GraphNavigator
     */
    public function getNavigator()
    {
        return $this->navigator;
    }

    public function visitNull($data, array $type, Context $context)
    {
        return null;
    }

    public function visitString($data, array $type, Context $context)
    {
        if (null === $this->root) {
            $this->root = $data;
        }

        return (string)$data;
    }

    public function visitBoolean($data, array $type, Context $context)
    {
        if (null === $this->root) {
            $this->root = $data;
        }

        return (boolean)$data;
    }

    public function visitInteger($data, array $type, Context $context)
    {
        if (null === $this->root) {
            $this->root = $data;
        }

        return (int)$data;
    }

    public function visitDouble($data, array $type, Context $context)
    {
        if (null === $this->root) {
            $this->root = $data;
        }

        return (float)$data;
    }

    /**
     * @param array $data
     * @param array $type
     * @param Context $context
     * @return mixed
     */
    public function visitArray($data, array $type, Context $context)
    {
        $this->dataStack->push($data);

        $isHash = isset($type['params'][1]);

        if (null === $this->root) {
            $this->root = $isHash ? new \ArrayObject() : array();
            $rs = &$this->root;
        } else {
            $rs = $isHash ? new \ArrayObject() : array();
        }

        $isList = isset($type['params'][0]) && !isset($type['params'][1]);

        foreach ($data as $k => $v) {
            $v = $this->navigator->accept($v, $this->getElementType($type), $context);

            if (null === $v && $context->shouldSerializeNull() !== true) {
                continue;
            }

            if ($isList) {
                $rs[] = $v;
            } else {
                $rs[$k] = $v;
            }
        }

        $this->dataStack->pop();
        return $rs;
    }

    public function startVisitingObject(ClassMetadata $metadata, $data, array $type, Context $context)
    {
        if (null === $this->root) {
            $this->root = new \stdClass;
        }

        $this->dataStack->push($this->data);
        $this->data = array();
    }

    public function endVisitingObject(ClassMetadata $metadata, $data, array $type, Context $context)
    {
        $rs = $this->data;
        $this->data = $this->dataStack->pop();

        // Force JSON output to "{}" instead of "[]" if it contains either no properties or all properties are null.
        if (empty($rs)) {
            $rs = new \ArrayObject();
        }

        if ($this->root instanceof \stdClass && 0 === $this->dataStack->count()) {
            $this->root = $rs;
        }

        return $rs;
    }

    public function visitProperty(PropertyMetadata $metadata, $data, Context $context)
    {
        $v = $this->accessor->getValue($data, $metadata);

        $v = $this->navigator->accept($v, $metadata->type, $context);
        if ((null === $v && $context->shouldSerializeNull() !== true)
            || (true === $metadata->skipWhenEmpty && ($v instanceof \ArrayObject || \is_array($v)) && 0 === count($v))
        ) {
            return;
        }

        if ($this->namingStrategy instanceof AdvancedNamingStrategyInterface) {
            $k = $this->namingStrategy->getPropertyName($metadata, $context);
        } else {
            $k = $this->namingStrategy->translateName($metadata);
        }

        if ($metadata->inline) {
            if (\is_array($v) || ($v instanceof \ArrayObject)) {
                $this->data = array_merge($this->data, (array) $v);
            }
        } else {
            $this->data[$k] = $v;
        }
    }

    /**
     * Allows you to add additional data to the current object/root element.
     * @deprecated use setData instead
     * @param string $key
     * @param integer|float|boolean|string|array|null $value This value must either be a regular scalar, or an array.
     *                                                       It must not contain any objects anymore.
     */
    public function addData($key, $value)
    {
        if (isset($this->data[$key])) {
            throw new InvalidArgumentException(sprintf('There is already data for "%s".', $key));
        }

        $this->data[$key] = $value;
    }

    /**
     * Checks if some data key exists.
     *
     * @param string $key
     * @return boolean
     */
    public function hasData($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Allows you to replace existing data on the current object/root element.
     *
     * @param string $key
     * @param integer|float|boolean|string|array|null $value This value must either be a regular scalar, or an array.
     *                                                       It must not contain any objects anymore.
     */
    public function setData($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function getRoot()
    {
        return $this->root;
    }

    /**
     * @param array|\ArrayObject $data the passed data must be understood by whatever encoding function is applied later.
     */
    public function setRoot($data)
    {
        $this->root = $data;
    }


    public function getResult()
    {
        $result = @json_encode($this->getRoot(), $this->options);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return $result;

            case JSON_ERROR_UTF8:
                throw new \RuntimeException('Your data could not be encoded because it contains invalid UTF8 characters.');

            default:
                throw new \RuntimeException(sprintf('An error occurred while encoding your data (error code %d).', json_last_error()));
        }
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions($options)
    {
        $this->options = (integer)$options;
    }
}
