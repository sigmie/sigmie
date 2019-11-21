<?php


namespace Sigma\Test\Unit;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sigma\Document\Document;
use Sigma\Mapping\Types\Text;
use Symfony\Component\Config\Resource\ClassExistenceResource;

class AnnotationTest extends TestCase
{
    /**
     * @test
     */
    public function foo(): void
    {
        AnnotationRegistry::registerLoader('class_exists');

        $reflectionClass = new ReflectionClass(Document::class);
        $property = $reflectionClass->getProperty('_id');

        $reader = new AnnotationReader();
        $annotation = $reader->getPropertyAnnotations($property);

        dump($annotation);
        die();
    }
}
