<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class ManticoreDataCollector extends DataCollector
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $this->data['nb_queries'] = $this->logger->getNbQueries();
        $this->data['queries'] = $this->logger->getQueries();
    }

    public function getQueryCount(): int
    {
        return $this->data['nb_queries'];
    }

    public function getQueries(): array
    {
        return $this->data['queries'];
    }

    public function getTime(): int
    {
        $time = 0;
//        foreach ($this->data['queries'] as $query) {
//            $time += $query['engineMS'];
//        }

        return $time;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'manticore.data_collector';
    }

    public function reset(): void
    {
        $this->data = [];
        $this->logger->reset();
    }
}
