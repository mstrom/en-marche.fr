<?php

namespace AppBundle\Algolia;

use Algolia\AlgoliaSearchBundle\Indexer\Indexer;
use Doctrine\ORM\EntityManagerInterface;

class ManualIndexer implements ManualIndexerInterface
{
    /**
     * @var \Algolia\AlgoliaSearchBundle\Indexer\ManualIndexer
     */
    private $indexer;

    public function __construct(Indexer $algolia, EntityManagerInterface $manager)
    {
        $this->indexer = $algolia->getManualIndexer($manager);
    }

    public function index($entities, array $options = []): void
    {
        $this->indexer->index($entities, $options);
    }
}
