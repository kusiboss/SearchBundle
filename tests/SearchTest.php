<?php

declare(strict_types=1);

namespace araise\SearchBundle\Tests;

use araise\SearchBundle\Manager\SearchManager;
use araise\SearchBundle\Populator\OneFieldPopulator;
use araise\SearchBundle\Populator\PopulatorInterface;
use araise\SearchBundle\Tests\App\Entity\Company;

class SearchTest extends AbstractSeaarchTest
{
    public function testSearchAll(): void
    {
        $this->createEntities();

        $searchManager = self::getContainer()->get(SearchManager::class);

        $result = $searchManager->searchByEntites('Mauri');

        self::assertSame(6, count($result));
    }

    public function testSearchEntity(): void
    {
        $this->createEntities();

        $searchManager = self::getContainer()->get(SearchManager::class);

        $result = $searchManager->searchByEntites('Mauri', [Company::class]);

        self::assertSame(1, count($result));
    }

    public function testSearchGroup(): void
    {
        $this->createEntities();

        $searchManager = self::getContainer()->get(SearchManager::class);

        $result = $searchManager->searchByEntites('Mauri', [], ['company']);

        self::assertSame(1, count($result));
    }

    protected function setUp(): void
    {
        /** @var OneFieldPopulator $populator */
        $populator = self::getContainer()->get(OneFieldPopulator::class);
        self::getContainer()->set(PopulatorInterface::class, $populator);
    }
}
