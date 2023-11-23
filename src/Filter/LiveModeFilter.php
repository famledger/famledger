<?php

namespace App\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Exception;

use App\Annotation\LiveModeFilterable;

class LiveModeFilter extends SQLFilter
{
    /**
     * @throws Exception
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        $liveMode = null;

        // Attempt to retrieve the 'liveMode' parameter
        try {
            $liveMode = $this->getParameter('livemode');
        } catch (Exception) {
        }

        // Return early if no liveMode has been specified or liveMode is 'ALL'
        if (null === $liveMode or "'ALL'" === $liveMode) {
            return '';
        }

        // Check if the current entity is "liveMode filterable"
        $liveModeFilterableAttribute = $targetEntity->getReflectionClass()->getAttributes(LiveModeFilterable::class);

        if (empty($liveModeFilterableAttribute)) {
            return '';
        }

        /** @var LiveModeFilterable $liveModeFilterable */
        $liveModeFilterable = $liveModeFilterableAttribute[0]->newInstance();
        $fieldName          = $liveModeFilterable->livemodeFieldName;

        // Ensure a field name is provided for liveMode filterable entities
        if (empty($fieldName)) {
            throw new Exception('LiveModeFilter requires liveModeFieldName to be defined.');
        }

        return sprintf("%s.%s = %s", $targetTableAlias, $fieldName, $liveMode);
    }
}