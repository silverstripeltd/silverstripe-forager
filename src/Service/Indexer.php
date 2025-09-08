<?php

namespace SilverStripe\Forager\Service;

use InvalidArgumentException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Interfaces\DependencyTracker;
use SilverStripe\Forager\Interfaces\DocumentAddHandler;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Interfaces\DocumentRemoveHandler;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\Traits\ConfigurationAware;
use SilverStripe\Forager\Service\Traits\ServiceAware;

class Indexer
{

    use Injectable;
    use Configurable;
    use ConfigurationAware;
    use ServiceAware;

    public const int METHOD_DELETE = 0;

    public const int METHOD_ADD = 1;

    private static array $dependencies = [
        'indexService' => '%$' . IndexingInterface::class,
    ];

    private bool $finished = false;

    private array $chunks = [];

    /**
     * @var DocumentInterface[]
     */
    private array $documents = [];

    private string $indexSuffix;

    private int $batchSize;

    private int $method;

    private bool $processDependencies = true;

    private bool $isComplete = false;

    public function __construct(
        string $indexSuffix,
        array $documents = [],
        int $method = self::METHOD_ADD,
        ?int $batchSize = null
    ) {
        $this->setConfiguration(IndexConfiguration::singleton());
        $this->setIndexSuffix($indexSuffix);
        $this->setMethod($method);
        $this->setBatchSize($batchSize ?: $this->getConfiguration()->getBatchSize());
        $this->setProcessDependencies($this->getConfiguration()->shouldTrackDependencies());
        $this->setDocuments($documents);
    }

    public function processNode(): void
    {
        $remainingChildren = $this->chunks;
        /** @var DocumentInterface[] $documents */
        $documents = array_shift($remainingChildren);

        if ($this->method === static::METHOD_DELETE) {
            $this->getIndexService()->removeDocuments($this->getIndexSuffix(), $documents);
        } else {
            $toRemove = [];
            $toUpdate = [];

            foreach ($documents as $document) {
                if (!$this->getConfiguration()->isClassIndexed($document->getSourceClass())) {
                    continue;
                }

                if ($document->shouldIndex()) {
                    if ($document instanceof DocumentAddHandler) {
                        $document->onAddToSearchIndexes(DocumentAddHandler::BEFORE_ADD);
                    }

                    $toUpdate[] = $document;
                } else {
                    if ($document instanceof DocumentRemoveHandler) {
                        $document->onRemoveFromSearchIndexes(DocumentRemoveHandler::BEFORE_REMOVE);
                    }

                    $toRemove[] = $document;
                }
            }

            if ($toUpdate) {
                $this->getIndexService()->addDocuments($this->getIndexSuffix(), $toUpdate);

                foreach ($toUpdate as $document) {
                    if ($document instanceof DocumentAddHandler) {
                        $document->onAddToSearchIndexes(DocumentAddHandler::AFTER_ADD);
                    }
                }
            }

            if ($toRemove) {
                $this->getIndexService()->removeDocuments($this->getIndexSuffix(), $toRemove);

                foreach ($toRemove as $document) {
                    if ($document instanceof DocumentRemoveHandler) {
                        $document->onRemoveFromSearchIndexes(DocumentRemoveHandler::AFTER_REMOVE);
                    }
                }
            }
        }

        $this->chunks = $remainingChildren;

        if ($this->processDependencies) {
            foreach ($documents as $document) {
                if (!$document instanceof DependencyTracker) {
                    continue;
                }

                $dependentDocs = array_filter(
                    $document->getDependentDocuments(),
                    function (DocumentInterface $dependentDocument) use ($document) {
                        return $dependentDocument->getIdentifier() !== $document->getIdentifier();
                    }
                );

                if ($dependentDocs) {
                    // Indexer::METHOD_ADD as default parameter make sure we check first its related documents
                    // and decide whether we should delete or update them automatically.
                    $child = static::create(
                        $this->getIndexSuffix(),
                        $dependentDocs,
                        self::METHOD_ADD,
                        $this->getBatchSize()
                    );

                    while (!$child->finished()) {
                        $child->processNode();
                    }
                }
            }
        }

        if (!count($remainingChildren)) {
            $this->isComplete = true;
        }
    }

    public function setMethod(mixed $method): static
    {
        if (!in_array($method, [self::METHOD_ADD, self::METHOD_DELETE])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid method: %s',
                $method
            ));
        }

        $this->method = $method;

        return $this;
    }

    public function getMethod(): int
    {
        return $this->method;
    }

    public function setProcessDependencies(bool $processDependencies): static
    {
        $this->processDependencies = $processDependencies;

        return $this;
    }

    public function setBatchSize(int $batchSize): static
    {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Batch size must be greater than 0');
        }

        $this->batchSize = $batchSize;
        $this->chunks = array_chunk($this->documents, $batchSize);

        return $this;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function finished(): bool
    {
        return $this->isComplete;
    }

    public function getChunkCount(): int
    {
        return sizeof($this->chunks);
    }

    /**
     * @return DocumentInterface[]
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * @param DocumentInterface[] $documents
     */
    public function setDocuments(array $documents): static
    {
        $this->documents = $documents;
        $this->chunks = array_chunk($this->documents, $this->getBatchSize());

        return $this;
    }

    public function getIndexSuffix(): string
    {
        return $this->indexSuffix;
    }

    public function setIndexSuffix(string $indexSuffix): static
    {
        $this->indexSuffix = $indexSuffix;

        return $this;
    }

}
