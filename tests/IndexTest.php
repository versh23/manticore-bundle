<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests;

use PHPUnit\Framework\TestCase;
use Versh23\ManticoreBundle\Index;
use Versh23\ManticoreBundle\Tests\Entity\SimpleEntity;

class IndexTest extends TestCase
{
    public function testCreateIndex()
    {
        $index = new Index('test_index', SimpleEntity::class, [
            'name' => ['property' => 'name'],
        ], [
            'status' => ['property' => 'status', 'type' => 'string'],
        ]);

        $this->assertCount(1, $index->getFields());
        $this->assertCount(1, $index->getAttributes());

        $this->assertEquals(SimpleEntity::class, $index->getClass());
        $this->assertEquals('test_index', $index->getName());
        $this->assertEquals(['name'], $index->getFieldsName());
    }
}
