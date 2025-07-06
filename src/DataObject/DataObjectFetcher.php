<?php

namespace SilverStripe\Forager\DataObject;

use InvalidArgumentException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Interfaces\DocumentFetcherInterface;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class DataObjectFetcher implements DocumentFetcherInterface
{

    use Configurable;
    use Injectable;

    private ?string $dataObjectClass = null;

    private ?int $batchSize = null;

    private int $offset = 0;

    public function __construct(string $class)
    {
        if (!is_subclass_of($class, DataObject::class)) {
            throw new InvalidArgumentException(sprintf(
                '%s is not a subclass of %s',
                $class,
                DataObject::class
            ));
        }

        $this->dataObjectClass = $class;
    }

    public function getBatchSize(): ?int
    {
        return $this->batchSize;
    }

    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }

    public function incrementOffsetUp(): void
    {
        $this->offset += $this->batchSize;
    }

    public function incrementOffsetDown(): void
    {
        // Never go below 0
        $this->offset = max(0, ($this->offset - $this->batchSize));
    }

    /**
     * @return DocumentInterface[]
     */
    public function fetch(): array
    {
        $list = $this->createDataList($this->getBatchSize(), $this->getOffset());
        $docs = [];

        foreach ($list as $record) {
            $docs[] = DataObjectDocument::create($record);
        }

        return $docs;
    }

    public function getTotalDocuments(): int
    {
        return $this->createDataList()->count();
    }

    public function createDocument(array $data): ?DocumentInterface
    {
        $idField = DataObjectDocument::config()->get('record_id_field');
        $ID = $data[$idField] ?? null;

        if (!$ID) {
            throw new InvalidArgumentException(sprintf(
                'No %s field found: %s',
                $idField,
                print_r($data, true)
            ));
        }

        $dataObject = DataObject::get_by_id($this->dataObjectClass, $ID);

        if (!$dataObject) {
            return null;
        }

        return DataObjectDocument::create($dataObject);
    }

    private function createDataList(?int $limit = null, ?int $offset = 0): DataList
    {
        $list = DataList::create($this->dataObjectClass);

        return $list->limit($limit, $offset);
    }

}
