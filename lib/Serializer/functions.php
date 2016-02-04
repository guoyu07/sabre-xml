<?php

namespace Sabre\Xml\Serializer;

use InvalidArgumentException;
use Sabre\Xml\Writer;
use Sabre\Xml\XmlSerializable;

/**
 * This file provides a number of 'serializer' helper functions.
 *
 * These helper functions can be used to easily xml-encode common PHP
 * data structures, or can be placed in the $classMap.
 */

/**
 * The 'enum' serializer writes simple list of elements.
 *
 * For example, calling:
 *
 * enum($writer, [
 *   "{http://sabredav.org/ns}elem1",
 *   "{http://sabredav.org/ns}elem2",
 *   "{http://sabredav.org/ns}elem3",
 *   "{http://sabredav.org/ns}elem4",
 *   "{http://sabredav.org/ns}elem5",
 * ]);
 *
 * Will generate something like this (if the correct namespace is declared):
 *
 * <s:elem1 />
 * <s:elem2 />
 * <s:elem3 />
 * <s:elem4>content</s:elem4>
 * <s:elem5 attr="val" />
 *
 * @param Writer $writer
 * @param string[] $values
 * @return void
 */
function enum(Writer $writer, array $values) {

    foreach ($values as $value) {
        $writer->writeElement($value);
    }
}

/**
 * The valueObject serializer turns a simple PHP object into a classname.
 *
 * Every public property will be encoded as an xml element with the same
 * name, in the XML namespace as specified.
 *
 * @param Writer $writer
 * @param object $valueObject
 * @param string $namespace
 */
function valueObject(Writer $writer, $valueObject, $namespace) {
    foreach (get_object_vars($valueObject) as $key => $val) {
        $writer->writeElement('{' . $namespace . '}' . $key, $val);
    }
}


/**
 * This serializer helps you serialize xml structures that look like
 * this:
 *
 * <collection>
 *    <item>...</item>
 *    <item>...</item>
 *    <item>...</item>
 * </collection>
 *
 * In that previous example, this serializer just serializes the item element,
 * and this could be called like this:
 *
 * repeatingElements($writer, $items, '{}item');
 *
 * @param Writer $writer
 * @param array $items A list of items sabre/xml can serialize.
 * @param string $childElementName Element name in clark-notation
 * @return void
 */
function repeatingElements(Writer $writer, array $items, $childElementName) {

    foreach ($items as $item) {
        $writer->writeElement($childElementName, $item);
    }

}

/**
 * This function is the 'default' serializer that is able to serialize most
 * things, and delegates to other serializers if needed.
 *
 * The standardSerializer supports a wide-array of values.
 *
 * $value may be a string or integer, it will just write out the string as text.
 * $value may be an instance of XmlSerializable or Element, in which case it
 *    calls it's xmlSerialize() method.
 * $value may be a PHP callback/function/closure, in case we call the callback
 *    and give it the Writer as an argument.
 * $value may be a an object, and if it's in the classMap we automatically call
 *    the correct serializer for it.
 * $value may be null, in which case we do nothing.
 *
 * If $value is an array, the array must look like this:
 *
 * [
 *    [
 *       'name' => '{namespaceUri}element-name',
 *       'value' => '...',
 *       'attributes' => [ 'attName' => 'attValue' ]
 *    ]
 *    [,
 *       'name' => '{namespaceUri}element-name2',
 *       'value' => '...',
 *    ]
 * ]
 *
 * This would result in xml like:
 *
 * <element-name xmlns="namespaceUri" attName="attValue">
 *   ...
 * </element-name>
 * <element-name2>
 *   ...
 * </element-name2>
 *
 * The value property may be any value standardSerializer supports, so you can
 * nest data-structures this way. Both value and attributes are optional.
 *
 * Alternatively, you can also specify the array using this syntax:
 *
 * [
 *    [
 *       '{namespaceUri}element-name' => '...',
 *       '{namespaceUri}element-name2' => '...',
 *    ]
 * ]
 *
 * This is excellent for simple key->value structures, and here you can also
 * specify anything for the value.
 *
 * You can even mix the two array syntaxes.
 *
 * @param Writer $writer
 * @param string|int|float|bool|array|object
 * @return void
 */
function standardSerializer(Writer $writer, $value) {

    if (is_scalar($value)) {

        // String, integer, float, boolean
        $writer->text($value);

    } elseif ($value instanceof XmlSerializable) {

        // XmlSerializable classes or Element classes.
        $value->xmlSerialize($writer);

    } elseif (is_object($value) && isset($writer->classMap[get_class($value)])) {

        // It's an object which class appears in the classmap.
        $writer->classMap[get_class($value)]($writer, $value);

    } elseif (is_callable($value)) {

        // A callback
        $value($writer);

    } elseif (is_null($value)) {

        // nothing!

    } elseif (is_array($value)) {

        foreach ($value as $name => $item) {

            if (is_int($name)) {

                // This item has a numeric index. We expect to be an array with a name and a value.
                if (!is_array($item) || !array_key_exists('name', $item)) {
                    throw new InvalidArgumentException('When passing an array to ->write with numeric indices, every item must be an array containing at least the "name" key');
                }

                $attributes = isset($item['attributes']) ? $item['attributes'] : [];
                $name = $item['name'];
                $item = isset($item['value']) ? $item['value'] : [];

            } elseif (is_array($item) && array_key_exists('value', $item)) {

                // This item has a text index. We expect to be an array with a value and optional attributes.
                $attributes = isset($item['attributes']) ? $item['attributes'] : [];
                $item = $item['value'];

            } else {
                // If it's an array with text-indices, we expect every item's
                // key to be an xml element name in clark notation.
                // No attributes can be passed.
                $attributes = [];
            }

            $writer->startElement($name);
            $writer->writeAttributes($attributes);
            $writer->write($item);
            $writer->endElement();

        }

    } elseif (is_object($value)) {

        throw new InvalidArgumentException('The writer cannot serialize objects of class: ' . get_class($value));

    } else {

        throw new InvalidArgumentException('The writer cannot serialize values of type: ' . gettype($value));

    }

}