<?php

namespace WHMCS\Api\NG\Versions\V2;

class ApiEntityDecoratorFactory
{
    protected static $decoratorMap = NULL;
    protected static $classMap = NULL;
    const DECORATOR_CLASSES = ["WHMCS\\Api\\NG\\Versions\\V2\\EntityDecorators\\AddonDecorator", "WHMCS\\Api\\NG\\Versions\\V2\\EntityDecorators\\CartDecorator", "WHMCS\\Api\\NG\\Versions\\V2\\EntityDecorators\\CollectionDecorator", "WHMCS\\Api\\NG\\Versions\\V2\\EntityDecorators\\CurrencyDecorator", "WHMCS\\Api\\NG\\Versions\\V2\\EntityDecorators\\DiscountDecorator", "WHMCS\\Api\\NG\\Versions\\V2\\EntityDecorators\\PriceDecorator", "WHMCS\\Api\\NG\\Versions\\V2\\EntityDecorators\\ProductDecorator", "WHMCS\\Api\\NG\\Versions\\V2\\EntityDecorators\\ProductGroupDecorator", "WHMCS\\Api\\NG\\Versions\\V2\\EntityDecorators\\TaxTotalDecorator"];

    protected static function createClassMap()
    {
        if (is_null(static::$classMap)) {
            static::$classMap = [];
            foreach (static::DECORATOR_CLASSES as $decoratorClass) {
                static::$classMap[$decoratorClass::getEntityClass()] = $decoratorClass;
            }
        }
    }

    protected static function createFor($ApiEntityDecoratorInterface, $entity)
    {
        static::createClassMap();
        if (is_object($entity)) {
            if ($entity instanceof \Illuminate\Support\Collection) {
                $entityClass = "Illuminate\\Support\\Collection";
            } else {
                $entityClass = get_class($entity);
            }
        } else {
            if (is_string($entity)) {
                $entityClass = $entity;
            } else {
                throw new \WHMCS\Exception\Api\NG\ApiNgException("The system could not find the API decorator for the entity type: " . gettype($entity));
            }
        }
        if (isset(static::$decoratorMap[$entityClass])) {
            return static::$decoratorMap[$entityClass];
        }
        $decoratorClass = static::$classMap[$entityClass] ?? NULL;
        if (!$decoratorClass) {
            throw new \WHMCS\Exception\Api\NG\ApiNgException("The system could not find the API decorator for the entity class: " . $entityClass);
        }
        $decorator = new $decoratorClass();
        if (!$decorator instanceof ApiEntityDecoratorInterface) {
            throw new \WHMCS\Exception\Api\NG\ApiNgException("All decorator classes must implement ApiEntityDecoratorInterface.");
        }
        static::$decoratorMap[$entityClass] = $decorator;
        return $decorator;
    }

    public static function decorate($entity)
    {
        return static::createFor($entity)->decorate($entity);
    }
}
