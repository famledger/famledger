<?php

namespace App\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use App\Service\DocumentDetector\DocumentDetectorRegistry;

class DocumentDetectorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(DocumentDetectorRegistry::class)) {
            return;
        }

        $definition = $container->findDefinition('App\Service\DocumentDetector\DocumentDetectorRegistry');

        $taggedServices = $container->findTaggedServiceIds('document_detector');

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addDetector', [new Reference($id)]);
        }
    }
}
