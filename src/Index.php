<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

class Index
{
    public const ATTR_TYPE_STRING = 'string';
    public const ATTR_TYPE_TIMESTAMP = 'timestamp';
    public const ATTR_TYPE_INT = 'uint';
    public const ATTR_TYPE_BIGINT = 'bigint';
    public const ATTR_TYPE_FLOAT = 'float';
    public const ATTR_TYPE_JSON = 'json';
    public const ATTR_TYPE_MVA = 'multi';
    public const ATTR_TYPE_BOOL = 'bool';

    public static $attrTypes = [
        self::ATTR_TYPE_STRING,
        self::ATTR_TYPE_TIMESTAMP,
        self::ATTR_TYPE_BIGINT,
        self::ATTR_TYPE_INT,
        self::ATTR_TYPE_FLOAT,
        self::ATTR_TYPE_JSON,
        self::ATTR_TYPE_MVA,
        self::ATTR_TYPE_BOOL,
    ];

    private $name;
    private $fields;
    private $attributes;
    private $class;

    public function __construct(string $name, string $class, array $fields, array $attributes)
    {
        $this->name = $name;
        $this->fields = $fields;
        $this->attributes = $attributes;
        $this->class = $class;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
