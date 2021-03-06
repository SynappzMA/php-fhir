<?php namespace DCarbone\PHPFHIR\ClassGenerator\Generator;

/*
 * Copyright 2016 Daniel Carbone (daniel.p.carbone@gmail.com)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use DCarbone\PHPFHIR\ClassGenerator\Enum\ElementTypeEnum;
use DCarbone\PHPFHIR\ClassGenerator\Enum\PrimitivePropertyTypesEnum;
use DCarbone\PHPFHIR\ClassGenerator\Template\ClassTemplate;
use DCarbone\PHPFHIR\ClassGenerator\Template\PropertyTemplate;
use DCarbone\PHPFHIR\ClassGenerator\Utilities\PrimitiveTypeUtils;
use DCarbone\PHPFHIR\ClassGenerator\Utilities\XMLUtils;
use DCarbone\PHPFHIR\ClassGenerator\XSDMap;

/**
 * Class PropertyGenerator
 * @package DCarbone\PHPFHIR\ClassGenerator\Utilities
 */
abstract class PropertyGenerator
{
    /**
     * TODO: I don't like how this is utilized, really.  Should think of a better way to do it.
     *
     * @param XSDMap $XSDMap
     * @param ClassTemplate $classTemplate
     * @param \SimpleXMLElement $propertyElement
     */
    public static function implementProperty(XSDMap $XSDMap, ClassTemplate $classTemplate, \SimpleXMLElement $propertyElement)
    {
        switch(strtolower($propertyElement->getName()))
        {
            case ElementTypeEnum::ATTRIBUTE:
                self::implementAttributeProperty($XSDMap, $classTemplate, $propertyElement);
                break;
            case ElementTypeEnum::CHOICE:
                self::implementChoiceProperty($XSDMap, $classTemplate, $propertyElement);
                break;
            case ElementTypeEnum::SEQUENCE:
                self::implementSequenceProperty($XSDMap, $classTemplate, $propertyElement);
                break;
            case ElementTypeEnum::UNION:
                self::implementUnionProperty($XSDMap, $classTemplate, $propertyElement);
                break;
            case ElementTypeEnum::ENUMERATION:
                self::implementEnumerationProperty($XSDMap, $classTemplate, $propertyElement);
                break;
        }
    }

    /**
     * @param XSDMap $XSDMap
     * @param ClassTemplate $classTemplate
     * @param \SimpleXMLElement $element
     * @param string $documentation
     * @param string $maxOccurs
     * @return PropertyTemplate|null
     */
    public static function buildProperty(XSDMap $XSDMap,
                                         ClassTemplate $classTemplate,
                                         \SimpleXMLElement $element,
                                         $documentation = null,
                                         $maxOccurs = null)
    {
        $propertyTemplate = new PropertyTemplate();

        $attributes = $element->attributes();

        if (null === $documentation)
            $propertyTemplate->setDocumentation(XMLUtils::getDocumentation($element));

        if (null === $maxOccurs && isset($attributes['maxOccurs']))
            $maxOccurs = (string)$attributes['maxOccurs'];

        if (null !== $maxOccurs && '' !== $maxOccurs)
            $propertyTemplate->setCollection(self::determineIfCollection($maxOccurs));

        $name = (string)$attributes['name'];
        $type = (string)$attributes['type'];
        $ref = (string)$attributes['ref'];

        if ('' === $name)
        {
            if ('' === $ref)
            {
                trigger_error(sprintf(
                    'Encountered property on FHIR object "%s" with no "name" or "ref" attribute, cannot create property for it.  Property definition: "%s"',
                    $classTemplate->getElementName(),
                    $element->saveXML()
                ));

                return null;
            }

            if (0 === strpos($ref, 'xhtml'))
            {
                $propertyTemplate->setName(substr($ref, 6));
                $propertyTemplate->setFHIRElementType('html');
                $propertyTemplate->setHtml(true);
                $propertyTemplate->setPhpType('string');

                return $propertyTemplate;
            }

            trigger_error(sprintf(
                'Unable to determine property name on object "%s" with ref value "%s".  Property definition: "%s"',
                $classTemplate->getElementName(),
                $attributes['ref'],
                $element->saveXML()
            ));

            return null;
        }

        $propertyTemplate->setName($name);
        $propertyTemplate->setFHIRElementType($type);

        // TODO: Implement proper primitive types
        if (false !== strpos($type, '-primitive'))
        {
            $propertyTemplate->setPrimitive(true);
            $propertyTemplate->setPhpType('string');
        }
        else if (false !== strpos($type, '-list'))
        {
            $propertyTemplate->setList(true);
            $propertyTemplate->setPhpType('string');
        }
        else
        {
            $propertyTemplate->setPhpType(
                $XSDMap->getClassUseStatementForFHIRElementName($type)
            );
        }

        return $propertyTemplate;
    }

