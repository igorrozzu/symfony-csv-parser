<?php


namespace Tests\AppBundle\Service;

use Ddeboer\DataImport\Reader;
use Ddeboer\DataImport\Writer;
use AppBundle\Helper\ImportHelper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;


class ImportHelperTest extends WebTestCase
{
    private $importer;

    private function getImporter()
    {
        if($this->importer === null) {
            $kernel = static::bootKernel();
            $this->importer = $kernel->getContainer()->get('app.import');
        }
        return $this->importer;
    }

    public function testImportProcessPositive()
    {
        $reader = $this->getImporter()->getHelper()->getReader('tests/AppBundle/Files/stock-good.csv');
        $result = $this->getImporter()->process($reader, null);
        $this->assertEquals($result->getErrorCount(), 0);
        $this->assertEquals($this->getImporter()->getSkippedRows(), 0);
    }

    public function testImportProcessInvalidData()
    {
        $reader = $this->getImporter()->getHelper()->getReader('tests/AppBundle/Files/stock-invalid-data.csv');
        $result = $this->getImporter()->process($reader, null);
        $this->assertEquals($result->getErrorCount(), 4);
        $this->assertEquals($this->getImporter()->getSkippedRows(), 0);
    }

    public function testImportProcessInvalidRuleForCostAndStock()
    {
        $reader = $this->getImporter()->getHelper()->getReader('tests/AppBundle/Files/stock-invalid-rule-stock-cost.csv');
        $result = $this->getImporter()->process($reader, null);
        $this->assertEquals($result->getErrorCount(), 0);
        $this->assertEquals($this->getImporter()->getSkippedRows(), 3);
    }

    public function testImportProcessDublicateProductCode()
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $reader = $this->getImporter()->getHelper()->getReader('tests/AppBundle/Files/stock-dublicate-product-code.csv');
        $writer = $this->getImporter()->getHelper()->getDoctrineWriter(false, 'AppBundle:Product');
        $result = $this->getImporter()->process($reader, $writer);
    }

}