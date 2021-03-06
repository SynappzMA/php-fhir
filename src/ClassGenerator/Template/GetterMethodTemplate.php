<?php namespace DCarbone\PHPFHIR\ClassGenerator\Template;

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

use DCarbone\PHPFHIR\ClassGenerator\Enum\PHPScopeEnum;
use DCarbone\PHPFHIR\ClassGenerator\Utilities\NameUtils;

/**
 * Class GetterMethodTemplate
 * @package DCarbone\PHPFHIR\ClassGenerator\Template
 */
class GetterMethodTemplate extends AbstractMethodTemplate
{
    /** @var PropertyTemplate */
    private $_property;

    /**
     * Constructor
     *
     * @param PropertyTemplate $propertyTemplate
     */
    public function __construct(PropertyTemplate $propertyTemplate)
    {
        $name = sprintf('get%s', NameUtils::getPropertyMethodName($propertyTemplate->getName()));

        parent::__construct($name, new PHPScopeEnum(PHPScopeEnum::_PUBLIC));

        $this->setDocumentation($propertyTemplate->getDocumentation());

        $this->_property = $propertyTemplate;
    }

    /**
     * @return PropertyTemplate
     */
    public function getProperty()
    {
        return $this->_property;
    }

    /**
     * @return string
     */
    public function compileTemplate()
    {
        $output = sprintf("    /**\n%s", $this->getDocBlockDocumentationFragment());

        $property = $this->getProperty();

        $output = sprintf(
            "%s     * @return %s%s%s\n     */\n    %s function %s()\n    {\n",
            $output,
            ($property->isPrimitive() || $property->isList() ? '' : '\\'),
            $property->getPhpType(),
            ($property->isCollection() ? '[]' : ''),
            (string)$this->getScope(),
            $this->getName()
        );

        foreach($this->getBody() as $line)
        {
            $output = sprintf("%s        %s\n", $output, $line);
        }

        return sprintf("%s    }\n\n", $output);
    }
}