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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Petstore;

use KonradMichalik\OutboundPetstore\Api\{PetApi, StoreApi, UserApi};
use KonradMichalik\OutboundPetstore\Dto\{OrderDto, OrderStatus, PetDto, PetStatus};
use KonradMichalik\Typo3Outbound\Exception\{ApiUnavailable, ApiValidationFailed};
use KonradMichalik\Typo3Outbound\Support\Redactor;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\{FakeClock, PetstoreKernel, RecordingTagReporter};
use KonradMichalik\Typo3Outbound\Value\Deferred;
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;

/**
 * PetstoreTest.
 *
 * End-to-end behaviour of the runtime against the Petstore fixture, with the
 * transport mocked by endpoint.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversNothing]
final class PetstoreTest extends TestCase
{
    private MockTransport $mock;
    private FakeClock $clock;
    private RecordingTagReporter $reporter;

    protected function setUp(): void
    {
        $this->mock = new MockTransport();
        $this->clock = new FakeClock();
        $this->reporter = new RecordingTagReporter();
    }

    #[Test]
    public function findMapsNestedObjectsListsAndEnums(): void
    {
        $this->mock->on(PetApi::class, 'find', ['petId' => 10])->respondWithFixture(PetstoreKernel::RESPONSES.'/pet-10.json');

        $pet = $this->pets()->find(10);

        self::assertInstanceOf(PetDto::class, $pet);
        self::assertSame('doggie', $pet->name);
        self::assertSame('Dogs', $pet->category?->name);
        self::assertSame('friendly', $pet->tags[0]->name);
        self::assertSame(PetStatus::Available, $pet->status);
        self::assertSame('https://petstore3.swagger.io/api/v3/pet/10', $this->mock->calls()[0]->request->url);
    }

    #[Test]
    public function secondFindIsServedFromCacheAndReportsTagsToThePageCache(): void
    {
        $this->mock->on(PetApi::class, 'find')->respondWithFixture(PetstoreKernel::RESPONSES.'/pet-10.json');
        $pets = $this->pets();

        $pets->find(10);
        $pets->find(10);

        self::assertSame(1, $this->mock->count(PetApi::class, 'find'));
        self::assertSame(['outbound_petstore', 'petstore_pet_10'], $this->reporter->reports[1]['tags']);
        self::assertSame(60, $this->reporter->reports[1]['lifetime']);
    }

    #[Test]
    public function unknownPetReturnsNullThroughOnStatus(): void
    {
        $this->mock->on(PetApi::class, 'find')->respondWith(404, 'Pet not found', ['content-type' => 'text/plain']);

        self::assertNull($this->pets()->find(999));
    }

    #[Test]
    public function findByStatusSendsTheEnumValueAndToleratesUnknownFields(): void
    {
        $this->mock->on(PetApi::class, 'findByStatus')->respondWithFixture(PetstoreKernel::RESPONSES.'/pets-available.json');

        $pets = $this->pets()->findByStatus(PetStatus::Available);

        self::assertCount(2, $pets);
        self::assertSame('whiskers', $pets[1]->name);
        self::assertStringEndsWith('/pet/findByStatus?status=available', $this->mock->calls()[0]->request->url);
    }

    #[Test]
    public function findByTagsRepeatsTheParameterForEveryTag(): void
    {
        $this->mock->on(PetApi::class, 'findByTags')->respondWithJson([]);

        $this->pets()->findByTags(['friendly', 'small']);

        self::assertStringEndsWith('/pet/findByTags?tags=friendly&tags=small', $this->mock->calls()[0]->request->url);
    }

    #[Test]
    public function addSendsTheNormalizedPetAsJson(): void
    {
        $this->mock->on(PetApi::class, 'add')->respondWithFixture(PetstoreKernel::RESPONSES.'/pet-10.json');

        $this->pets()->add(new PetDto('doggie', ['https://example.org/doggie.jpg'], status: PetStatus::Pending));

        $request = $this->mock->calls()[0]->request;
        self::assertSame('POST', $request->method);
        self::assertSame('application/json', $request->headers['Content-Type']);
        self::assertSame(
            ['name' => 'doggie', 'photoUrls' => ['https://example.org/doggie.jpg'], 'id' => null, 'category' => null, 'tags' => [], 'status' => 'pending'],
            json_decode((string) $request->body, true),
        );
    }

    #[Test]
    public function deleteSendsTheApiKeyHeaderAndMarksItSensitive(): void
    {
        $this->mock->on(PetApi::class, 'delete')->respondWith(200);

        $this->pets()->delete(10, 'special-key');

        $request = $this->mock->calls()[0]->request;
        self::assertSame('special-key', $request->headers['api_key']);
        self::assertSame(Redactor::MASK, Redactor::headers($request->headers, $request->sensitiveKeys)['api_key']);
    }

    #[Test]
    public function loginReturnsThePlainTextBodyAndRedactsThePassword(): void
    {
        $this->mock->on(UserApi::class, 'login')->respondWith(200, 'logged in user session:1234', ['content-type' => 'text/plain']);

        self::assertSame('logged in user session:1234', $this->users()->login('theUser', 'secret'));
        $request = $this->mock->calls()[0]->request;
        self::assertStringEndsWith('password=%5Bredacted%5D&username=theUser', str_replace(Redactor::MASK, rawurlencode(Redactor::MASK), Redactor::url($request->url, $request->sensitiveKeys)));
    }

    #[Test]
    public function idempotentCallsAreRetriedButPostIsNot(): void
    {
        $this->mock->on(PetApi::class, 'find')->respondWith(503)->respondWithFixture(PetstoreKernel::RESPONSES.'/pet-10.json');
        $this->mock->on(PetApi::class, 'add')->respondWith(503);

        self::assertSame('doggie', $this->pets()->find(10)?->name);
        self::assertSame(2, $this->mock->count(PetApi::class, 'find'));
        self::assertCount(1, $this->clock->sleeps);

        $this->expectException(ApiUnavailable::class);
        try {
            $this->pets()->add(new PetDto('doggie'));
        } finally {
            self::assertSame(1, $this->mock->count(PetApi::class, 'add'));
        }
    }

    #[Test]
    public function placeOrderSendsAQuotedIdempotencyKeyAndMapsDates(): void
    {
        $this->mock->on(StoreApi::class, 'placeOrder')->respondWithFixture(PetstoreKernel::RESPONSES.'/order-5.json');

        $order = $this->store()->placeOrder(new OrderDto(petId: 10, quantity: 1));

        self::assertSame(OrderStatus::Placed, $order->status);
        self::assertSame('2026-11-15', $order->shipDate?->format('Y-m-d'));
        self::assertMatchesRegularExpression('/^"[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}"$/', $this->mock->calls()[0]->request->headers['Idempotency-Key']);
    }

    #[Test]
    public function inventoryIsMappedOntoAMap(): void
    {
        $this->mock->on(StoreApi::class, 'inventory')->respondWithFixture(PetstoreKernel::RESPONSES.'/inventory.json');

        self::assertSame(['available' => 7, 'pending' => 2, 'sold' => 5], $this->store()->inventory());
    }

    #[Test]
    public function deferredCallsAreSentBeforeTheirResultsAreRead(): void
    {
        $this->mock->on(PetApi::class, 'findLater')->respondWithFixture(PetstoreKernel::RESPONSES.'/pet-10.json');
        $pets = $this->pets();

        $first = $pets->findLater(10);
        $second = $pets->findLater(11);

        self::assertInstanceOf(Deferred::class, $first);
        self::assertSame(2, $this->mock->count(PetApi::class, 'findLater'));
        self::assertSame('doggie', $first->get()->name);
        self::assertSame('doggie', $second->get()->name);
    }

    #[Test]
    public function staleEntryIsServedWhenTheApiFails(): void
    {
        $this->mock->on(PetApi::class, 'find')->respondWithFixture(PetstoreKernel::RESPONSES.'/pet-10.json')->respondWith(500);
        $pets = $this->pets();
        $pets->find(10);

        $this->clock->advance(120);

        self::assertSame('doggie', $pets->find(10)?->name);
    }

    #[Test]
    public function circuitBreakerOpensAfterRepeatedFailures(): void
    {
        $this->mock->on(UserApi::class, 'find')->respondWith(500);
        $users = $this->users();
        for ($i = 0; $i < 5; ++$i) {
            try {
                $users->find('theUser');
            } catch (ApiUnavailable) {
            }
        }
        $sent = $this->mock->count(UserApi::class, 'find');

        try {
            $users->find('theUser');
            self::fail('Expected an open circuit breaker.');
        } catch (ApiUnavailable $exception) {
            self::assertStringContainsString('circuit breaker open', $exception->getMessage());
        }
        self::assertSame($sent, $this->mock->count(UserApi::class, 'find'));
    }

    #[Test]
    public function problemDetailsBecomeFieldErrors(): void
    {
        $this->mock->on(PetApi::class, 'update')->respondWithJson(
            ['title' => 'Invalid pet', 'invalid-params' => [['name' => 'name', 'reason' => 'must not be empty']]],
            422,
            ['content-type' => 'application/problem+json'],
        );

        try {
            $this->pets()->update(new PetDto(''));
            self::fail('Expected a validation error.');
        } catch (ApiValidationFailed $exception) {
            self::assertSame(['name' => ['must not be empty']], $exception->fieldErrors);
            self::assertSame('PetApi::update', $exception->endpoint);
        }
    }

    private function pets(): PetApi
    {
        return PetstoreKernel::create($this->mock, $this->clock, $this->reporter)->api(PetApi::class);
    }

    private function store(): StoreApi
    {
        return PetstoreKernel::create($this->mock, $this->clock, $this->reporter)->api(StoreApi::class);
    }

    private function users(): UserApi
    {
        return PetstoreKernel::create($this->mock, $this->clock, $this->reporter)->api(UserApi::class);
    }
}
