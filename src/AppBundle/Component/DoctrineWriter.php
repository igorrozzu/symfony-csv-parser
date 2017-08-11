<?php
/**
 * Created by PhpStorm.
 * User: i.razumovsky
 * Date: 8/10/17
 * Time: 2:09 PM
 */

namespace AppBundle\Component;


class DoctrineWriter extends \Ddeboer\DataImport\Writer\DoctrineWriter
{
    /**
     * Add the associated objects in case the item have for persist its relation
     *
     * @param array  $item
     * @param object $entity
     */
    protected function loadAssociationObjectsToEntity(array $item, $entity)
    {
        foreach ($this->entityMetadata->fieldMappings as $associationMapping) {

            $value = null;
            if (isset($item[$associationMapping['fieldName']]) && !is_object($item[$associationMapping['fieldName']])) {
                $value = $item[$associationMapping['fieldName']];
            }

            if (null === $value) {
                continue;
            }

            $setter = 'set' . ucfirst($associationMapping['fieldName']);
            $this->setValue($entity, $value, $setter);
        }
    }

}