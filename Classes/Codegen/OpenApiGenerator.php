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

namespace KonradMichalik\Typo3Outbound\Codegen;

use function array_filter;
use function array_map;
use function array_pop;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function ltrim;
use function preg_replace;
use function sort;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function trim;
use function usort;
use function var_export;

/**
 * OpenApiGenerator.
 *
 * Generates a base interface (#[Connection] from `servers` and
 * `securitySchemes`), one resource interface per tag and DTOs and enums from
 * `components/schemas`. Credentials are always generated as Secret references,
 * never as values. Output is normal project code to be reviewed and committed.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class OpenApiGenerator
{
    /** @var array<string, string> */
    private array $files = [];

    /** @var array<string, mixed> */
    private array $spec = [];

    private string $namespace = '';
    private string $connection = '';

    /**
     * @param array<string, mixed> $spec
     *
     * @return array<string, string> Relative file path => content
     */
    public function generate(array $spec, string $namespace, string $baseName): array
    {
        $this->files = [];
        $this->spec = $spec;
        $this->namespace = trim($namespace, '\\');
        $base = Names::studly($baseName).'Api';
        $this->connection = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', Names::studly($baseName)));

        foreach ((array) ($spec['components']['schemas'] ?? []) as $name => $schema) {
            if (is_array($schema)) {
                $this->schema(Names::className((string) $name), $schema);
            }
        }
        $this->files['Api/'.$base.'.php'] = $this->base($base);
        foreach ($this->operationsByTag() as $tag => $operations) {
            $resource = Names::studly($tag).'Api';
            $this->files['Api/'.$resource.'.php'] = $this->resource($resource, $base, $operations);
        }

        return $this->files;
    }

    private function base(string $base): string
    {
        $servers = (array) ($this->spec['servers'] ?? []);
        $url = is_array($servers[0] ?? null) && is_string($servers[0]['url'] ?? null) ? $servers[0]['url'] : 'https://api.example.com';
        $attributes = [sprintf('#[Outbound\\Connection(baseUri: %s)]', var_export($url, true))];
        foreach ((array) ($this->spec['components']['securitySchemes'] ?? []) as $name => $scheme) {
            if (!is_array($scheme)) {
                continue;
            }
            $key = $this->connection.'.'.Names::camel((string) $name);
            $attribute = match (true) {
                'apiKey' === ($scheme['type'] ?? null) && in_array($scheme['in'] ?? null, ['header', 'query'], true) => sprintf(
                    '#[Outbound\\Auth\\ApiKey(name: %s, value: new Secret(%s)%s)]',
                    var_export((string) ($scheme['name'] ?? 'api_key'), true),
                    var_export($key, true),
                    'query' === $scheme['in'] ? ', in: ApiKeyLocation::Query' : '',
                ),
                'http' === ($scheme['type'] ?? null) && 'bearer' === strtolower((string) ($scheme['scheme'] ?? '')) => sprintf('#[Outbound\\Auth\\Bearer(token: new Secret(%s))]', var_export($key, true)),
                'oauth2' === ($scheme['type'] ?? null) && is_string($scheme['flows']['clientCredentials']['tokenUrl'] ?? null) => sprintf(
                    '#[Outbound\\Auth\\OAuth2ClientCredentials(tokenUrl: %s, clientId: new Secret(%s), clientSecret: new Secret(%s))]',
                    var_export($scheme['flows']['clientCredentials']['tokenUrl'], true),
                    var_export($key.'ClientId', true),
                    var_export($key.'ClientSecret', true),
                ),
                default => sprintf('// Security scheme "%s" (%s) is not generated; add an authentication attribute if needed.', self::slug($name), self::slug((string) ($scheme['type'] ?? 'unknown'))),
            };
            $attributes[] = $attribute;
        }
        // A connection carries at most one authentication.
        $authSeen = false;
        $attributes = array_values(array_filter($attributes, static function (string $line) use (&$authSeen): bool {
            if (!str_starts_with($line, '#[Outbound\\Auth')) {
                return true;
            }
            $seen = $authSeen;
            $authSeen = true;

            return !$seen;
        }));
        $title = ClassFile::docLine(is_string($this->spec['info']['title'] ?? null) ? $this->spec['info']['title'] : $base);

        return ClassFile::file($this->namespace.'\\Api', sprintf("/**\n * %s, generated by outbound:generate.\n */\n%s\ninterface %s {}\n", $title, implode("\n", $attributes), $base), [
            'KonradMichalik\\Typo3Outbound\\Attribute as Outbound',
            \KonradMichalik\Typo3Outbound\Enum\ApiKeyLocation::class,
            \KonradMichalik\Typo3Outbound\Value\Secret::class,
        ]);
    }

    /**
     * @return array<string, list<array{path: string, method: string, operation: array<string, mixed>}>>
     */
    private function operationsByTag(): array
    {
        $tags = [];
        foreach ((array) ($this->spec['paths'] ?? []) as $path => $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                $operation = $item[$method] ?? null;
                if (!is_array($operation)) {
                    continue;
                }
                $operation['parameters'] = [...(array) ($item['parameters'] ?? []), ...(array) ($operation['parameters'] ?? [])];
                $tag = is_string($operation['tags'][0] ?? null) ? $operation['tags'][0] : 'default';
                $tags[$tag][] = ['path' => (string) $path, 'method' => $method, 'operation' => $operation];
            }
        }

        return $tags;
    }

    /**
     * @param list<array{path: string, method: string, operation: array<string, mixed>}> $operations
     */
    private function resource(string $resource, string $base, array $operations): string
    {
        $prefix = $this->commonPrefix(array_map(static fn (array $operation): string => $operation['path'], $operations));
        $methods = [];
        $uses = ['KonradMichalik\\Typo3Outbound\\Attribute as Outbound'];
        foreach ($operations as $operation) {
            $methods[] = $this->method($operation, $prefix, $uses);
        }
        sort($uses);

        return ClassFile::file($this->namespace.'\\Api', sprintf("#[Outbound\\Resource(%s)]\ninterface %s extends %s\n{\n%s}\n", var_export($prefix, true), $resource, $base, implode("\n", $methods)), array_values(array_unique($uses)));
    }

    /**
     * @param array{path: string, method: string, operation: array<string, mixed>} $operation
     * @param list<string>                                                         $uses
     */
    private function method(array $operation, string $prefix, array &$uses): string
    {
        $definition = $operation['operation'];
        $name = Names::camel(is_string($definition['operationId'] ?? null) ? $definition['operationId'] : $operation['method'].' '.$operation['path']);
        $path = substr($operation['path'], strlen($prefix));
        $parameters = [];
        $docs = [];
        foreach ((array) $definition['parameters'] as $parameter) {
            if (!is_array($parameter) || !is_string($parameter['name'] ?? null) || !in_array($parameter['in'] ?? null, ['path', 'query', 'header'], true)) {
                continue;
            }
            [$type, $doc] = $this->phpType((array) ($parameter['schema'] ?? []), Names::studly($name).Names::studly($parameter['name']), $uses);
            $variable = Names::camel($parameter['name']);
            $required = 'path' === $parameter['in'] || true === ($parameter['required'] ?? false);
            $attribute = match ($parameter['in']) {
                'path' => $variable === $parameter['name'] ? '' : sprintf('#[Outbound\\Path(%s)] ', var_export($parameter['name'], true)),
                'header' => sprintf('#[Outbound\\Header(%s)] ', var_export($parameter['name'], true)),
                default => self::queryAttribute($variable !== $parameter['name'] ? 'name: '.var_export($parameter['name'], true) : '', $this->style($parameter)),
            };
            if (null !== $doc) {
                $docs[] = sprintf('     * @param %s $%s', $doc, $variable);
            }
            $typeCode = $required ? $type : $this->nullable($type);
            $parameters[] = ['code' => $attribute.$typeCode.' $'.$variable.($required ? '' : ' = null'), 'required' => $required];
        }

        $body = $this->requestBody($definition, Names::studly($name), $uses);
        if (null !== $body) {
            $parameters[] = ['code' => $body['code'], 'required' => true];
            if (null !== $body['doc']) {
                $docs[] = $body['doc'];
            }
        }
        usort($parameters, static fn (array $a, array $b): int => (int) $b['required'] <=> (int) $a['required']);

        [$returnType, $returnDoc] = $this->returnType($definition, Names::studly($name), $uses);
        if (null !== $returnDoc) {
            $docs[] = sprintf('     * @return %s', $returnDoc);
        }
        $summary = is_string($definition['summary'] ?? null) ? $definition['summary'] : null;
        $docBlock = null !== $summary || [] !== $docs ? "    /**\n".(null !== $summary ? '     * '.ClassFile::docLine($summary)."\n".([] !== $docs ? "     *\n" : '') : '').implode("\n", $docs).([] !== $docs ? "\n" : '')."     */\n" : '';
        $verb = Names::studly($operation['method']);

        return sprintf(
            "%s    #[Outbound\\%s%s]\n    public function %s(%s): %s;\n",
            $docBlock,
            $verb,
            '' === $path ? '' : '('.var_export($path, true).')',
            $name,
            implode(', ', array_map(static fn (array $parameter): string => trim($parameter['code']), $parameters)),
            $returnType,
        );
    }

    /**
     * Keys and types from a spec end up in a single-line comment: anything beyond a plain token could end it.
     */
    private static function slug(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_.-]/', '_', $value);
    }

    private static function queryAttribute(string $name, string $style): string
    {
        $arguments = implode(', ', array_filter([$name, $style], static fn (string $part): bool => '' !== $part));

        return '' === $arguments ? '#[Outbound\\Query] ' : '#[Outbound\\Query('.$arguments.')] ';
    }

    private function nullable(string $type): string
    {
        return str_starts_with($type, '?') || 'mixed' === $type ? $type : '?'.$type;
    }

    /**
     * @param array<string, mixed> $parameter
     */
    private function style(array $parameter): string
    {
        $style = $parameter['style'] ?? 'form';
        $explode = $parameter['explode'] ?? ('form' === $style);

        return match (true) {
            'deepObject' === $style => 'style: QueryStyle::DeepObject',
            'pipeDelimited' === $style => 'style: QueryStyle::PipeDelimited',
            'form' === $style && false === $explode => 'style: QueryStyle::CommaDelimited',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $definition
     * @param list<string>         $uses
     *
     * @return array{code: string, doc: ?string}|null
     */
    private function requestBody(array $definition, string $context, array &$uses): ?array
    {
        $body = $this->resolve($definition['requestBody'] ?? null);
        if (!is_array($body) || !is_array($body['content'] ?? null)) {
            return null;
        }
        foreach (['application/json' => '', 'application/x-www-form-urlencoded' => 'format: BodyFormat::Form', 'multipart/form-data' => 'multipart'] as $type => $style) {
            if (!is_array($body['content'][$type] ?? null)) {
                continue;
            }
            if ('multipart' === $style) {
                return ['code' => '#[Outbound\\Multipart] array $parts', 'doc' => '     * @param array<string, string|\\KonradMichalik\\Typo3Outbound\\Value\\FilePart> $parts'];
            }
            [$phpType, $doc] = $this->phpType((array) ($body['content'][$type]['schema'] ?? []), $context.'Body', $uses);
            if ('' !== $style) {
                $uses[] = \KonradMichalik\Typo3Outbound\Enum\BodyFormat::class;
            }
            $variable = Names::camel(ltrim(str_replace($this->namespace.'\\Dto\\', '', $phpType), '\\?')) ?: 'body';

            return ['code' => sprintf('#[Outbound\\Body%s] %s $%s', '' !== $style ? '('.$style.')' : '', $phpType, $variable), 'doc' => null !== $doc ? sprintf('     * @param %s $%s', $doc, $variable) : null];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $definition
     * @param list<string>         $uses
     *
     * @return array{0: string, 1: ?string}
     */
    private function returnType(array $definition, string $context, array &$uses): array
    {
        foreach (['200', '201', '202'] as $status) {
            $response = $this->resolve($definition['responses'][$status] ?? null);
            if (!is_array($response)) {
                continue;
            }
            $content = (array) ($response['content'] ?? []);
            $schema = $content['application/json']['schema'] ?? null;
            if (is_array($schema)) {
                return $this->phpType($schema, $context.'Result', $uses);
            }

            return [] === $content ? ['void', null] : ['string', null];
        }

        return ['void', null];
    }

    /**
     * @param array<string, mixed> $schema
     * @param list<string>         $uses
     *
     * @return array{0: string, 1: ?string} PHP type and PHPDoc type
     */
    private function phpType(array $schema, string $context, array &$uses): array
    {
        if (is_string($schema['$ref'] ?? null)) {
            $class = Names::className(substr($schema['$ref'], (int) strrpos($schema['$ref'], '/') + 1));
            $uses[] = $this->namespace.'\\Dto\\'.$class;

            return [$class, null];
        }
        $type = $schema['type'] ?? 'string';
        if ('array' === $type) {
            [$item] = $this->phpType((array) ($schema['items'] ?? []), $context.'Item', $uses);

            return ['array', sprintf('list<%s>', $item)];
        }
        if ('object' === $type) {
            $additional = $schema['additionalProperties'] ?? null;
            if (is_array($additional)) {
                [$value] = $this->phpType($additional, $context.'Value', $uses);

                return ['array', sprintf('array<string, %s>', $value)];
            }
            if (is_array($schema['properties'] ?? null)) {
                $this->schema($context, $schema);
                $uses[] = $this->namespace.'\\Dto\\'.$context;

                return [$context, null];
            }

            return ['array', 'array<string, string>'];
        }
        if ('string' === $type && is_array($schema['enum'] ?? null)) {
            $this->files['Dto/'.$context.'.php'] = ClassFile::enum($this->namespace.'\\Dto', $context, array_values(array_map(strval(...), $schema['enum'])));
            $uses[] = $this->namespace.'\\Dto\\'.$context;

            return [$context, null];
        }

        return [match (true) {
            'integer' === $type => 'int',
            'number' === $type => 'float',
            'boolean' === $type => 'bool',
            'string' === $type && in_array($schema['format'] ?? null, ['date-time', 'date'], true) => '\\DateTimeImmutable',
            default => 'string',
        }, null];
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function schema(string $class, array $schema): void
    {
        if ('string' === ($schema['type'] ?? null) && is_array($schema['enum'] ?? null)) {
            $this->files['Dto/'.$class.'.php'] = ClassFile::enum($this->namespace.'\\Dto', $class, array_values(array_map(strval(...), $schema['enum'])));

            return;
        }
        $required = (array) ($schema['required'] ?? []);
        $properties = [];
        $uses = [];
        foreach ((array) ($schema['properties'] ?? []) as $name => $property) {
            if (!is_array($property)) {
                continue;
            }
            [$type, $doc] = $this->phpType($property, $class.Names::studly((string) $name), $uses);
            $properties[] = ['name' => Names::property((string) $name, true), 'type' => $type, 'doc' => $doc, 'optional' => !in_array($name, $required, true)];
        }
        $this->files['Dto/'.$class.'.php'] = ClassFile::dto($this->namespace.'\\Dto', $class, $properties, is_string($schema['description'] ?? null) ? $schema['description'] : null);
    }

    private function resolve(mixed $value): mixed
    {
        if (!is_array($value) || !is_string($value['$ref'] ?? null) || !str_starts_with($value['$ref'], '#/')) {
            return $value;
        }
        $node = $this->spec;
        foreach (explode('/', substr($value['$ref'], 2)) as $segment) {
            if (!is_array($node) || !isset($node[$segment])) {
                return null;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * @param list<string> $paths
     */
    private function commonPrefix(array $paths): string
    {
        $segments = null;
        foreach ($paths as $path) {
            $parts = array_values(array_filter(explode('/', $path), static fn (string $part): bool => '' !== $part));
            if (null === $segments) {
                $segments = $parts;
                continue;
            }
            $common = [];
            foreach ($segments as $index => $segment) {
                if (($parts[$index] ?? null) !== $segment || str_starts_with($segment, '{')) {
                    break;
                }
                $common[] = $segment;
            }
            $segments = $common;
        }
        $segments = array_values(array_filter($segments ?? [], static fn (string $segment): bool => !str_starts_with($segment, '{')));
        if (1 === count($paths) && [] !== $segments) {
            array_pop($segments);
        }

        return [] === $segments ? '' : '/'.implode('/', $segments);
    }
}
