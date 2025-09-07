<?php

declare(strict_types=1);

namespace Sigmie\Search\Formatters;

use Sigmie\Document\RerankedHit;
use Sigmie\Mappings\Properties;

class RerankedSearchResponse extends SigmieSearchResponse
{
    protected array $rerankedHits = [];
    protected SigmieSearchResponse $originalResponse;
    
    public function __construct(SigmieSearchResponse $originalResponse, array $rerankedHits = [])
    {
        // Get properties from original response using reflection or public method
        $reflection = new \ReflectionClass($originalResponse);
        $propsProperty = $reflection->getProperty('properties');
        $propsProperty->setAccessible(true);
        $properties = $propsProperty->getValue($originalResponse);
        
        // Call parent constructor with properties
        parent::__construct($properties ?? new Properties());
        
        // Store original response and copy its data
        $this->originalResponse = $originalResponse;
        
        // Copy internal state from original response
        $searchProperty = $reflection->getProperty('search');
        $searchProperty->setAccessible(true);
        $this->search = $searchProperty->getValue($originalResponse);
        
        $queryRawProperty = $reflection->getProperty('queryResponseRaw');
        $queryRawProperty->setAccessible(true);
        $this->queryResponseRaw = $queryRawProperty->getValue($originalResponse);
        
        $facetsRawProperty = $reflection->getProperty('facetsResponseRaw');
        $facetsRawProperty->setAccessible(true);
        $this->facetsResponseRaw = $facetsRawProperty->getValue($originalResponse);
        
        $errorsProperty = $reflection->getProperty('errors');
        $errorsProperty->setAccessible(true);
        $this->errors = $errorsProperty->getValue($originalResponse);
        
        // Store reranked hits
        $this->rerankedHits = $rerankedHits;
    }
    
    public function hits(): array
    {
        // Return reranked hits if available, otherwise fallback to original hits
        if (!empty($this->rerankedHits)) {
            return $this->rerankedHits;
        }
        
        return parent::hits();
    }
    
    public function getContext()
    {
        return $this->search ?? null;
    }
}