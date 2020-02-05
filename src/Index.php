<?php


namespace Versh23\ManticoreBundle;


class Index
{
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
}