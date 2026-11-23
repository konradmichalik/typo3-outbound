# Migrating a service wrapper

Most projects already talk to their APIs through a hand-written service. This guide moves a typical one to typo3-outbound. The example is a course API: one list, one detail endpoint, a bearer token, a cache.

## Before

```php
final class CourseService
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly FrontendInterface $cache, // injected cache "courses"
        private readonly ExtensionConfiguration $configuration,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function courses(string $category): array
    {
        $key = 'courses_'.md5($category);
        if (is_array($cached = $this->cache->get($key))) {
            return $cached;
        }
        $config = $this->configuration->get('my_site');
        try {
            $response = $this->requestFactory->request(
                $config['courseApiUrl'].'/courses?category='.$category,
                'GET',
                ['headers' => ['Authorization' => 'Bearer '.$config['courseApiToken']], 'timeout' => 30],
            );
            $data = json_decode((string) $response->getBody(), true);
        } catch (\Throwable $exception) {
            $this->logger->error('Course API failed: '.$exception->getMessage());

            return [];
        }
        $this->cache->set($key, $data['data'] ?? [], ['courses'], 3600);

        return $data['data'] ?? [];
    }
}
```

Typical problems, all of them easy to miss in review:

- the category is concatenated into the URL without encoding
- the token sits in extension configuration, often committed
- 30 s timeout inside a page request; no retry, or a retry loop that also repeats writes
- the page cache does not know the data; editors flush everything to see changes
- a failure returns `[]`, so the page silently shows "no courses"
- arrays all the way down; a renamed field breaks the template, not the code
- tests need a real API or a mocked Guzzle stack

## After

The connection and the resource:

```php
#[Outbound\Connection(baseUri: new Setting('courses.baseUri'), allowedHosts: ['api.courses.example'])]
#[Outbound\Auth\Bearer(token: new Secret('courses.token'))]
#[Outbound\Retry(maxRetries: 2)]
interface CourseApi {}

#[Outbound\Resource('/courses')]
interface CoursesApi extends CourseApi
{
    /** @return list<CourseDto> */
    #[Outbound\Get(unwrap: 'data')]
    #[Outbound\Cache(lifetime: 3600, tags: ['courses', 'courses_{category}'], staleOnError: 86400)]
    public function list(#[Outbound\Query] string $category): array;

    #[Outbound\Get('/{id}', unwrap: 'data')]
    #[Outbound\Cache(lifetime: 3600, tags: ['course_{id}'])]
    #[Outbound\OnStatus(404, returns: null)]
    public function find(int $id): ?CourseDto;
}
```

The DTO:

```php
final readonly class CourseDto
{
    public function __construct(
        public int $id,
        public string $title,
        public ?\DateTimeImmutable $startsAt = null,
    ) {}
}
```

The consumer:

```php
final class CourseController extends ActionController
{
    public function __construct(private readonly CoursesApi $courses) {}

    public function listAction(string $category): ResponseInterface
    {
        try {
            $this->view->assign('courses', $this->courses->list($category));
        } catch (ApiException $exception) {
            $this->view->assign('unavailable', true);
        }

        return $this->htmlResponse();
    }
}
```

## Steps

1. **Inventory the calls.** List every URL, method, parameter and response the service uses. `outbound:make:dto` turns a saved response into DTO scaffolds; `outbound:generate` does the whole API if there is an OpenAPI document.
2. **Move configuration.** Base URI into a site setting (`courses.baseUri`), the token into an environment variable referenced by a site setting (`courses.token: 'env:COURSES_TOKEN'`). Remove both from extension configuration.
3. **Declare connection, resources and DTOs.** Place them below the folder your `Services.yaml` resource covers, not in an `exclude` path; they are registered automatically.
4. **Decide the behaviour per endpoint.** Cache lifetime and tags, `staleOnError`, `#[OnStatus]` for expected statuses, `#[InvalidatesCache]` on writes. Run `outbound:debug` to see the effective configuration and `outbound:lint` for likely mistakes.
5. **Replace the service.** Inject the resource interface where the service was used. Keep the old service as a thin adapter for a while if many places use it.
6. **Make failures visible.** Catch `ApiException` (or a specific subclass) where a fallback makes sense; do not swallow errors into empty arrays.
7. **Test by endpoint.** `OutboundTestTrait::mockEndpoint()` in functional tests, `MockTransport` in unit tests.
8. **Check the request and the page.** `outbound:call CoursesApi::list category=yoga --scope=main --dry-run` shows the request with the settings of site `main`; a page using the data must carry the `courses` tag in its page cache entry.

## Upgrading from 0.1 to 0.2

The framework-agnostic parts moved into the package [`konradmichalik/php-outbound`](https://github.com/konradmichalik/php-outbound), which this extension now requires. Composer installs it with the extension, and the TER artefact bundles it. The classes of 0.1 are not aliased.

| 0.1 | 0.2 |
|-----|-----|
| `KonradMichalik\Typo3Outbound\{Attribute, Auth, Cache, Context, Enum, Exception, Format, Http, Mapping, Metadata, Proxy, Resilience, Runtime, Support, Testing, Transport, Value}\…` | `KonradMichalik\Outbound\…` |
| `KonradMichalik\Typo3Outbound\Outbound` | `KonradMichalik\Outbound\Outbound` |
| `KonradMichalik\Typo3Outbound\Value\SiteSetting` | `KonradMichalik\Outbound\Value\Setting` |
| `CallContext::siteIdentifier()` | `CallContext::scope()`, only relevant for your own `CallContext` implementations |
| `StaticCallContext(site: …)` | `StaticCallContext(scope: …)` |
| `KonradMichalik\Typo3Outbound\Typo3\RequestContext` and the other adapters | `KonradMichalik\Typo3Outbound\RequestContext`, the `Typo3` namespace segment is gone |
| `outbound:call … --site=main` | `outbound:call … --scope=main` |

Exception codes, attributes and their parameters stay the same. Replace the namespaces with a search and replace over your code, then flush all caches so the generated proxies and the container are rebuilt.
