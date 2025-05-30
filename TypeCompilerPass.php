<?php

/*
 * This file is part of the enhavo package.
 *
 * (c) WE ARE INDEED GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Enhavo\Component\Type;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class TypeCompilerPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $namespace,
        private readonly string $tagName,
        private readonly string $class,
    ) {
    }

    public function process(ContainerBuilder $container)
    {
        $registryDefinition = $this->createRegistryDefinition();
        $container->addDefinitions([
            sprintf('%s[%s]', RegistryInterface::class, $this->namespace) => $registryDefinition,
        ]);

        $factoryDefinitionName = sprintf('%s[%s]', FactoryInterface::class, $this->namespace);
        if (!$container->hasDefinition($factoryDefinitionName)) {
            $factoryDefinition = $this->createFactoryDefinition($this->class, $registryDefinition);
            $container->addDefinitions([$factoryDefinitionName => $factoryDefinition]);
        }

        $taggedServices = $container->findTaggedServiceIds($this->tagName);

        foreach ($taggedServices as $id => $tagAttributes) {
            $tagServiceDefinition = $container->getDefinition($id);
            $tagServiceDefinition->setPublic(true);
            $registryDefinition->addMethodCall(
                'register',
                [$tagServiceDefinition->getClass() ? $tagServiceDefinition->getClass() : $id, $id]
            );
        }
    }

    private function createRegistryDefinition(): Definition
    {
        $definition = new Definition();
        $definition->setClass(Registry::class);
        $definition->setArguments([$this->namespace]);
        $definition->addMethodCall('setContainer', [new Reference('service_container')]);

        return $definition;
    }

    private function createFactoryDefinition($class, $registryDefinition): Definition
    {
        $definition = new Definition();
        $definition->setClass(Factory::class);
        $definition->setArguments([$class, $registryDefinition]);

        return $definition;
    }
}
