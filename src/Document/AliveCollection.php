<?php

declare(strict_types=1);

namespace Sigmie\Document;

use ArrayAccess;
use Countable;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\Actions as DocumentActions;
use Sigmie\Document\Contracts\DocumentCollection;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Shared\Mappings;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\HTML;
use Sigmie\Mappings\Types\Text;
use Sigmie\Semantic\DocumentEmbeddings;
use Sigmie\Shared\EmbeddingsProvider;
use Traversable;
use Sigmie\Shared\Collection;

class AliveCollection implements ArrayAccess, Countable, DocumentCollection
{
    use DocumentActions;
    use IndexActions;
    use LazyEach;
    use Search;
    use Mappings;
    use EmbeddingsProvider;

    protected bool $retrieveEmbeddings = false;

    protected bool $populateEmbeddings = true;

    public function __construct(
        protected string $name,
        ElasticsearchConnection $connection,
        protected string $refresh = 'false'
    ) {
        $this->setElasticsearchConnection($connection);

        $this->properties = new Properties();
    }

    public function populateEmbeddings(bool $value = true)
    {
        $this->populateEmbeddings = $value;

        return $this;
    }

    public function retrieveEmbeddings(bool $value = true)
    {
        $this->retrieveEmbeddings = $value;

        return $this;
    }

    public function refresh()
    {
        $this->refreshIndex($this->name);

        return $this;
    }

    public function replace(Document $document): Document
    {
        $document = $this->documentEmbeddings($document);

        $doc = $this->updateDocument($this->name, $document, $this->refresh);

        return $doc;
    }

    public function all(): Traversable
    {
        return $this->getIterator();
    }

    public function has(string $_id): bool
    {
        return $this->documentExists($this->name, $_id);
    }

    public function take(int $limit)
    {
        if ($limit > 0) {
            return $this->listDocuments($this->name, 0, $limit)->toArray();
        }

        $total = $this->count();
        $pageSize = abs($limit);
        $lastPage = (int) ceil($total / $pageSize);
        $offset = ($lastPage - 1) * $pageSize;

        return $this->listDocuments($this->name, $offset, $pageSize)->toArray();
    }

    public function add(Document $document): Document
    {
        $document = $this->documentEmbeddings($document);

        $document = $this->createDocument($this->name, $document, $this->refresh);

        return $document;
    }

    public function merge(array $docs): AliveCollection
    {
        $documentEmbeddings = new DocumentEmbeddings($this->properties, $this->aiProvider);
        
        $docs = array_map(fn(Document $doc) => $documentEmbeddings->make($doc), $docs);

        $collection = $this->upsertDocuments($this->name, $docs, $this->refresh);

        return $this;
    }

    private function documentEmbeddings(Document $document): Document
    {
        $embeddings = [];
        $fields = $this->properties->nestedSemanticFields();

        if ($fields->isEmpty()) {
            return $document;
        }

        // First pass: collect field texts by strategy
        $fieldTexts = [];
        $fieldStrategies = [];

        $fields->each(function (Text $field, $name) use (&$fieldTexts, &$fieldStrategies, $document) {

            $value = dot($document->_source)->get($name);

            if (!$value) {
                return;
            }

            $fieldStrategies[$name] = $field->strategy();
            
            // Handle both scalar and array values
            if (is_array($value)) {
                $fieldTexts[$name] = $value;
            } else {
                $fieldTexts[$name] = [$value];
            }
        });

        if (empty($fieldTexts)) {
            return $document;
        }

        // Second pass: prepare embedding items based on strategy
        $embeddingItems = [];
        $fieldMap = [];
        
        foreach ($fieldTexts as $name => $values) {
            $strategy = $fieldStrategies[$name];
            $field = $fields->get($name);
            
            if ($strategy === VectorStrategy::Concatenate) {
                // For Concatenate: join all values with space and create a single embedding
                $concatenated = implode(' ', $values);
                $embeddingItems[] = [
                    'text' => $concatenated,
                    'type' => $field
                ];
                $fieldMap[count($embeddingItems) - 1] = [
                    'field' => $name,
                    'index' => null,
                    'strategy' => $strategy
                ];
            } else if ($strategy === VectorStrategy::Average || $strategy === VectorStrategy::ScriptScore) {
                // For Average and ScriptScore: process each value individually
                foreach ($values as $i => $value) {
                    $embeddingItems[] = [
                        'text' => $value,
                        'type' => $field
                    ];
                    $fieldMap[count($embeddingItems) - 1] = [
                        'field' => $name,
                        'index' => $i,
                        'strategy' => $strategy
                    ];
                }
            }
        }

        // Get embeddings from AI provider
        $batchResults = $this->aiProvider->batchEmbed($embeddingItems);

        // Group embeddings per field
        $fieldEmbeddings = [];
        foreach ($batchResults as $index => $result) {
            if (!isset($result['embeddings']) || !is_array($result['embeddings'])) {
                continue;
            }

            $map = $fieldMap[$index] ?? null;
            if (!$map) {
                continue;
            }

            $field = $map['field'];
            $i = $map['index'];
            $strategy = $map['strategy'];
            
            // Store embeddings based on vector strategy
            if ($strategy === VectorStrategy::Concatenate) {
                // For Concatenate: directly store the embedding
                $embeddings[$field] = $result['embeddings'];
            } else {
                // For other strategies: collect for further processing
                $fieldEmbeddings[$field] ??= [];
                $fieldEmbeddings[$field][$i] = $result['embeddings'];
            }
        }
        
        // Process remaining strategies
        foreach ($fieldEmbeddings as $field => $values) {
            if (empty($values)) continue;
            
            $strategy = $fieldStrategies[$field];
            
            if ($strategy === VectorStrategy::ScriptScore) {
                // For ScriptScore: create array of objects with embedding field
                $embeddings[$field] = array_map(function($embedding) {
                    return ['embedding' => $embedding];
                }, $values);
            } else if ($strategy === VectorStrategy::Average && count($values) > 1) {
                // For Average: compute average of all vectors
                $dimensions = count(reset($values));
                $sum = array_fill(0, $dimensions, 0);
                
                foreach ($values as $vector) {
                    foreach ($vector as $i => $val) {
                        $sum[$i] += $val;
                    }
                }
                
                $avg = array_map(function($total) use ($values) {
                    return $total / count($values);
                }, $sum);
                
                $embeddings[$field] = $avg;
            } else if ($strategy === VectorStrategy::Average && count($values) === 1) {
                // Single item for Average: use as is
                $embeddings[$field] = reset($values);
            }
        }

        $document['embeddings'] = $embeddings;

        return $document;
    }

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    public function clear(): void
    {
        $this->indexAPICall("{$this->name}/_delete_by_query?refresh={$this->refresh}", 'POST', [
            'query' => ['match_all' => (object) []],
        ]);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function remove(array|string $_id): bool
    {
        if (is_array($_id)) {
            return $this->deleteDocuments($this->name, $_id, $this->refresh);
        }

        return $this->deleteDocument($this->name, $_id, $this->refresh);
    }

    public function get(string $_id): ?Document
    {
        return $this->getDocument($this->name, $_id);
    }

    public function count(): int
    {
        $res = $this->countAPICall($this->name);

        return $res->json('count');
    }

    public function getIterator(): Traversable
    {
        return $this->indexGenerator();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): ?Document
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $_id, mixed $doc): void
    {
        $this->add($doc);
    }

    public function offsetUnset(mixed $_id): void
    {
        $this->remove((string) $_id);
    }
}
