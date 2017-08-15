<?php

namespace AppBundle\Helper;

use AppBundle\Component\DoctrineWriter;
use AppBundle\Entity\Product;
use Ddeboer\DataImport\Reader\CsvReader;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Validator\Validator\RecursiveValidator as Validator;

class ImportHelper
{
    private $em;
    private $validator;

    public function __construct(Validator $validator, EntityManager $em)
    {
        $this->validator = $validator;
        $this->em = $em;
    }

    public function getReader(string $filename)
    {
        try {
            $file = new \SplFileObject($filename);
            if ($file->getExtension() === 'csv') {
                $csvReader = new CsvReader($file);
                $csvReader->setHeaderRowNumber(0);

                return $csvReader;
            } else {
                throw new FileNotFoundException('Invalid format file. Need csv format');
            }
        } catch (\RuntimeException $e) {
            throw new FileNotFoundException('Failed to open stream: No such file or directory');
        }
    }

    public function getDoctrineWriter(bool $testMode, $entityName)
    {
        $doctrineWriter = null;
        if (!$testMode) {
            $doctrineWriter = new DoctrineWriter($this->em, $entityName);
        }

        return $doctrineWriter;
    }

    public function getRules(): array
    {
        $constraints = [];
        $rules = $this->validator->getMetadataFor(new Product());
        foreach ($rules->properties as $attribute => $propMetadata) {
            foreach ($propMetadata->getConstraints() as $constraint) {
                $constraints[$attribute][] = $constraint;
            }
        }

        return $constraints;
    }
}
