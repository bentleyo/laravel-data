<?php

namespace Spatie\LaravelData\Tests;

use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\WithoutValidation;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Tests\Fakes\DummyBackedEnum;
use Spatie\LaravelData\Tests\Fakes\SimpleData;
use Spatie\LaravelData\Tests\Fakes\SimpleDataWithExplicitValidationRuleAttributeData;
use Spatie\LaravelData\Tests\TestSupport\DataValidationAsserter;

it('can validate a string', function () {
    $dataClass = new class () extends Data {
        public string $property;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['property' => 'Hello World'])
        ->assertRules([
            'property' => ['string', 'required'],
        ]);
});

it('can validate a float', function () {
    $dataClass = new class () extends Data {
        public float $property;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['property' => 10.0])
        ->assertRules([
            'property' => ['numeric', 'required'],
        ]);
});

it('can validate an integer', function () {
    $dataClass = new class () extends Data {
        public int $property;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['property' => 10.0])
        ->assertRules([
            'property' => ['numeric', 'required'],
        ]);
});

it('can validate an array', function () {
    $dataClass = new class () extends Data {
        public array $property;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['property' => ['Hello World']])
        ->assertRules([
            'property' => ['array', 'required'],
        ]);
});

it('can validate a bool', function () {
    $dataClass = new class () extends Data {
        public bool $property;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['property' => true])
        ->assertRules([
            'property' => ['boolean'],
        ]);
});

it('can validate a nullable type', function () {
    $dataClass = new class () extends Data {
        public ?array $property;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['property' => ['Hello World']])
        ->assertOk(['property' => null])
        ->assertOk([])
        ->assertRules([
            'property' => ['array', 'nullable'],
        ]);
});

