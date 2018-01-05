<?php

namespace AppBundle\Algolia;

use Algolia\AlgoliaSearchBundle\Indexer\Indexer;
use Algolia\AlgoliaSearchBundle\Indexer\ManualIndexer;
use Doctrine\ORM\EntityManagerInterface;

class ManualIndexer implements ManualIndexerInterface
{
    /**
     * @var ManualIndexer
     */
    private $algolia;

    public function __construct(Indexer $algolia, EntityManagerInterface $em)
    {
        $this->algolia = $algolia->getManualIndexer($em);
    }

    public function index($entities, array $options = []): void
    {
        $this->algolia->index($entities, $options);
    }
}
