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

namespace KonradMichalik\Typo3Outbound\Http;

use KonradMichalik\Typo3Outbound\Auth\Credentials;
use KonradMichalik\Typo3Outbound\Context\{CallContext, ValueResolver};
use KonradMichalik\Typo3Outbound\Enum\{BodyFormat, QueryStyle};
use KonradMichalik\Typo3Outbound\Exception\BlockedRequest;
use KonradMichalik\Typo3Outbound\Mapping\Normalizer;
use KonradMichalik\Typo3Outbound\Metadata\{ConnectionMetadata, EndpointMetadata, ParameterKind};
use KonradMichalik\Typo3Outbound\Value\{Env, FilePart, Secret, SiteSetting};
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

use function array_key_exists;
use function http_build_query;
use function is_array;
use function is_scalar;
use function json_encode;
use function rawurlencode;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * RequestBuilder.
 *
 * Builds the request of an endpoint call. Path placeholders are always
 * percent-encoded (a value can never add path segments or a query), every URL
 * passes the HostGuard, and attribute values are never interpreted as code.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class RequestBuilder
{
    public function __construct(
        private ValueResolver $values,
        private CallContext $context,
    ) {}

    /**
     * @param array<string, mixed>     $arguments  Named call arguments
     * @param array<array-key, scalar> $extraQuery Additional query parameters, e.g. for pagination
     * @param string|null              $url        Absolute URL replacing path and query, e.g. a next-page link
     */
    public function build(EndpointMetadata $endpoint, ConnectionMetadata $connection, array $arguments, Credentials $credentials, array $extraQuery = [], ?string $url = null): RequestSpec
    {
        $definition = $connection->definition;
        $normalizer = new Normalizer($definition->dateFormat, $definition->booleansAsStrings);
        $baseUri = rtrim($this->values->resolve($definition->baseUri), '/');

        if (null === $url) {
            $query = [];
            foreach ($endpoint->parametersOf(ParameterKind::Query) as $parameter) {
                $query[] = [$parameter->wireName, $normalizer->forQuery($arguments[$parameter->name] ?? null), $parameter->style];
            }
            foreach ([...$extraQuery, ...$credentials->query] as $name => $value) {
                $query[] = [(string) $name, (string) $value, QueryStyle::Form];
            }
            $queryString = QuerySerializer::build($query);
            $url = $baseUri.$this->path($endpoint, $arguments, $normalizer).('' !== $queryString ? '?'.$queryString : '');
        } elseif ([] !== $credentials->query) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($credentials->query);
        }
        HostGuard::check($url, $definition->baseUri instanceof SiteSetting ? null : $baseUri, $definition->allowedHosts, $definition->allowPrivateNetwork, $endpoint->key);

        $headers = ['Accept' => $definition->format->accept()];
        $secretHeaders = [];
        foreach ($definition->headers as $name => $value) {
            $headers[$name] = $this->values->resolve($value);
            if ($value instanceof Secret || $value instanceof Env) {
                $secretHeaders[] = $name;
            }
        }
        if ($definition->sendLanguage && null !== ($language = $this->context->language())) {
            $headers['Accept-Language'] = $language;
        }
        foreach ($endpoint->parametersOf(ParameterKind::Header) as $parameter) {
            $value = $normalizer->forQuery($arguments[$parameter->name] ?? null);
            if (is_scalar($value) && '' !== (string) $value) {
                $headers[$parameter->wireName] = (string) $value;
            }
        }
        $headers = [...$headers, ...$credentials->headers];

        [$body, $bodyHeaders] = $this->body($endpoint, $connection, $arguments, $normalizer);

        return new RequestSpec(
            $endpoint->httpMethod(),
            $url,
            [...$headers, ...$bodyHeaders],
            $body,
            [...$credentials->sensitiveKeys, ...$secretHeaders, ...$this->sensitiveNames($endpoint)],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function path(EndpointMetadata $endpoint, array $arguments, Normalizer $normalizer): string
    {
        $path = $endpoint->path;
        foreach ($endpoint->parametersOf(ParameterKind::Path) as $parameter) {
            $value = $normalizer->forQuery($arguments[$parameter->name] ?? null);
            if (!is_scalar($value) || '' === (string) $value) {
                throw new BlockedRequest(sprintf('%s: path parameter $%s must not be empty.', $endpoint->key, $parameter->name), 1794700120, $endpoint->key);
            }
            if ('.' === (string) $value || '..' === (string) $value) {
                throw new BlockedRequest(sprintf('%s: path parameter $%s must not be a dot segment.', $endpoint->key, $parameter->name), 1794700123, $endpoint->key);
            }
            $path = str_replace('{'.$parameter->wireName.'}', rawurlencode((string) $value), $path);
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{0: string|iterable<string>|null, 1: array<string, string>}
     */
    private function body(EndpointMetadata $endpoint, ConnectionMetadata $connection, array $arguments, Normalizer $normalizer): array
    {
        foreach ($endpoint->parameters as $parameter) {
            if (!array_key_exists($parameter->name, $arguments) || null === $arguments[$parameter->name]) {
                continue;
            }
            $value = $arguments[$parameter->name];
            if (ParameterKind::Multipart === $parameter->kind) {
                return $this->multipart($endpoint, $value);
            }
            if (ParameterKind::Body !== $parameter->kind) {
                continue;
            }
            if (BodyFormat::Form === $parameter->bodyFormat) {
                $form = $normalizer->forQuery($value);

                return [http_build_query(is_array($form) ? $form : [$parameter->name => $form]), ['Content-Type' => 'application/x-www-form-urlencoded']];
            }
            $payload = $normalizer->forBody($value);
            if (is_array($payload)) {
                $payload = $connection->definition->format->encodeBody($payload);
            }

            return [json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), ['Content-Type' => $connection->definition->format->contentType()]];
        }

        return [null, []];
    }

    /**
     * @return array{0: iterable<string>, 1: array<string, string>}
     */
    private function multipart(EndpointMetadata $endpoint, mixed $value): array
    {
        if (!is_array($value)) {
            throw new BlockedRequest(sprintf('%s: a #[Multipart] argument must be an array of fields.', $endpoint->key), 1794700121, $endpoint->key);
        }
        $fields = [];
        foreach ($value as $name => $field) {
            $fields[(string) $name] = match (true) {
                $field instanceof FilePart && null !== $field->path => DataPart::fromPath($field->path, $field->filename, $field->contentType),
                $field instanceof FilePart => new DataPart((string) $field->contents, $field->filename, $field->contentType),
                is_scalar($field) => (string) $field,
                default => throw new BlockedRequest(sprintf('%s: multipart field "%s" must be a string or FilePart.', $endpoint->key, $name), 1794700122, $endpoint->key),
            };
        }
        $part = new FormDataPart($fields);
        $headers = [];
        foreach ($part->getPreparedHeaders()->all() as $header) {
            if ($header instanceof HeaderInterface) {
                $headers[$header->getName()] = $header->getBodyAsString();
            }
        }
        /** @var iterable<string> $body */
        $body = $part->bodyToIterable();

        return [$body, $headers];
    }

    /**
     * @return list<string>
     */
    private function sensitiveNames(EndpointMetadata $endpoint): array
    {
        $names = [];
        foreach ($endpoint->parameters as $parameter) {
            if ($parameter->sensitive) {
                $names[] = $parameter->wireName;
            }
        }

        return $names;
    }
}
