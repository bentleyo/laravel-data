<?php

namespace Spatie\LaravelData\Support\Annotations;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\FqsenResolver;
use phpDocumentor\Reflection\Types\Context;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Contracts\BaseDataCollectable;

class DataCollectableAnnotationReader
{
    /** @var array<string, Context> */
    protected static array $contexts = [];

    public static function create(): self
    {
        return new self();
    }

    /** @return array<string, \Spatie\LaravelData\Support\Annotations\DataCollectableAnnotation> */
    public function getForClass(ReflectionClass $class): array
    {
        return collect($this->get($class))->keyBy(fn(DataCollectableAnnotation $annotation) => $annotation->property)->all();
    }

    public function getForProperty(ReflectionProperty $property): ?DataCollectableAnnotation
    {
        return Arr::first($this->get($property));
    }

    /** @return \Spatie\LaravelData\Support\Annotations\DataCollectableAnnotation[] */
    protected function get(
        ReflectionProperty|ReflectionClass $reflection
    ): array {
        $comment = $reflection->getDocComment();

        if ($comment === false) {
            return [];
        }

        $comment = str_replace('?', '', $comment);

        $kindPattern = '(?:@property|@var)\s*';
        $fqsenPattern = '[\\\\a-z0-9_\|]+';
        $keyPattern = '(?:int|string|\(int\|string\)|array-key)';
        $parameterPattern = '\s*\$?(?<parameter>[a-z0-9_]+)?';

        preg_match_all(
            "/{$kindPattern}(?<dataClass>{$fqsenPattern})\[\]{$parameterPattern}/i",
            $comment,
            $arrayMatches,
        );

        preg_match_all(
            "/{$kindPattern}(?<collectionClass>{$fqsenPattern})<(?:{$keyPattern},\s*)?(?<dataClass>{$fqsenPattern})>{$parameterPattern}/i",
            $comment,
            $collectionMatches,
        );

        return [
            ...$this->resolveArrayAnnotations($reflection, $arrayMatches),
            ...$this->resolveCollectionAnnotations($reflection, $collectionMatches),
        ];
    }

    protected function resolveArrayAnnotations(
        ReflectionProperty|ReflectionClass $reflection,
        array $arrayMatches
    ): array {
        $annotations = [];

        foreach ($arrayMatches['dataClass'] as $index => $dataClass) {
            $parameter = $arrayMatches['parameter'][$index];

            $dataClass = $this->resolveDataClass($reflection, $dataClass);

            if ($dataClass === null) {
                continue;
            }

            $annotations[] = new DataCollectableAnnotation(
                $dataClass,
                null,
                empty($parameter) ? null : $parameter
            );
        }

        return $annotations;
    }

    protected function resolveCollectionAnnotations(
        ReflectionProperty|ReflectionClass $reflection,
        array $collectionMatches
    ): array {
        $annotations = [];

        foreach ($collectionMatches['dataClass'] as $index => $dataClass) {
            $parameter = $collectionMatches['parameter'][$index];

            $dataClass = $this->resolveDataClass($reflection, $dataClass);

            if ($dataClass === null) {
                continue;
            }

            $annotations[] = new DataCollectableAnnotation(
                $dataClass,
                null,
                empty($parameter) ? null : $parameter
            );
        }

        return $annotations;
    }

    protected function resolveDataClass(
        ReflectionProperty|ReflectionClass $reflection,
        string $class
    ): ?string {
        if (str_contains($class, '|')) {
            foreach (explode('|', $class) as $explodedClass) {
                if ($foundClass = $this->resolveDataClass($reflection, $explodedClass)) {
                    return $foundClass;
                }
            }

            return null;
        }

        $class = ltrim($class, '\\');

        if (is_subclass_of($class, BaseData::class)) {
            return $class;
        }

        $class = $this->resolveFcqn($reflection, $class);

        if (is_subclass_of($class, BaseData::class)) {
            return $class;
        }

        return null;
    }

    protected function resolveCollectionClass(
        ReflectionProperty|ReflectionClass $reflection,
        string $class
    ): ?string {
        if (str_contains($class, '|')) {
            foreach (explode('|', $class) as $explodedClass) {
                if ($foundClass = $this->resolveCollectionClass($reflection, $explodedClass)) {
                    return $foundClass;
                }
            }

            return null;
        }

        if ($class === 'array') {
            return $class;
        }

        $class = ltrim($class, '\\');

        if (is_a($class, BaseDataCollectable::class, true)
            || is_a($class, Enumerable::class, true)
            || is_a($class, AbstractPaginator::class, true)
            || is_a($class, CursorPaginator::class, true)
        ) {
            return $class;
        }

        $class = $this->resolveFcqn($reflection, $class);

        if (is_a($class, BaseDataCollectable::class, true)
            || is_a($class, Enumerable::class, true)
            || is_a($class, AbstractPaginator::class, true)
            || is_a($class, CursorPaginator::class, true)) {
            return $class;
        }

        return null;
    }

    protected function resolveFcqn(
        ReflectionProperty|ReflectionClass $reflection,
        string $class
    ): ?string {
        $context = $this->getContext($reflection);

        $type = (new FqsenResolver())->resolve($class, $context);

        return ltrim((string) $type, '\\');
    }


    protected function getContext(ReflectionProperty|ReflectionClass $reflection): Context
    {
        $reflectionClass = $reflection instanceof ReflectionProperty
            ? $reflection->getDeclaringClass()
            : $reflection;

        return static::$contexts[$reflectionClass->getName()] ??= (new ContextFactory())->createFromReflector($reflectionClass);
    }
}