it('can validated a property with custom rules', function () {
    $dataClass = new class () extends Data {
        public ?array $property;

        public static function rules(): array
        {
            return [
                'property' => ['array', 'min:5'],
            ];
        }
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules([
            'property' => ['array', 'min:5'],
        ]);
});

it('can validate a property with custom rules as string', function () {
    $dataClass = new class () extends Data {
        public ?array $property;

        public static function rules(): array
        {
            return [
                'property' => 'array|min:5',
            ];
        }
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules([
            'property' => ['array', 'min:5'],
        ]);
});

it('can validate a property with custom rules as object', function () {
    $dataClass = new class () extends Data {
        public ?array $property;

        public static function rules(): array
        {
            return [
                'property' => [new ArrayType(), new Min(5)],
            ];
        }
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules([
            'property' => ['array', 'min:5'],
        ]);
});

it('can validate a property with attributes', function () {
    $dataClass = new class () extends Data {
        #[Min(5)]
        public ?array $property;
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules([
            'property' => ['array', 'min:5', 'nullable'],
        ]);
});

it('can validate an optional attribute', function () {
    DataValidationAsserter::for(new class () extends Data {
        public array|Optional $property;
    })
        ->assertOk([])
        ->assertOk(['property' => []])
        ->assertErrors(['property' => null])
        ->assertRules([
            'property' => ['sometimes', 'array'],
        ]);

    DataValidationAsserter::for(new class () extends Data {
        public array|Optional|null $property;
    })
        ->assertOk([])
        ->assertOk(['property' => []])
        ->assertOk(['property' => null])
        ->assertRules([
            'property' => ['sometimes', 'array', 'nullable'],
        ]);

    DataValidationAsserter::for(new class () extends Data {
        #[Max(10)]
        public array|Optional $property;
    })
        ->assertOk([])
        ->assertOk(['property' => []])
        ->assertErrors(['property' => null])
        ->assertRules([
            'property' => ['sometimes', 'array', 'max:10'],
        ]);
});

it('can validate a native enum', function () {
    $dataClass = new class () extends Data {
        public DummyBackedEnum $property;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['property' => 'foo'])
        ->assertRules([
            'property' => [new Enum(DummyBackedEnum::class), 'required'],
        ]);
});

it('will use name mapping within validation', function () {
    $dataClass = new class () extends Data {
        #[MapInputName('some_property')]
        public string $property;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['some_property' => 'foo'])
        ->assertRules([
            'some_property' => ['string', 'required'],
        ]);
});

it('can disable validation', function () {
    $dataClass = new class () extends Data {
        #[WithoutValidation]
        public string $property;

        #[DataCollectionOf(SimpleData::class), WithoutValidation]
        public DataCollection $collection;

        #[WithoutValidation]
        public SimpleData $data;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk([])
        ->assertRules([]);
});

it('can write custom rules based upon payloads', function () {
    $dataClass = new class () extends Data {
        public bool $strict;

        public string $property;

        #[MapInputName(SnakeCaseMapper::class)]
        public string $mappedProperty;

        public static function rules(array $payload): array
        {
            if ($payload['strict'] === true) {
                return [
                    'property' => ['in:strict'],
                    'mapped_property' => ['in:strict'],
                ];
            }

            return [];
        }
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules(
            rules: [
                'strict' => ['boolean'],
                'property' => ['in:strict'],
                'mapped_property' => ['in:strict'],
            ],
            payload: [
                'strict' => true,
            ]
        )
        ->assertRules(
            rules: [
                'strict' => ['boolean'],
                'property' => ['string', 'required'],
                'mapped_property' => ['string', 'required'],
            ],
            payload: [
                'strict' => false,
            ]
        );
});

it('can validate nested data', function () {
    eval(<<<'PHP'
            use Spatie\LaravelData\Data;
            class NestedClassA extends Data {
                public string $name;
            }
        PHP);

    $dataClass = new class () extends Data {
        public \NestedClassA $nested;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['nested' => ['name' => 'Hello World']])
        ->assertErrors(['nested' => []])
        ->assertErrors(['nested' => null])
        ->assertRules([
            'nested' => ['required', 'array'],
            'nested.name' => ['string', 'required'],
        ]);
});

it('can validate nested nullable data', function () {
    eval(<<<'PHP'
            use Spatie\LaravelData\Data;
            class NestedClassB extends Data {
                public string $name;
            }
        PHP);

    $dataClass = new class () extends Data {
        public ?\NestedClassB $nested;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['nested' => ['name' => 'Hello World']])
        ->assertOk(['nested' => ['name' => null]])
        ->assertOk(['nested' => null])
        ->assertOk(['nested' => []])
        ->assertRules([
            'nested' => ['nullable', 'array'],
            'nested.name' => ['nullable', 'string'],
        ]);
});

it('can validate nested optional data', function () {
    eval(<<<'PHP'
            use Spatie\LaravelData\Data;
            class NestedClassC extends Data {
                public string $name;
            }
        PHP);

    $dataClass = new class () extends Data {
        public \NestedClassC|Optional $nested;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['nested' => ['name' => 'Hello World']])
        ->assertOk(['nested' => null])
        ->assertErrors(['nested' => ['name' => null]])
        ->assertErrors(['nested' => []])
        ->assertRules([
            'nested' => ['sometimes', 'array'],
            'nested.name' => ['nullable', 'string'],
        ]);
})->skip('Failures');

it('can add additional rules to nested data', function () {
    eval(<<<'PHP'
            use Spatie\LaravelData\Attributes\Validation\In;use Spatie\LaravelData\Data;
            class NestedClassD extends Data {
                public string $name;
            }
        PHP);

    $dataClass = new class () extends Data {
        #[Min(100)]
        public \NestedClassD|Optional $nested;
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules([
            'nested' => ['sometimes', 'array', 'min:100'],
            'nested.name' => ['nullable', 'string'],
        ]);
});

it('will use name mapping with nested objects', function () {
    $dataClass = new class () extends Data {
        #[MapInputName('some_nested')]
        public SimpleData $nested;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk(['some_nested' => ['string' => 'Hello World']])
        ->assertRules([
            'some_nested' => ['required', 'array'],
            'some_nested.string' => ['string', 'required'],
        ]);
});

it('can use nested payloads in nested data', function () {
    eval(<<<'PHP'
            use Spatie\LaravelData\Attributes\Validation\In;use Spatie\LaravelData\Data;
            class NestedClassF extends Data {
                public bool $strict;

                public string $name;

                public static function rules(array $payload, string $path): array{
                    if(Arr::get($payload, "{$path}.strict")) {
                        return ['name' => ['in:strict']];
                    }
                    return [];
                }
            }
        PHP);

    $dataClass = new class () extends Data {
        public \NestedClassF $some_nested;
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules(
            rules: [
                'some_nested' => ['required', 'array'],
                'some_nested.strict' => ['boolean'],
                'some_nested.name' => ['in:strict'],
            ],
            payload: [
                'some_nested' => [
                    'strict' => true,
                ],
            ]
        )
        ->assertRules(
            rules: [
                'some_nested' => ['required', 'array'],
                'some_nested.strict' => ['boolean'],
                'some_nested.name' => ['required', 'string'],
            ],
            payload: [
                'some_nested' => [
                    'strict' => false,
                ],
            ]
        );
});

it('can use nested payloads in nested collection data', function () {
    // A modified version of data_get with path support so that we get all known data and its matching path
    // for a key like collection_a.*.collection_b.*.example
    eval(<<<'PHP'
        function data_get_with_path($target, $key, $default = null, $path = null): array
        {
            $result = [];

            if (is_null($key)) {
                return $result;
            }

            $key = is_array($key) ? $key : explode('.', $key);
            $fullPath = $path;

            foreach ($key as $i => $segment) {
                unset($key[$i]);

                if (is_null($segment)) {
                    return $result;
                }

                if ($segment === '*') {
                    if ($target instanceof Collection) {
                        $target = $target->all();
                    } elseif (! is_iterable($target)) {
                        return value($default);
                    }

                    foreach ($target as $iteration => $item) {
                        $result = [
                            ...$result,
                            ...data_get_with_path($item, $key, null, $fullPath ? "{$fullPath}.{$iteration}" : $iteration),
                        ];
                    }

                    return $result;
                }

                $fullPath = $fullPath
                    ? "{$fullPath}.{$segment}"
                    : "{$segment}";

                if (Arr::accessible($target) && Arr::exists($target, $segment)) {
                    $target = $target[$segment];
                } elseif (is_object($target) && isset($target->{$segment})) {
                    $target = $target->{$segment};
                } else {
                    $result[$fullPath] = value($default);
                    return $result;
                }
            }

            $result[$fullPath] = $target;
            return $result;
        }
    PHP);

    eval(<<<'PHP'
        use Spatie\LaravelData\Attributes\Validation\In;use Spatie\LaravelData\Data;
        class NestedClassH extends Data {
            public bool $strict;

            public string $name;

            public static function rules(array $payload, string $path): array
            {
                $rules = [];
                foreach (data_get_with_path($payload, $path) as $dataObjectPath => $dataObjectData) {
                    if ($dataObjectData['strict'] ?? false) {
                        // Using ^ to signify an absolute rule path instead of relative
                        $rules["^{$dataObjectPath}.name"] = ['in:strict'];
                    }
                }
                return $rules;
            }
        }
    PHP);

    $dataClass = new class () extends Data {
        #[DataCollectionOf(\NestedClassH::class)]
        public DataCollection $some_nested;
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules(
            rules: [
                'some_nested' => ['present', 'array'],
                'some_nested.*.strict' => ['boolean'],
                'some_nested.*.name' => ['required', 'string'],
                'some_nested.0.name' => ['in:strict'],
                'some_nested.2.name' => ['in:strict'],
            ],
            payload: [
                'some_nested' => [
                    ['strict' => true],
                    ['strict' => false],
                    ['strict' => true],
                ],
            ]
        );
});

test('rules in nested data are rewritten according to their fields', function () {
    // Should we do the same with the `rules` method?
    // Also implement for collections
    eval(<<<'PHP'
            use Spatie\LaravelData\Attributes\Validation\In;
            use Spatie\LaravelData\Attributes\Validation\RequiredIf;use Spatie\LaravelData\Attributes\Validation\RequiredWith;use Spatie\LaravelData\Data;
            class NestedClassG extends Data {
                public bool $alsoAString;

                #[RequiredIf('alsoAString', true)]
                public string $string;
            }
        PHP);

    $dataClass = new class () extends Data {
        public \NestedClassG $nested;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk([
            'nested' => ['alsoAString' => '0'],
        ])
        ->assertErrors([
            'nested' => ['alsoAString' => '1'],
        ]) // Fails when we prefix the rule with nested.
        ->assertRules(
            rules: [
                'nested' => ['required', 'array'],
                'nested.alsoAString' => ['boolean'],
                'nested.string' => ['required_if:alsoAString,1', 'string'],
            ]
        );
})->skip('Feature to add');

it('will validate a collection', function () {
    $dataClass = new class () extends Data {
        #[DataCollectionOf(SimpleData::class)]
        public DataCollection $collection;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk([
            'collection' => [
                ['string' => 'Never Gonna'],
                ['string' => 'Give You Up'],
            ],
        ])
        ->assertOk(['collection' => []])
        ->assertErrors(['collection' => null])
        ->assertErrors([])
        ->assertErrors([
            'collection' => [
                ['other_string' => 'Hello World'],
            ],
        ])
        ->assertRules([
            'collection' => ['present', 'array'],
            'collection.*.string' => ['string', 'required'],
        ]);
});

it('will validate a nullable collection', function () {
    $dataClass = new class () extends Data {
        #[DataCollectionOf(SimpleData::class)]
        public ?DataCollection $collection;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk([
            'collection' => [
                ['string' => 'Never Gonna'],
                ['string' => 'Give You Up'],
            ],
        ])
        ->assertOk(['collection' => []])
        ->assertOk(['collection' => null])
        ->assertOk([])
        ->assertErrors([
            'collection' => [
                ['other_string' => 'Hello World'],
            ],
        ])
        ->assertRules([
            'collection' => ['nullable', 'array'],
            'collection.*.string' => ['string', 'required'],
        ]);
});

it('will validate an optional collection', function () {
    $dataClass = new class () extends Data {
        #[DataCollectionOf(SimpleData::class)]
        public Optional|DataCollection $collection;
    };

    DataValidationAsserter::for($dataClass)
        ->assertOk([
            'collection' => [
                ['string' => 'Never Gonna'],
                ['string' => 'Give You Up'],
            ],
        ])
        ->assertOk(['collection' => []])
        ->assertOk([])
        ->assertErrors(['collection' => null])
        ->assertErrors([
            'collection' => [
                ['other_string' => 'Hello World'],
            ],
        ])
        ->assertRules([
            'collection' => ['sometimes', 'array'],
            'collection.*.string' => ['string', 'required'],
        ]);
});

it('can overwrite collection class rules', function () {
    $dataClass = new class () extends Data {
        #[DataCollectionOf(SimpleData::class)]
        public DataCollection $collection;

        public static function rules(): array
        {
            return [
                'collection' => ['array', 'min:1', 'max:5'],
                'collection.*.string' => ['required', 'string', 'min:100'],
            ];
        }
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules([
            'collection' => ['array', 'min:1', 'max:5'],
            'collection.*.string' => ['required', 'string', 'min:100'],
        ]);
});

it('can add collection class rules using attributes', function () {
    $dataClass = new class () extends Data {
        #[DataCollectionOf(SimpleDataWithExplicitValidationRuleAttributeData::class)]
        #[Min(10)]
        public DataCollection $collection;
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules([
            'collection' => ['present', 'array', 'min:10'],
            'collection.*.email' => ['string', 'required', 'email:rfc'],
        ]);
});

/**
 * Complex Examples
 */

it('can nest data in collections', function () {
    eval(<<<'PHP'
            use Spatie\LaravelData\Data;
            class NestedClassE extends Data {
                public string $string;
            }

            class CollectionClassA extends Data {
                public \NestedClassE $nested;
            }
        PHP);

    $dataClass = new class () extends Data {
        #[DataCollectionOf(\CollectionClassA::class)]
        public DataCollection $collection;
    };

    DataValidationAsserter::for($dataClass)
        ->assertRules([
            'collection' => ['present', 'array'],
            'collection.*.nested' => ['required', 'array'],
            'collection.*.nested.string' => ['required', 'string'],
        ])
        ->assertOk(['collection' => [['nested' => ['string' => 'Hello World']]]]);
});
