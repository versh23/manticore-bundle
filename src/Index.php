<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

class Index
{
    public const TYPE_TEXT = 'text';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_MULTI = 'multi';
    public const TYPE_MULTI64 = 'multi64';
    public const TYPE_BOOL = 'bool';
    public const TYPE_JSON = 'json';
    public const TYPE_STRING = 'string';
    public const TYPE_TIMESTAMP = 'timestamp';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_INTEGER,
        self::TYPE_FLOAT,
        self::TYPE_MULTI,
        self::TYPE_MULTI64,
        self::TYPE_BOOL,
        self::TYPE_JSON,
        self::TYPE_STRING,
        self::TYPE_TIMESTAMP,
    ];

    private $name;
    private $fields;
    private $options;
    private $class;

    public function __construct(string $name, string $class, array $fields, array $options)
    {
        $this->name = $name;
        $this->fields = $fields;
        $this->options = $options;
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

    public function getFieldsName(): array
    {
        return array_keys($this->fields);
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
