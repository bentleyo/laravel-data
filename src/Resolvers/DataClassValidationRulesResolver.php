<?php

namespace Spatie\LaravelData\Resolvers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Validation\RulesMapper;
use Spatie\LaravelData\Support\Validation\ValidationRule;

class DataClassValidationRulesResolver
{
    public function __construct(
        protected DataConfig $dataConfig,
        protected RulesMapper $ruleAttributesResolver,
    ) {
    }

    public function execute(string $class, array $payload = [], bool $nullable = false, ?string $dataPath = null): Collection
    {
        $resolver = app(DataPropertyValidationRulesResolver::class);

        $overWrittenRules = $this->resolveOverwrittenRules($class, $payload, $dataPath);

        return $this->dataConfig->getDataClass($class)
            ->properties
            ->reject(fn (DataProperty $property) => ! $property->validate)
            ->mapWithKeys(fn (DataProperty $property) => $resolver->execute($property, $payload, $nullable, $dataPath)->all())
            ->merge($overWrittenRules);
    }

    private function resolveOverwrittenRules(string $class, array $payload = [], ?string $dataPath = null): array
    {
        $overWrittenRules = [];

        if (method_exists($class, 'rules')) {
            $overWrittenRules = app()->call([$class, 'rules'], [
                'payload' => $payload,
                'path' => $dataPath,
            ]);
        }

        foreach ($overWrittenRules as $property => $rules) {
            $overWrittenRules[$property] = collect(Arr::wrap($rules))
                ->map(fn (mixed $rule) => is_string($rule) ? explode('|', $rule) : $rule)
                ->map(fn (mixed $rule) => $rule instanceof ValidationRule ? $rule->getRules() : $rule)
                ->flatten()
                ->all();
        }

        return $overWrittenRules;
    }
}
