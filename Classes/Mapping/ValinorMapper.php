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

namespace KonradMichalik\Typo3Outbound\Mapping;

use CuyZ\Valinor\Cache\Cache;
use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use KonradMichalik\Typo3Outbound\Attribute\MapKeys;
use KonradMichalik\Typo3Outbound\Enum\KeyCase;
use KonradMichalik\Typo3Outbound\Exception\ApiMappingFailed;
use KonradMichalik\Typo3Outbound\Metadata\ConnectionMetadata;

use function array_slice;
use function count;
use function implode;
use function is_array;
use function sprintf;
use function str_starts_with;

/**
 * ValinorMapper.
 *
 * Maps decoded responses with Valinor. Superfluous keys are allowed, so a new
 * API field never breaks an integration; missing nullable values become null.
 * Wrong types and missing required values fail with ApiMappingFailed.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ValinorMapper implements ResponseMapper
{
    /** @var array<string, MapperBuilder> */
    private array $builders = [];

    /**
     * @param Cache<mixed>|null $cache
     */
    public function __construct(
        private readonly ?Cache $cache = null,
    ) {}

    public function map(string $signature, mixed $data, ConnectionMetadata $connection, ?MapKeys $mapKeys, string $endpoint): mixed
    {
        $source = $data;
        if (is_array($data)) {
            $source = Source::array($data);
            if (KeyCase::Camel === $connection->definition->keyCase) {
                $source = $source->camelCaseKeys();
            }
            if (null !== $mapKeys) {
                $source = $source->map($mapKeys->map);
            }
        }

        try {
            return $this->builder($connection->definition->dateFormat)->mapper()->map($signature, $source);
        } catch (MappingError $error) {
            $messages = [];
            foreach ($error->messages()->errors() as $message) {
                $messages[] = $message->path().': '.$message;
            }

            throw new ApiMappingFailed(sprintf('%s: the response does not match %s (%s%s).', $endpoint, $signature, implode('; ', array_slice($messages, 0, 3)), count($messages) > 3 ? '; ...' : ''), 1794700110, $endpoint, null, null, $error);
        } catch (\Throwable $exception) {
            if (!str_starts_with($exception::class, 'CuyZ\\Valinor\\')) {
                throw $exception;
            }

            throw new ApiMappingFailed(sprintf('%s: %s cannot be mapped: %s Declare list and array types in PHPDoc, e.g. @param list<string> $tags.', $endpoint, $signature, $exception->getMessage()), 1794700111, $endpoint, null, null, $exception);
        }
    }

    public function warmup(string ...$signatures): void
    {
        $this->builder(\DateTimeInterface::ATOM)->warmupCacheFor(...$signatures);
    }

    private function builder(string $dateFormat): MapperBuilder
    {
        if (isset($this->builders[$dateFormat])) {
            return $this->builders[$dateFormat];
        }
        $builder = (new MapperBuilder())
            ->allowSuperfluousKeys()
            ->allowUndefinedValues()
            ->supportDateFormats('' !== $dateFormat ? $dateFormat : \DateTimeInterface::ATOM, \DateTimeInterface::ATOM, 'Y-m-d\TH:i:s.uP', 'Y-m-d', 'U');
        if (null !== $this->cache) {
            $builder = $builder->withCache($this->cache);
        }

        return $this->builders[$dateFormat] = $builder;
    }
}