    /**
     * @param string|number $maxOccurs
     * @return bool
     */
    public static function determineIfCollection($maxOccurs)
    {
        return 'unbounded' === strtolower($maxOccurs) || (is_numeric($maxOccurs) && (int)$maxOccurs > 1);
    }

    /**
     * @param XSDMap $XSDMap
     * @param ClassTemplate $classTemplate
     * @param \SimpleXMLElement $sequence
     */
    public static function implementSequenceProperty(XSDMap $XSDMap, ClassTemplate $classTemplate, \SimpleXMLElement $sequence)
    {
        // Check if this is a simple or complex sequence
        $elements = $sequence->xpath('xs:element');
        if (0 === count($elements))
        {
            foreach($sequence->children('xs', true) as $_element)
            {
                /** @var \SimpleXMLElement $_element */
                switch(strtolower($_element->getName()))
                {
                    case ElementTypeEnum::CHOICE:
                        self::implementChoiceProperty($XSDMap, $classTemplate, $_element);
                        break;
                }
            }
        }
        else
        {
            foreach($elements as $element)
            {
                $propertyTemplate = self::buildProperty($XSDMap, $classTemplate, $element);
                if ($propertyTemplate)
                    $classTemplate->addProperty($propertyTemplate);
            }
        }
    }

    /**
     * @param XSDMap $XSDMap
     * @param ClassTemplate $classTemplate
     * @param \SimpleXMLElement $choice
     */
    public static function implementChoiceProperty(XSDMap $XSDMap, ClassTemplate $classTemplate, \SimpleXMLElement $choice)
    {
        $attributes = $choice->attributes();
//        $minOccurs = (int)$attributes['minOccurs'];
        $maxOccurs = $attributes['maxOccurs'];
        $documentation = XMLUtils::getDocumentation($choice);

        foreach($choice->xpath('xs:element') as $_element)
        {
            $propertyTemplate = self::buildProperty($XSDMap, $classTemplate, $_element, $documentation, $maxOccurs);
            if ($propertyTemplate)
                $classTemplate->addProperty($propertyTemplate);
        }
    }

    /**
     * @param XSDMap $XSDMap
     * @param ClassTemplate $classTemplate
     * @param \SimpleXMLElement $attribute
     */
    public static function implementAttributeProperty(XSDMap $XSDMap, ClassTemplate $classTemplate, \SimpleXMLElement $attribute)
    {
        $propertyTemplate = self::buildProperty($XSDMap, $classTemplate, $attribute, null, 1);
        if ($propertyTemplate)
            $classTemplate->addProperty($propertyTemplate);
    }

    /**
     * @param XSDMap $XSDMap
     * @param ClassTemplate $classTemplate
     * @param \SimpleXMLElement $union
     */
    public static function implementUnionProperty(XSDMap $XSDMap, ClassTemplate $classTemplate, \SimpleXMLElement $union)
    {
        // TODO: Implement these!
    }

    /**
     * @param XSDMap $XSDMap
     * @param ClassTemplate $classTemplate
     * @param \SimpleXMLElement $enumeration
     */
    public static function implementEnumerationProperty(XSDMap $XSDMap, ClassTemplate $classTemplate, \SimpleXMLElement $enumeration)
    {
        // TODO: Implement these!
    }
}