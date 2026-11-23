# Features

All examples extend this connection:

```php
use KonradMichalik\Typo3Outbound\Attribute as Outbound;
use KonradMichalik\Typo3Outbound\Value\{Secret, SiteSetting};

#[Outbound\Connection(baseUri: new SiteSetting('crm.baseUri'), allowedHosts: ['crm.example.com'])]
#[Outbound\Auth\Bearer(token: new Secret('crm.token'))]
interface CrmApi {}
```

## Timeouts and retries

```php
#[Outbound\Resource('/contacts')]
#[Outbound\Retry(maxRetries: 2)]
interface ContactsApi extends CrmApi
{
    #[Outbound\Get('/{id}')]
    #[Outbound\Timeout(1.5)]
    public function find(int $id): ContactDto;

    #[Outbound\Post('', idempotencyKey: true)]
    public function create(#[Outbound\Body] ContactDto $contact): ContactDto;
}
```

- Each attempt may take 3 s in web requests and 30 s in CLI; `#[Timeout]` overrides it. All calls of one web request share a budget of 8 s
- `#[Retry]` backs off exponentially with jitter. A `Retry-After` above 1 s (web) or 60 s (CLI) gives up instead of blocking the page
- A POST is only retried with `idempotencyKey: true` or `force: true`, so a timeout never creates a contact twice
- After a 401 an OAuth token is refreshed once and the request resent

## Caching

```php
#[Outbound\Get('/{id}')]
#[Outbound\Cache(lifetime: 600, tags: ['crm_contact_{id}'], staleOnError: 3600)]
public function find(int $id): ContactDto;

#[Outbound\Put('/{id}')]
#[Outbound\InvalidatesCache(tags: ['crm_contact_{id}', 'crm_contacts'])]
public function update(int $id, #[Outbound\Body] ContactDto $contact): ContactDto;
```

- The cache key covers URL, body, site language, principal and `#[Header]` parameters. On a method `#[Cache]` needs an idempotent endpoint; on a connection or resource it applies to the reading endpoints only
- Tags and the remaining lifetime reach the page cache: `outbound:cache:flush crm` or a successful `update()` clears the data and every page showing it
- `staleOnError` serves an expired entry while the API fails; such a page is cached for 10 s at most
- Expired entries with an ETag are revalidated with `If-None-Match`; concurrent misses for the same key cause one API call
- In your own code, e.g. a DataHandler hook, `CacheTags::tag('crm_contact_{id}', ['id' => 42])` produces exactly the tag the cache set

## Rate limits and circuit breaker

```php
#[Outbound\Connection(baseUri: 'https://api.example.com')]
#[Outbound\RateLimit(100, per: '1 minute')]
#[Outbound\CircuitBreaker(failureThreshold: 5, cooldown: 30)]
interface WeatherApi {}
```

- An exhausted limit throws `ApiRateLimited` in web requests and waits in CLI. Quotas the API announces (`X-RateLimit-Remaining`/`-Reset`) count the same way
- An open breaker fails fast with `ApiUnavailable` without touching the network
- Retries need a rate limit token and a closed breaker like the first request
- State is shared by all sites; with a base URI from a site setting, every site keeps its own

## Pagination

```php
/** @return iterable<ContactDto> */
#[Outbound\Get('')]
#[Outbound\Paginate(style: PaginationStyle::Page, size: 100, maxPages: 50)]
public function all(): iterable;

/** @return iterable<ContactDto> */
#[Outbound\Get('/changes')]
#[Outbound\Paginate(style: PaginationStyle::Cursor, itemsPath: 'items', cursorPath: 'next')]
public function changes(): iterable;
```

- Styles: `Page`, `Offset`, `Cursor`, `LinkHeader` (RFC 8288) and `Format` (next link from JSON:API or Hydra)
- Pages load lazily while you iterate; `maxPages` bounds the requests
- `Page` and `Offset` end on an empty page, because servers cap the page size silently. A repeated page, an unchanged cursor or a next link to the current page also ends it
- Next-page links must match the allowed hosts like every other URL

## Formats

```php
#[Outbound\Connection(baseUri: 'https://shop.example.com/api', format: new JsonApi(includeDepth: 2))]
interface ShopApi {}
```

- `PlainJson` (default) reads RFC 9457 problem details
- `JsonApi` flattens resources and resolves `included` up to `includeDepth`
- `Hydra` reads API Platform responses with and without the `hydra:` prefix
- Custom formats implement `ResponseFormat`

## Deferred calls

```php
/** @return Deferred<ContactDto> */
#[Outbound\Get('/{id}')]
public function findLater(int $id): Deferred;
```

```php
$first = $contacts->findLater(1);
$second = $contacts->findLater(2);
render($first->get(), $second->get()); // both requests ran in parallel
```

## API conventions

```php
/** @param array<string, string|FilePart> $parts */
#[Outbound\Post('/{id}/documents')]
public function upload(int $id, #[Outbound\Multipart] array $parts): DocumentDto;
```

```php
$contacts->upload(42, ['title' => 'Contract', 'file' => FilePart::fromPath('/tmp/contract.pdf')]);
```

- `Accept-Language` follows the site language; `sendLanguage: false` on the connection turns it off
- `idempotencyKey: true` sends a stable `Idempotency-Key` across retries; `ifMatch: true` sends the last known ETag as `If-Match`
- `Deprecation` and `Sunset` headers are logged and listed by `outbound:health`
