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

namespace KonradMichalik\Typo3Outbound\Metadata;

use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use KonradMichalik\Typo3Outbound\Attribute\Auth\{Authentication, OAuth2ClientCredentials, TokenLogin};
use KonradMichalik\Typo3Outbound\Attribute\{Body, Cache, CircuitBreaker, Connection, Endpoint, Header, HealthCheck, InvalidatesCache, MapKeys, Multipart, OnStatus, Paginate, Path, Query, RateLimit, Resource, Retry, Sensitive, Timeout};
use KonradMichalik\Typo3Outbound\Auth\TokenProvider;
use KonradMichalik\Typo3Outbound\Cache\CacheTags;
use KonradMichalik\Typo3Outbound\Enum\PaginationStyle;
use KonradMichalik\Typo3Outbound\Exception\InvalidDefinition;
use KonradMichalik\Typo3Outbound\Value\{Deferred, RawResponse, Setting};
use ReflectionAttribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Reflector;
use Throwable;

use function array_filter;
use function array_keys;
use function count;
use function in_array;
use function is_int;
use function is_string;
use function sprintf;
use function strlen;

/**
 * MetadataFactory.
 *
 * Reads the attributes of connection and resource interfaces and produces the
 * effective metadata of every endpoint. Runs only while the container is built;
 * every inconsistency is reported as InvalidDefinition with the endpoint key.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class MetadataFactory
{
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'QUERY'];
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'QUERY'];
    private const BODYLESS_METHODS = ['GET', 'HEAD'];

    public function __construct(
        private TypeResolver $types = new TypeResolver(),
    ) {}

    /**
     * Returns the interface carrying #[Connection] for a base or resource interface.
     *
     * @param ReflectionClass<object> $interface
     *
     * @return ReflectionClass<object>
     */
    public function baseOf(ReflectionClass $interface): ReflectionClass
    {
        if (!$interface->isInterface()) {
            throw new InvalidDefinition(sprintf('%s must be an interface.', $interface->getName()), 1794700001);
        }
        if (null !== $this->attribute($interface, Connection::class)) {
            if (null !== $this->attribute($interface, Resource::class)) {
                throw new InvalidDefinition(sprintf('%s carries both #[Connection] and #[Resource].', $interface->getName()), 1794700002);
            }

            return $interface;
        }
        if (null === $this->attribute($interface, Resource::class)) {
            throw new InvalidDefinition(sprintf('%s carries neither #[Connection] nor #[Resource].', $interface->getName()), 1794700003);
        }

        $bases = array_values(array_filter(
            $interface->getInterfaces(),
            fn (ReflectionClass $parent): bool => null !== $this->attribute($parent, Connection::class),
        ));
        if (1 !== count($bases)) {
            throw new InvalidDefinition(sprintf('Resource %s must extend exactly one interface with #[Connection], found %d.', $interface->getName(), count($bases)), 1794700004);
        }

        return $bases[0];
    }

    /**
     * @param ReflectionClass<object> $base
     */
    public function connection(ReflectionClass $base): ConnectionMetadata
    {
        $definition = $this->attribute($base, Connection::class);
        if (!$definition instanceof Connection) {
            throw new InvalidDefinition(sprintf('%s carries no #[Connection].', $base->getName()), 1794700005);
        }
        $name = Naming::connectionName($base->getShortName(), $definition->name);
        if (!Naming::isValidConnectionName($name)) {
            throw new InvalidDefinition(sprintf('Connection name "%s" of %s must match [a-z][a-z0-9_]*.', $name, $base->getName()), 1794700006);
        }
        if (is_string($definition->baseUri) && !str_starts_with($definition->baseUri, 'https://') && !$definition->allowPrivateNetwork) {
            throw new InvalidDefinition(sprintf('Connection %s: the base URI must use https. Plain http is only accepted together with allowPrivateNetwork.', $name), 1794700007);
        }

        if ($definition->baseUri instanceof Setting && [] === $definition->allowedHosts) {
            throw new InvalidDefinition(sprintf('Connection %s: a base URI from a setting needs allowedHosts. Otherwise whoever can edit the setting can redirect calls and credentials to any host.', $name), 1794700036);
        }

        $auth = $base->getAttributes(Authentication::class, ReflectionAttribute::IS_INSTANCEOF);
        if (count($auth) > 1) {
            throw new InvalidDefinition(sprintf('Connection %s declares more than one authentication.', $name), 1794700008);
        }
        $authentication = isset($auth[0]) ? $auth[0]->newInstance() : null;
        self::validateAuthentication($name, $authentication, $definition);
        $rateLimit = $this->attribute($base, RateLimit::class);
        if ($rateLimit instanceof RateLimit && ($rateLimit->limit < 1 || !self::isPositiveInterval($rateLimit->per))) {
            throw new InvalidDefinition(sprintf('Connection %s: #[RateLimit] needs a positive limit and an interval like "1 minute".', $name), 1794700009);
        }
        $breaker = $this->attribute($base, CircuitBreaker::class);
        if ($breaker instanceof CircuitBreaker && ($breaker->failureThreshold < 1 || $breaker->cooldown < 1)) {
            throw new InvalidDefinition(sprintf('Connection %s: #[CircuitBreaker] needs a failureThreshold and a cooldown of at least 1.', $name), 1794700234);
        }

        return new ConnectionMetadata(
            name: $name,
            interface: $base->getName(),
            definition: $definition,
            auth: $authentication,
            retry: $this->attribute($base, Retry::class),
            timeout: $this->attribute($base, Timeout::class),
            cache: $this->attribute($base, Cache::class),
            rateLimit: $rateLimit,
            circuitBreaker: $breaker,
        );
    }

    /**
     * Endpoints declared directly on the given interface.
     *
     * @param ReflectionClass<object> $interface
     *
     * @return list<EndpointMetadata>
     */
    public function endpoints(ReflectionClass $interface, ConnectionMetadata $connection): array
    {
        $resource = $this->attribute($interface, Resource::class);
        $prefix = $resource instanceof Resource ? $resource->prefix : '';
        $endpoints = [];
        foreach ($interface->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $interface->getName()) {
                continue;
            }
            $endpoints[] = $this->endpoint($interface, $method, $connection, $prefix);
        }

        return $endpoints;
    }

    public static function joinPath(string $prefix, string $path): string
    {
        $joined = trim($prefix, '/').'/'.trim($path, '/');
        $joined = trim($joined, '/');

        return '' === $joined ? '' : '/'.$joined;
    }

    /**
     * Extracts the type of a PHPDoc tag, keeping generics and shapes intact.
     */
    public static function docType(string $docComment, string $tag): ?string
    {
        if (1 !== preg_match('/@'.$tag.'\s+(.+)$/m', $docComment, $match)) {
            return null;
        }
        $line = trim($match[1]);
        $depth = 0;
        $type = '';
        foreach (str_split($line) as $char) {
            if (0 === $depth && (' ' === $char || '*' === $char) && '' !== $type && !str_ends_with($type, ':') && !str_ends_with($type, ',') && !str_ends_with($type, '|')) {
                break;
            }
            $depth += match ($char) {
                '<', '{', '(' => 1,
                '>', '}', ')' => -1,
                default => 0,
            };
            $type .= $char;
        }

        return '' === trim($type) ? null : trim($type);
    }

    /**
     * @param ReflectionClass<object> $interface
     */
    private function endpoint(ReflectionClass $interface, ReflectionMethod $method, ConnectionMetadata $connection, string $prefix): EndpointMetadata
    {
        $key = Naming::endpointKey($interface->getShortName(), $method->getName());
        $verbs = $method->getAttributes(Endpoint::class, ReflectionAttribute::IS_INSTANCEOF);
        if (1 !== count($verbs)) {
            throw new InvalidDefinition(sprintf('%s needs exactly one verb attribute (#[Get], #[Post], ...), found %d.', $key, count($verbs)), 1794700010);
        }
        $verb = $verbs[0]->newInstance();
        $path = self::joinPath($prefix, $verb->path);
        $parameters = $this->parameters($method, $key, $path, $verb->method());
        $returnType = $this->returnType($interface, $method, $key);
        $paginate = $this->attribute($method, Paginate::class);
        if ((ReturnKind::Iterable === $returnType->kind) !== ($paginate instanceof Paginate)) {
            throw new InvalidDefinition(sprintf('%s: #[Paginate] and an iterable return type belong together.', $key), 1794700011);
        }

        $idempotent = $verb->idempotent ?? (in_array($verb->method(), self::IDEMPOTENT_METHODS, true) || $verb->idempotencyKey);
        $origins = [];
        $retry = $this->effective(Retry::class, $method, $interface, $connection->retry, $connection->interface, $origins);
        $timeout = $this->effective(Timeout::class, $method, $interface, $connection->timeout, $connection->interface, $origins);
        $cache = $this->effective(Cache::class, $method, $interface, $connection->cache, $connection->interface, $origins);
        if ($cache instanceof Cache && null === $this->attribute($method, Cache::class) && !in_array($verb->method(), self::SAFE_METHODS, true)) {
            // An inherited #[Cache] is a default for the reads of the scope; PUT or DELETE are idempotent, but no reads.
            $cache = null;
            unset($origins['Cache']);
        }
        if ($cache instanceof Cache && !$idempotent) {
            throw new InvalidDefinition(sprintf('%s: #[Cache] needs an idempotent endpoint. Mark it with idempotent: true if the request is safe to repeat.', $key), 1794700012);
        }

        self::validateValues($key, $retry, $timeout, $cache, $paginate);

        $invalidates = $this->attribute($method, InvalidatesCache::class);
        $this->validateTags($key, '#[Cache]', $cache->tags ?? [], $method);
        $this->validateTags($key, '#[InvalidatesCache]', $invalidates->tags ?? [], $method);

        $healthCheck = null !== $this->attribute($method, HealthCheck::class);
        if ($healthCheck && $method->getNumberOfRequiredParameters() > 0) {
            throw new InvalidDefinition(sprintf('%s: a #[HealthCheck] endpoint must not have required parameters.', $key), 1794700013);
        }

        return new EndpointMetadata(
            key: $key,
            interface: $interface->getName(),
            method: $method->getName(),
            connection: $connection->name,
            verb: $verb,
            path: $path,
            parameters: $parameters,
            returnType: $returnType,
            idempotent: $idempotent,
            retry: $retry,
            timeout: $timeout,
            cache: $cache,
            mapKeys: $this->attribute($method, MapKeys::class),
            paginate: $paginate,
            onStatus: array_map(static fn (ReflectionAttribute $attribute): OnStatus => $attribute->newInstance(), $method->getAttributes(OnStatus::class)),
            healthCheck: $healthCheck,
            origins: $origins,
            invalidates: $invalidates,
        );
    }

    /**
     * @return list<ParameterMetadata>
     */
    private function parameters(ReflectionMethod $method, string $key, string $path, string $httpMethod): array
    {
        preg_match_all('/\{([A-Za-z_]\w*)\}/', $path, $matches);
        $placeholders = $matches[1];
        $rest = (string) preg_replace('/\{[A-Za-z_]\w*\}/', '', $path);
        if (str_contains($rest, '{') || str_contains($rest, '}')) {
            throw new InvalidDefinition(sprintf('%s: "%s" contains a brace that is not a {name} placeholder. Names consist of letters, digits and underscores.', $key, $path), 1794700235);
        }
        $parameters = [];
        $bound = [];
        $bodies = 0;
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                throw new InvalidDefinition(sprintf('%s: variadic parameter $%s is not supported.', $key, $parameter->getName()), 1794700014);
            }
            if ($parameter->isPassedByReference()) {
                throw new InvalidDefinition(sprintf('%s: parameter $%s is passed by reference, which an API call cannot write back.', $key, $parameter->getName()), 1794700038);
            }
            $metadata = $this->parameter($parameter, $placeholders, $key);
            if (ParameterKind::Path === $metadata->kind) {
                self::validatePath($key, $path, $parameter, $metadata->wireName, $placeholders, $bound);
                $bound[] = $metadata->wireName;
            }
            if (in_array($metadata->kind, [ParameterKind::Body, ParameterKind::Multipart], true)) {
                ++$bodies;
                if (in_array($httpMethod, self::BODYLESS_METHODS, true)) {
                    throw new InvalidDefinition(sprintf('%s: %s requests carry no body.', $key, $httpMethod), 1794700016);
                }
            }
            $parameters[] = $metadata;
        }
        if ($bodies > 1) {
            throw new InvalidDefinition(sprintf('%s: at most one #[Body] or #[Multipart] parameter is allowed.', $key), 1794700017);
        }
        $unbound = array_diff($placeholders, $bound);
        if ([] !== $unbound) {
            throw new InvalidDefinition(sprintf('%s: placeholder {%s} has no parameter.', $key, implode('}, {', $unbound)), 1794700018);
        }

        return $parameters;
    }

    /**
     * @param list<string> $placeholders
     */
    private function parameter(ReflectionParameter $parameter, array $placeholders, string $key): ParameterMetadata
    {
        $name = $parameter->getName();
        $sensitive = null !== $this->attribute($parameter, Sensitive::class);
        $base = ['name' => $name, 'position' => $parameter->getPosition(), 'sensitive' => $sensitive];

        $roles = array_filter([
            $this->attribute($parameter, Path::class),
            $this->attribute($parameter, Query::class),
            $this->attribute($parameter, Body::class),
            $this->attribute($parameter, Header::class),
            $this->attribute($parameter, Multipart::class),
        ]);
        if (count($roles) > 1) {
            throw new InvalidDefinition(sprintf('%s: parameter $%s has more than one role attribute.', $key, $name), 1794700019);
        }
        $role = array_values($roles)[0] ?? null;

        return match (true) {
            $role instanceof Path => new ParameterMetadata(...$base, kind: ParameterKind::Path, wireName: $role->name),
            $role instanceof Query => new ParameterMetadata(...$base, kind: ParameterKind::Query, wireName: $role->name ?? $name, style: $role->style),
            $role instanceof Body => new ParameterMetadata(...$base, kind: ParameterKind::Body, wireName: $name, bodyFormat: $role->format),
            $role instanceof Header => new ParameterMetadata(...$base, kind: ParameterKind::Header, wireName: $role->name),
            $role instanceof Multipart => new ParameterMetadata(...$base, kind: ParameterKind::Multipart, wireName: $name),
            in_array($name, $placeholders, true) => new ParameterMetadata(...$base, kind: ParameterKind::Path, wireName: $name),
            default => throw new InvalidDefinition(sprintf('%s: parameter $%s has no role. Use it as {%s} placeholder or add #[Query], #[Body], #[Header] or #[Multipart].', $key, $name, $name), 1794700020),
        };
    }

    /**
     * @param ReflectionClass<object> $interface
     */
    private function returnType(ReflectionClass $interface, ReflectionMethod $method, string $key): ReturnType
    {
        $type = $method->getReturnType();
        if (null === $type) {
            throw new InvalidDefinition(sprintf('%s needs a return type.', $key), 1794700021);
        }
        if ($type instanceof ReflectionNamedType && 'never' === $type->getName()) {
            throw new InvalidDefinition(sprintf('%s: an endpoint returns, so never is no valid return type.', $key), 1794700039);
        }
        $doc = self::docType((string) $method->getDocComment(), 'return');
        $resolvedDoc = null !== $doc ? $this->types->resolve($doc, $interface) : null;

        if ($type instanceof ReflectionUnionType) {
            $names = array_map(static fn (ReflectionNamedType|ReflectionIntersectionType $t): string => (string) $t, $type->getTypes());

            return new ReturnType(ReturnKind::Mapped, $resolvedDoc ?? implode('|', $names), $type->allowsNull());
        }
        if (!$type instanceof ReflectionNamedType) {
            throw new InvalidDefinition(sprintf('%s: intersection return types are not supported.', $key), 1794700022);
        }

        $name = $type->getName();
        $nullable = $type->allowsNull() && 'mixed' !== $name && 'null' !== $name;

        return match (true) {
            'void' === $name => new ReturnType(ReturnKind::Void, 'void'),
            RawResponse::class === $name => new ReturnType(ReturnKind::Raw, RawResponse::class),
            Deferred::class === $name => new ReturnType(ReturnKind::Deferred, self::genericArgument($resolvedDoc, $key, 'Deferred<T>')),
            in_array($name, ['iterable', 'Generator', 'Traversable', 'Iterator'], true) => new ReturnType(ReturnKind::Iterable, self::genericArgument($resolvedDoc, $key, 'iterable<T>', 'Generator' === $name)),
            'array' === $name => new ReturnType(ReturnKind::Mapped, $resolvedDoc ?? 'array', $nullable),
            default => new ReturnType(ReturnKind::Mapped, $type->isBuiltin() ? $name : ltrim($name, '\\'), $nullable),
        };
    }

    /**
     * Every placeholder must be a non-nullable int, string or backed enum
     * parameter, and the static part must be a valid TYPO3 cache tag.
     *
     * @param list<string> $templates
     */
    private function validateTags(string $key, string $attribute, array $templates, ReflectionMethod $method): void
    {
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }
        foreach ($templates as $template) {
            if (1 !== preg_match(CacheTags::PATTERN, CacheTags::staticPart($template))) {
                throw new InvalidDefinition(sprintf('%s: %s tag "%s" may only contain letters, digits, _, -, %% and & besides {placeholders}.', $key, $attribute, $template), 1794700031);
            }
            foreach (CacheTags::placeholders($template) as $name) {
                $parameter = $parameters[$name] ?? throw new InvalidDefinition(sprintf('%s: %s tag "%s" uses {%s}, but the method has no such parameter.', $key, $attribute, $template, $name), 1794700032);
                $type = $parameter->getType();
                $valid = $type instanceof ReflectionNamedType
                    && !$type->allowsNull()
                    && (in_array($type->getName(), ['int', 'string'], true) || is_subclass_of($type->getName(), BackedEnum::class));
                if (!$valid) {
                    throw new InvalidDefinition(sprintf('%s: %s tag placeholder {%s} needs a non-nullable int, string or backed enum parameter.', $key, $attribute, $name), 1794700033);
                }
            }
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T>         $attribute
     * @param ReflectionClass<object> $interface
     * @param T|null                  $connectionValue
     * @param array<string, string>   $origins
     *
     * @return T|null
     */
    private function effective(string $attribute, ReflectionMethod $method, ReflectionClass $interface, ?object $connectionValue, string $connectionInterface, array &$origins): ?object
    {
        $short = substr($attribute, (int) strrpos($attribute, '\\') + 1);
        foreach ([[$method, $interface->getShortName().'::'.$method->getName()], [$interface, $interface->getName()]] as [$reflector, $origin]) {
            $value = $this->attribute($reflector, $attribute);
            if (null !== $value) {
                $origins[$short] = $origin;

                return $value;
            }
        }
        if (null !== $connectionValue) {
            $origins[$short] = $connectionInterface;
        }

        return $connectionValue;
    }

    /**
     * @template T of object
     *
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionParameter $reflector
     * @param class-string<T>                                              $attribute
     *
     * @return T|null
     */
    private function attribute(Reflector $reflector, string $attribute): ?object
    {
        $attributes = $reflector->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);

        return isset($attributes[0]) ? $attributes[0]->newInstance() : null;
    }

    /**
     * @param list<string> $placeholders
     * @param list<string> $bound
     */
    private static function validatePath(string $key, string $path, ReflectionParameter $parameter, string $name, array $placeholders, array $bound): void
    {
        if (!in_array($name, $placeholders, true)) {
            throw new InvalidDefinition(sprintf('%s: #[Path] "%s" has no placeholder in "%s".', $key, $name, $path), 1794700015);
        }
        if (in_array($name, $bound, true)) {
            throw new InvalidDefinition(sprintf('%s: placeholder {%s} is bound by more than one parameter.', $key, $name), 1794700042);
        }
        // Untyped and mixed parameters allow null as well, but are not declared nullable.
        $type = $parameter->getType();
        $nullable = $type instanceof ReflectionType && $type->allowsNull() && !($type instanceof ReflectionNamedType && 'mixed' === $type->getName());
        if ($nullable || ($parameter->isDefaultValueAvailable() && null === $parameter->getDefaultValue())) {
            throw new InvalidDefinition(sprintf('%s: path parameter $%s must not be nullable, an empty segment would change the URL.', $key, $parameter->getName()), 1794700043);
        }
    }

    private static function isPositiveInterval(string $interval): bool
    {
        try {
            $parsed = @DateInterval::createFromDateString($interval);
        } catch (Throwable) {
            return false;
        }

        return $parsed instanceof DateInterval && (new DateTimeImmutable('@0'))->add($parsed)->getTimestamp() > 0;
    }

    /**
     * Values that would not fail but misbehave: a lifetime of 0 means "never expires" in the
     * database cache backend, a page size of 0 never advances.
     */
    private static function validateAuthentication(string $name, ?Authentication $authentication, Connection $definition): void
    {
        if ($authentication instanceof OAuth2ClientCredentials && $authentication->tokenUrl instanceof Setting && [] === $definition->allowedHosts) {
            throw new InvalidDefinition(sprintf('Connection %s: a token URL from a setting needs allowedHosts that include its host.', $name), 1794700037);
        }
        if ($authentication instanceof TokenLogin) {
            self::validateTokenLogin($name, $authentication, $definition);
        }
    }

    private static function validateTokenLogin(string $name, TokenLogin $login, Connection $definition): void
    {
        $valid = [] !== $login->body;
        foreach (array_keys($login->body) as $field) {
            $valid = $valid && is_string($field) && '' !== $field;
        }
        if (!$valid) {
            throw new InvalidDefinition(sprintf('Connection %s: #[TokenLogin] needs a body of named fields.', $name), 1794700237);
        }
        if ('' === $login->tokenField) {
            throw new InvalidDefinition(sprintf('Connection %s: #[TokenLogin] needs a tokenField.', $name), 1794700238);
        }
        if ($login->ttl <= TokenProvider::EXPIRY_MARGIN) {
            throw new InvalidDefinition(sprintf('Connection %s: #[TokenLogin] needs a ttl above %d seconds, the token is renewed that long before it expires.', $name, TokenProvider::EXPIRY_MARGIN), 1794700239);
        }
        if (is_string($login->url) && !str_starts_with($login->url, '/') && !str_starts_with($login->url, 'https://')) {
            throw new InvalidDefinition(sprintf('Connection %s: the login URL must be a path or use https.', $name), 1794700240);
        }
        if ($login->url instanceof Setting && [] === $definition->allowedHosts) {
            throw new InvalidDefinition(sprintf('Connection %s: a login URL from a setting needs allowedHosts that include its host.', $name), 1794700241);
        }
    }

    private static function validateValues(string $key, ?Retry $retry, ?Timeout $timeout, ?Cache $cache, ?Paginate $paginate): void
    {
        if ($cache instanceof Cache && ($cache->lifetime < 1 || $cache->staleOnError < 0)) {
            throw new InvalidDefinition(sprintf('%s: #[Cache] needs a lifetime of at least 1 second and a staleOnError of 0 or more.', $key), 1794700230);
        }
        if ($timeout instanceof Timeout && $timeout->seconds <= 0) {
            throw new InvalidDefinition(sprintf('%s: #[Timeout] needs a positive number of seconds.', $key), 1794700231);
        }
        if ($retry instanceof Retry) {
            $badStatus = array_filter($retry->statusCodes, static fn (mixed $status): bool => !is_int($status) || $status < 100 || $status > 599);
            if ($retry->maxRetries < 0 || $retry->delayMs < 0 || $retry->maxDelayMs < 0 || $retry->multiplier < 1.0 || [] !== $badStatus) {
                throw new InvalidDefinition(sprintf('%s: #[Retry] needs maxRetries, delayMs and maxDelayMs of 0 or more, a multiplier of at least 1 and HTTP status codes between 100 and 599.', $key), 1794700232);
            }
        }
        if ($paginate instanceof Paginate) {
            if ($paginate->size < 1 || $paginate->maxPages < 1) {
                throw new InvalidDefinition(sprintf('%s: #[Paginate] needs a size and maxPages of at least 1.', $key), 1794700233);
            }
            if (PaginationStyle::Cursor === $paginate->style && (null === $paginate->cursorPath || '' === $paginate->cursorPath)) {
                throw new InvalidDefinition(sprintf('%s: #[Paginate] with the Cursor style needs a cursorPath.', $key), 1794700236);
            }
        }
    }

    /**
     * The item type is the last type argument, except for `Generator<TKey, TValue, TSend, TReturn>`
     * where it is the second one.
     */
    private static function genericArgument(?string $doc, string $key, string $expected, bool $generator = false): string
    {
        if (null === $doc || 1 !== preg_match('/<(.+)>$/s', $doc, $match)) {
            throw new InvalidDefinition(sprintf('%s: declare the item type in PHPDoc as @return %s.', $key, $expected), 1794700023);
        }
        $arguments = [];
        $depth = 0;
        $start = 0;
        $inner = $match[1];
        $length = strlen($inner);
        for ($i = 0; $i < $length; ++$i) {
            $depth += match ($inner[$i]) {
                '<', '{', '(' => 1,
                '>', '}', ')' => -1,
                default => 0,
            };
            if (0 === $depth && ',' === $inner[$i]) {
                $arguments[] = trim(substr($inner, $start, $i - $start));
                $start = $i + 1;
            }
        }
        $arguments[] = trim(substr($inner, $start));

        return $generator && count($arguments) >= 2 ? $arguments[1] : $arguments[count($arguments) - 1];
    }
}
