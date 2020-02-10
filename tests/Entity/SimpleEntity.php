<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests\Entity;

class SimpleEntity
{
    private $id;
    private $name;
    private $status;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }
}
