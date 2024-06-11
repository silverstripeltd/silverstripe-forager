<?php

namespace SilverStripe\Forager\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\Exception\IndexConfigurationException;
use SilverStripe\Forager\Exception\IndexingServiceException;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Interfaces\DocumentMetaProvider;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\Traits\ConfigurationAware;
use SilverStripe\Forager\Service\Traits\RegistryAware;

class DocumentBuilder
{

    use Injectable;
    use ConfigurationAware;
    use RegistryAware;

    public function __construct(IndexConfiguration $configuration, DocumentFetchCreatorRegistry $registry)
    {
        $this->setConfiguration($configuration);
        $this->setRegistry($registry);
    }

    /**
     * @throws IndexingServiceException
     * @throws IndexConfigurationException
     */
    public function toArray(DocumentInterface $document): array
    {
        $idField = $this->getConfiguration()->getIDField();
        $sourceClassField = $this->getConfiguration()->getSourceClassField();

        $data = $document->toArray();
        $data[$idField] = $document->getIdentifier();

        if ($document instanceof DocumentMetaProvider) {
            $extraMeta = $document->provideMeta();
            $data = array_merge($data, $extraMeta);
        }

        $data[$sourceClassField] = $document->getSourceClass();

        return $this->truncateDocument($data);
    }

    public function fromArray(array $data): ?DocumentInterface
    {
        $sourceClassField = $this->getConfiguration()->getSourceClassField();
        $sourceClass = $data[$sourceClassField] ?? null;

        if (!$sourceClass) {
            return null;
        }

        $fetcher = $this->getRegistry()->getFetcher($sourceClass);

        if (!$fetcher) {
            return null;
        }

        return $fetcher->createDocument($data);
    }

    /**
     * @throws IndexingServiceException
     */
    private function truncateDocument(array $data): array
    {
        $indexService = Injector::inst()->get(IndexingInterface::class);
        $documentMaxSize = $indexService->getMaxDocumentSize();

        if (!$documentMaxSize || strlen(json_encode($data)) < $documentMaxSize) {
            return $data;
        }

        while (strlen(json_encode($data)) >= $documentMaxSize) {
            $max = 0;
            $key = '';

            // Determine which field is the largest, so we can halve that to have the most impact
            foreach ($data as $k => $v) {
                $size = strlen(json_encode($v));

                if ($size > $max) {
                    $max = $size;
                    $key = $k;
                }
            }

            // Make sure we don't chop any characters in the middle making them UTF-8 invalid and non-jsonable
            $data[$key] = mb_substr($data[$key], 0, -(floor(mb_strlen($data[$key]) / 2)));
        }

        return $data;
    }

}
