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

namespace KonradMichalik\Typo3Outbound;

use KonradMichalik\Typo3Outbound\Auth\{Authenticator, TokenProvider};
use KonradMichalik\Typo3Outbound\Cache\{CacheInvalidator, CacheLock, CacheTagReporter, InMemoryCacheLock, InMemoryResponseCache, NullCacheTagReporter, ResponseCache, ResponseCacheInvalidator};
use KonradMichalik\Typo3Outbound\Context\{CallContext, ExecutionContext, SapiExecutionContext, StaticCallContext, ValueResolver};
use KonradMichalik\Typo3Outbound\Http\RequestBuilder;
use KonradMichalik\Typo3Outbound\Mapping\{ResponseMapper, ValinorMapper};
use KonradMichalik\Typo3Outbound\Metadata\Registry;
use KonradMichalik\Typo3Outbound\Proxy\{FileProxyStore, ProxyFactory, ProxyStore};
use KonradMichalik\Typo3Outbound\Resilience\{CircuitBreaker, InMemoryStateStore, RateLimitGuard, StateStore};
use KonradMichalik\Typo3Outbound\Runtime\{CacheLayer, CallFactory, ConventionObserver, Dispatcher, EndpointInvoker, Paginator, RequestBudget, ResultMapper, Settings};
use KonradMichalik\Typo3Outbound\Support\{Clock, SystemClock};
use KonradMichalik\Typo3Outbound\Transport\{SymfonyTransport, Transport};
use Psr\Log\{LoggerInterface, NullLogger};
use Symfony\Component\RateLimiter\Storage\{InMemoryStorage, StorageInterface};

use function get_current_user;
use function getmyuid;
use function hash;
use function substr;
use function sys_get_temp_dir;

/**
 * Outbound.
 *
 * Composes the runtime without a DI container: for tests, scripts and use
 * outside TYPO3. Inside TYPO3 the same services are wired by the container.
 *
 *     $outbound = Outbound::create([ContactsApi::class])->withTransport($mock);
 *     $contacts = $outbound->api(ContactsApi::class);
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class Outbound
{
    private ?ProxyFactory $proxies = null;

    /** @var array<class-string, object> */
    private array $instances = [];

    private function __construct(
        private readonly Registry $registry,
        private Transport $transport,
        private ResponseCache $cache,
        private StateStore $store,
        private CacheTagReporter $reporter,
        private CallContext $context,
        private ExecutionContext $execution,
        private Clock $clock,
        private LoggerInterface $logger,
        private Settings $settings,
        private ProxyStore $proxyStore,
        private ResponseMapper $mapper,
        private StorageInterface $rateLimitStorage,
        private CacheLock $cacheLock,
        private ?CacheInvalidator $invalidator = null,
    ) {}

    /**
     * @param list<class-string> $interfaces
     */
    public static function create(array $interfaces): self
    {
        return new self(
            Registry::build($interfaces),
            SymfonyTransport::create(),
            new InMemoryResponseCache(),
            new InMemoryStateStore(),
            new NullCacheTagReporter(),
            new StaticCallContext(),
            new SapiExecutionContext(),
            new SystemClock(),
            new NullLogger(),
            new Settings(),
            new FileProxyStore(sys_get_temp_dir().'/outbound-proxies-'.substr(hash('xxh128', __DIR__.':'.get_current_user().':'.getmyuid()), 0, 12)),
            new ValinorMapper(),
            new InMemoryStorage(),
            new InMemoryCacheLock(),
        );
    }

    public function withTransport(Transport $transport): self
    {
        return $this->with('transport', $transport);
    }

    public function withCache(ResponseCache $cache): self
    {
        return $this->with('cache', $cache);
    }

    public function withCacheLock(CacheLock $cacheLock): self
    {
        return $this->with('cacheLock', $cacheLock);
    }

    public function withCacheInvalidator(CacheInvalidator $invalidator): self
    {
        return $this->with('invalidator', $invalidator);
    }

    public function withStateStore(StateStore $store): self
    {
        return $this->with('store', $store);
    }

    public function withCacheTagReporter(CacheTagReporter $reporter): self
    {
        return $this->with('reporter', $reporter);
    }

    public function withCallContext(CallContext $context): self
    {
        return $this->with('context', $context);
    }

    public function withExecutionContext(ExecutionContext $execution): self
    {
        return $this->with('execution', $execution);
    }

    public function withClock(Clock $clock): self
    {
        return $this->with('clock', $clock);
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return $this->with('logger', $logger);
    }

    public function withSettings(Settings $settings): self
    {
        return $this->with('settings', $settings);
    }

    public function withProxyStore(ProxyStore $proxyStore): self
    {
        return $this->with('proxyStore', $proxyStore);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $interface
     *
     * @return T
     */
    public function api(string $interface): object
    {
        $this->proxies ??= new ProxyFactory($this->proxyStore, $this->invoker());

        /** @var T $proxy */
        $proxy = $this->instances[$interface] ??= $this->proxies->create($interface);

        return $proxy;
    }

    public function registry(): Registry
    {
        return $this->registry;
    }

    public function invoker(): EndpointInvoker
    {
        $values = new ValueResolver($this->context);
        $budget = new RequestBudget($this->settings, $this->execution, $this->clock);
        $tokens = new TokenProvider($this->transport, $this->store, $values, $this->context, $this->clock, $budget);
        $authenticator = new Authenticator($values, $tokens, $this->context);
        $rateLimit = new RateLimitGuard($this->store, $this->execution, $this->clock, $this->rateLimitStorage, $this->context);
        $dispatcher = new Dispatcher(
            $this->transport,
            new CircuitBreaker($this->store, $this->clock, $this->logger, $this->context),
            $rateLimit,
            $authenticator,
            $budget,
            new ConventionObserver($rateLimit, $this->store, $this->logger),
            $this->clock,
            $this->logger,
        );
        $calls = new CallFactory(new RequestBuilder($values, $this->context), $authenticator, $this->store, $this->execution, $this->settings);
        $layer = new CacheLayer($dispatcher, $this->cache, $this->reporter, $this->invalidator ?? new ResponseCacheInvalidator($this->cache), $this->cacheLock, $budget, $this->store, $this->context, $this->clock, $this->logger);
        $results = new ResultMapper($this->mapper, $this->logger);

        return new EndpointInvoker($this->registry, $calls, $layer, $results, new Paginator($calls, $layer, $results, $this->logger));
    }

    private function with(string $property, object $value): self
    {
        $clone = clone $this;
        $clone->{$property} = $value;
        $clone->proxies = null;
        $clone->instances = [];

        return $clone;
    }
}
