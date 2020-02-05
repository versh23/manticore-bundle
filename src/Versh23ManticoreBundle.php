<?php

namespace Versh23\ManticoreBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Versh23\ManticoreBundle\DependencyInjection\Compiler\IndexManagerPass;


class Versh23ManticoreBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new IndexManagerPass());
    }
}