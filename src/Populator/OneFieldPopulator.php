<?php

declare(strict_types=1);

namespace araise\SearchBundle\Populator;

use araise\SearchBundle\Entity\Index;
use araise\SearchBundle\Exception\MethodNotFoundException;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\DBAL;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class OneFieldPopulator extends AbstractPopulator
{
    /**
     * @throws MappingException
     * @throws MethodNotFoundException
     * @throws \ReflectionException
     * @throws Exception
     */
    public function index(object $entity): void
    {
        if ($this->disableEntityListener) {
            return;
        }

        if ($entity instanceof Index) {
            return;
        }

        if ($this->entityWasIndexed($entity)) {
            return;
        }

        $entityName = ClassUtils::getClass($entity);
        if (! $this->indexManager->hasEntityIndexes($entityName)) {
            return;
        }

        $classes = $this->getClassTree($entityName);
        foreach ($classes as $class) {
            if (! $this->canBeIndexed($class)) {
                continue;
            }

            $idMethod = $this->indexManager->getIdMethod($class);

            $groupedContent = $this->collectEntityIndexData($entityName, $entity);

            foreach ($groupedContent as $group => $content) {
                $entry = $this->indexManager->getIndexRepository()->findExisting($class, $group, $entity->{$idMethod}());
                if (! $entry) {
                    $insertData = [];
                    $insertSqlParts = [];
                    $insertData[] = $entity->{$idMethod}();
                    $insertData[] = $class;
                    $insertData[] = $group;
                    $insertData[] = implode(' ', $content);
                    $insertSqlParts[] = '(?,?,?,?)';

                    $this->bulkInsert($insertSqlParts, $insertData);
                } else {
                    $this->update($entry->{$idMethod}(), implode(' ', $content));
                }
            }
        }
    }

    /**
     * Populate index of given entity.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws MethodNotFoundException
     * @throws \ReflectionException|DBAL\Exception
     */
    protected function indexEntity(string $entityName): void
    {
        $workingValues = $this->getIndexEntityWorkingValues($entityName);
        if ($workingValues === false) {
            return;
        }
        [$entities, $idMethod] = $workingValues;

        $i = 0;
        $insertData = [];
        $insertSqlParts = [];

        foreach ($entities as $entity) {
            $groupedContent = $this->collectEntityIndexData($entityName, $entity[0]);

            // Persist entry
            foreach ($groupedContent as $group => $content) {
                $insertData[] = $entity[0]->{$idMethod}();
                $insertData[] = $entityName;
                $insertData[] = $group;
                $insertData[] = implode(' ', $content);
                $insertSqlParts[] = '(?,?,?,?)';
            }

            // Update progress bar every 200 iterations
            // as well as gc
            if ($i % 200 === 0) {
                if (count($insertData)) {
                    $this->bulkInsert($insertSqlParts, $insertData);
                }
                $insertSqlParts = [];
                $insertData = [];

                $this->output->setProgress($i);
                $this->gc();
            }
            ++$i;
        }

        if (count($insertData)) {
            $this->bulkInsert($insertSqlParts, $insertData);
        }

        $this->gc();

        $this->output->progressFinish();
    }

    /**
     * Clean up garbage.
     */
    protected function gc(): void
    {
        $this->entityManager->clear();
        gc_collect_cycles();
    }

    /**
     * @throws \ReflectionException
     * @throws MethodNotFoundException
     */
    protected function collectEntityIndexData($entityName, $entity): array
    {
        $indexes = $this->indexManager->getIndexesOfEntity($entityName);

        $content = [];
        /** @var \araise\SearchBundle\Annotation\Index $index */
        foreach ($indexes as $field => $index) {
            $fieldMethod = $this->indexManager->getFieldAccessorMethod($entityName, $field);

            $formatter = $this->formatterManager->getFormatter($index->getFormatter());
            $formatter->processOptions($index->getFormatterOptions());
            foreach ($index->getGroups() as $indexGroup) {
                $content[$indexGroup][] = $formatter->getString($entity->{$fieldMethod}());
            }
        }

        return $content;
    }
}
