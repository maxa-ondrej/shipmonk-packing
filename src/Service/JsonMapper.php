<?php

declare(strict_types=1);

namespace App\Service;

use JsonException;
use JsonMapper\Exception\TypeError;
use JsonMapper\JsonMapperInterface;
use RuntimeException;
use Throwable;

readonly class JsonMapper {
    public function __construct(
        private JsonMapperInterface $jsonDecoder,
    ) {}

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T
     *
     * @throws JsonException
     */
    public function decode(string $json, string $className): object {
        try {
            return $this->jsonDecoder->mapToClassFromString($json, $className);
        } catch (TypeError $exception) {
            throw new RuntimeException('Provided class does not exist', previous: $exception);
        } catch (Throwable $exception) {
            throw new JsonException('Failed to decode json', previous: $exception);
        }
    }

    /**
     * @throws JsonException
     */
    public function encode(object $object): string {
        return json_encode($object, JSON_THROW_ON_ERROR);
    }
}
