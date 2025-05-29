<?php

namespace Webhub\SecureDbDump;

use Closure;

class AnonymizerConfig
{
    private array $config = [

        /**
         * Name of the database field to anonymize.
         */
        'field' => null,

        /**
         * Type of anonymization to apply.
         * Can be 'faker' or 'static'
         */
        'type' => null,

        /**
         * Faker method to use.
         * Required if 'type' is 'faker'.
         * See: https://fakerphp.org/formatters/
         */
        'method' => null,

        /**
         * Arguments for the faker method.
         * Optional, must be an array.
         */
        'args' => [],

        /**
         * Value to be set.
         * Required if 'type' is 'static'.
         */
        'value' => null,

        /**
         * Where conditions to apply the anonymization.
         * Optional, must be an array.
         */
        'where' => null,
    ];

    public static function make(): self
    {
        return new self;
    }

    public function field(string $field): self
    {
        $this->config['field'] = $field;

        return $this;
    }

    public function type(string $type): self
    {
        $this->config['type'] = $type;

        return $this;
    }

    public function method(string $method): self
    {
        $this->config['method'] = $method;

        return $this;
    }

    public function args(array $args): self
    {
        $this->config['args'] = $args;

        return $this;
    }

    public function value($value): self
    {
        $this->config['value'] = $value;

        return $this;
    }

    public function where(string $field, string|Closure $condition): self
    {
        $this->config['where'][$field] = $condition;

        return $this;
    }

    public function build(): array
    {
        return $this->config;
    }
}
