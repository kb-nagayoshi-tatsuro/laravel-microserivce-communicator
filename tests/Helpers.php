<?php


function get_private_property($object, $propertyName)
{
    $reflection = new ReflectionClass(get_class($object));
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    return $property->getValue($object);
}