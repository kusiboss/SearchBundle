<?php

declare(strict_types=1);

namespace whatwedo\SearchBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use whatwedo\SearchBundle\Tests\App\Entity\Company;
use whatwedo\SearchBundle\Tests\App\Entity\Contact;
use whatwedo\SearchBundle\Tests\App\Factory\CompanyFactory;
use whatwedo\SearchBundle\Tests\App\Factory\ContactFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class EntityCreationTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testCreateCompanies()
    {
        $entities = CompanyFactory::new()->withoutPersisting()->createMany(100);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach ($entities as $entity) {
            $em->persist($entity->object());
        }
        $em->flush();
        $this->assertSame(100, $em->getRepository(Company::class)->count([]));
    }

    public function testCreateContacts()
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $entities = CompanyFactory::new()->withoutPersisting()->createMany(100);
        foreach ($entities as $entity) {
            $em->persist($entity->object());
        }

        $em->flush();

        $entities = ContactFactory::new()->withoutPersisting()->createMany(1000);

        foreach ($entities as $entity) {
            $em->persist($entity->object());
        }

        $em->flush();

        $this->assertSame(100, $em->getRepository(Company::class)->count([]));
        $this->assertSame(1000, $em->getRepository(Contact::class)->count([]));
    }
}
