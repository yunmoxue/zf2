<?php


namespace BirdSystem\Model;


abstract class GlobalConfig
{
    public function __toArray()
    {
        $properties = (new \ReflectionClass(__CLASS__))->getProperties(\ReflectionProperty::IS_PUBLIC);
        $result     = [];
        foreach ($properties as $Property) {
            $result[$Property->getName()] = $Property->getValue();
        }

        return $result;
    }
}