<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Testing\TestCase;

class RagTest extends TestCase
{
    /**
     * @test
     */
    public function knn_filter()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 256);
        $blueprint->text('text')->semantic(accuracy: 1, dimensions: 256);
        $blueprint->category('language');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collected = $this->sigmie->collect($indexName, true)
            ->properties($blueprint);

        $collected->aiProvider(new SigmieAI)
            ->populateEmbeddings()
            ->merge([
                new Document([
                    'title' => 'Patient Privacy and Confidentiality Policy',
                    'text' => 'Patient privacy and confidentiality are essential for maintaining trust and respect in healthcare. This policy outlines the rights and responsibilities of patients, healthcare providers, and staff to ensure the protection of sensitive information.',
                    'language' => 'en',
                ]),
                new Document([
                    'title' => 'Emergency Room Triage Protocol',
                    'text' => 'The emergency room triage protocol is a critical component of emergency care. It ensures that patients receive timely and appropriate care based on their medical needs and severity of condition.',
                    'language' => 'en',
                ],),
                new Document([
                    'title' => 'Medication Administration Safety Guidelines',
                    'text' => 'Medication administration safety guidelines are essential for ensuring the safe and effective use of medications. This policy outlines the procedures and protocols for administering medications to patients.',
                    'language' => 'en',
                ],),
            ]);

        $answer = $this->sigmie
            ->newRag($indexName)
            ->properties($blueprint)
            ->question('What is the privacy policy?')
            ->rerank()
            ->filter('language:"en"')
            ->size(3)
            ->prompt("
                Question:
                {{question}}

                Answer using only the context. Cite after each claim.

                Context:
                {{context}}
            ")
            ->answer();

        $this->assertArrayHasKey('answer', $answer);
    }
}
