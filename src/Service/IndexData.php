<?php

namespace SilverStripe\Forager\Service;

use Exception;
use InvalidArgumentException;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Interfaces\IndexDataContextProvider;

/**
 * Class to contain configuration data for a single index suffix
 */
class IndexData
{

    use Injectable;
    use Extensible;

    public const string CONTEXT_KEY = 'context';
    public const string CONTEXT_KEY_DEFAULT = 'default';

    public function __construct(private array $data, private string $suffix)
    {
    }

    /**
     * Index contexts
     *
     * @var array
     */
    public array $contexts = [];

    public function getData(): array
    {
        return $this->data;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function getClassData(): array
    {
        return $this->data['includeClasses'];
    }

    public function getClassConfig(string $class): array
    {
        $classes = $this->getClassData();

        if (!array_key_exists($class, $classes)) {
            throw new Exception(sprintf("No data for the '%s' class is configured", $class));
        }

        return $classes[$class];
    }

    /**
     * @return string[]
     */
    public function getClasses(): array
    {
        return array_keys($this->getClassData());
    }

    public function getContextKey(): string
    {
        $key = static::CONTEXT_KEY_DEFAULT;

        if (!array_key_exists(static::CONTEXT_KEY, $this->data)) {
            return $key;
        }

        return $this->data[static::CONTEXT_KEY];
    }

    public function withIndexContext(callable $callback): void
    {

        $contextKey = $this->getContextKey();
        $contexts = $this->contexts;

        if (!array_key_exists($contextKey, $contexts)) {
            throw new InvalidArgumentException(sprintf('No context configured for key: "%s"', $contextKey));
        }

        $context = $contexts[$contextKey];

        $wrappers = array_map(
            function (IndexDataContextProvider $provider) {
                return $provider->getContext();
            },
            array_values($context)
        );

        $next = function () use ($callback): mixed {
            return $callback($this);
        };

        foreach (array_reverse($wrappers) as $wrapper) {
            $next = function () use ($wrapper, $next): mixed {
                 return $wrapper($next, $this);
            };
        }

        $next();
    }

}
