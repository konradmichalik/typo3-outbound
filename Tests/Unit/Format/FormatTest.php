<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_outbound" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Format;

use KonradMichalik\Typo3Outbound\Format\{Hydra, JsonApi, PlainJson};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * FormatTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(JsonApi::class)]
#[CoversClass(Hydra::class)]
#[CoversClass(PlainJson::class)]
final class FormatTest extends TestCase
{
    #[Test]
    public function jsonApiFlattensResourcesAndResolvesIncludedWithDepthLimit(): void
    {
        $document = [
            'data' => [['type' => 'articles', 'id' => '1', 'attributes' => ['title' => 'A'], 'relationships' => ['author' => ['data' => ['type' => 'people', 'id' => '9']]]]],
            'included' => [['type' => 'people', 'id' => '9', 'attributes' => ['name' => 'Ada'], 'relationships' => ['favourite' => ['data' => ['type' => 'articles', 'id' => '1']]]], ['type' => 'articles', 'id' => '1', 'attributes' => ['title' => 'A']]],
            'links' => ['next' => 'https://api.example.com/articles?page[number]=2'],
        ];
        $format = new JsonApi(includeDepth: 1);

        $items = $format->collection($document);

        self::assertSame(['id' => '1', 'type' => 'articles', 'title' => 'A', 'author' => ['id' => '9', 'type' => 'people', 'name' => 'Ada', 'favourite' => ['id' => '1', 'type' => 'articles']]], $items[0]);
        self::assertSame('https://api.example.com/articles?page[number]=2', $format->nextLink($document));
        self::assertSame(['data' => ['type' => 'articles', 'attributes' => ['title' => 'B']]], $format->encodeBody(['type' => 'articles', 'title' => 'B']));
    }

    #[Test]
    public function jsonApiDataNullIsNull(): void
    {
        self::assertNull((new JsonApi())->item(['data' => null]));
    }

    #[Test]
    public function jsonApiErrorsBecomeFieldErrorsByPointer(): void
    {
        $error = (new JsonApi())->error(422, ['errors' => [['title' => 'Invalid', 'detail' => 'too short', 'source' => ['pointer' => '/data/attributes/title']]]]);

        self::assertSame(['title' => ['too short']], $error?->fieldErrors);
    }

    #[Test]
    public function hydraAcceptsBothPrefixVariants(): void
    {
        $prefixed = ['hydra:member' => [['@id' => '/api/events/1', '@type' => 'Event', 'name' => 'A']], 'hydra:view' => ['hydra:next' => '/api/events?page=2']];
        $plain = ['member' => [['@id' => '/api/events/1', 'name' => 'A']], 'view' => ['next' => '/api/events?page=2']];

        foreach ([new Hydra(prefix: false), new Hydra(prefix: true)] as $format) {
            self::assertSame([['iri' => '/api/events/1', 'name' => 'A']], $format->collection($prefixed));
            self::assertSame([['iri' => '/api/events/1', 'name' => 'A']], $format->collection($plain));
            self::assertSame('/api/events?page=2', $format->nextLink($prefixed));
        }
    }

    #[Test]
    public function hydraViolationsBecomeFieldErrors(): void
    {
        $error = (new Hydra())->error(422, ['title' => 'Validation failed', 'violations' => [['propertyPath' => 'name', 'message' => 'blank']]]);

        self::assertNotNull($error);
        self::assertSame('Validation failed', $error->title);
        self::assertSame(['name' => ['blank']], $error->fieldErrors);
    }

    #[Test]
    public function plainJsonReadsProblemDetails(): void
    {
        $error = (new PlainJson())->error(400, ['title' => 'Bad', 'detail' => 'x', 'errors' => ['email' => ['invalid']]]);

        self::assertNotNull($error);
        self::assertSame('Bad', $error->title);
        self::assertSame(['email' => ['invalid']], $error->fieldErrors);
        self::assertNull((new PlainJson())->error(500, 'oops'));
    }
}
