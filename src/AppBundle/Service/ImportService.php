<?php
/**
 * Created by PhpStorm.
 * User: i.razumovsky
 * Date: 8/8/17
 * Time: 1:48 PM.
 */

namespace AppBundle\Service;

use AppBundle\Entity\Product;
use AppBundle\Helper\ImportHelper;
use Ddeboer\DataImport\Reader\CsvReader;
use Ddeboer\DataImport\Result;
use Ddeboer\DataImport\Step\FilterStep;
use Ddeboer\DataImport\Step\MappingStep;
use Ddeboer\DataImport\Step\ValidatorStep;
use Ddeboer\DataImport\Step\ValueConverterStep;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Validator\Validator\ValidatorInterface as Validator;
use Ddeboer\DataImport\Workflow\StepAggregator as Workflow;

class ImportService
{
    private $helper;
    private $validator;
    private $em;
    private $skippedRows = 0;
    private $exceptions = [];
    private $mapping;

    public function __construct(ImportHelper $helper, Validator $validator, EntityManager $em)
    {
        $this->validator = $validator;
        $this->em = $em;
        $this->helper = $helper;
    }

    public function process(CsvReader $reader, $writer): Result
    {
        $workflow = new Workflow($reader);
        $workflow->setSkipItemOnFailure(true);
        if(isset($writer)) {
            $workflow->addWriter($writer);
        }

        $mapping = new MappingStep($this->mapping);

        $converter = new ValueConverterStep();
        $converter->add('[dateDiscontinued]', function ($dateDiscontinued) {
            return $dateDiscontinued === 'yes' ? new \DateTime() : null;
        });

        $validate = new ValidatorStep($this->validator);
        $validate->throwExceptions(true);
        foreach ($this->helper->getRules() as $attribute => $constraints) {
            foreach ($constraints as $constraint) {
                $validate->add($attribute, $constraint);
            }
        }
        $filter = new FilterStep();
        $product = new Product();
        $costAndStockfilter = function ($data) use ($product)  {
            $product->setCost((float) $data['cost']);
            $product->setStock($data['stock']);
            $errors = $this->validator->validate($product, null, ['costAndStockConstraint']);
            if ($errors->count() > 0) {
                ++$this->skippedRows;
                foreach ($errors as $error) {
                    $this->exceptions[] = $error;
                }

                return false;
            }

            return true;
        };
        $filter->add($costAndStockfilter);

        $result = $workflow
            ->addStep($mapping, 4)
            ->addStep($converter, 3)
            ->addStep($validate, 2)
            ->addStep($filter, 1)
            ->process();

        return $result;
    }

    /**
     * @return mixed
     */
    public function getSkippedRows(): int
    {
        return $this->skippedRows;
    }

    /**
     * @return array
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * @return ImportHelper
     */
    public function getHelper(): ImportHelper
    {
        return $this->helper;
    }

    /**
     * @param mixed $mapping
     */
    public function setMapping($mapping)
    {
        $this->mapping = $mapping;
    }
}