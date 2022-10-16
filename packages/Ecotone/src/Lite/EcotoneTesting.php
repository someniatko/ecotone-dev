<?php

namespace Ecotone\Lite;

use Ecotone\AnnotationFinder\InMemory\InMemoryAnnotationFinder;
use Ecotone\Messaging\Config\ConfiguredMessagingSystem;
use Ecotone\Messaging\Config\InMemoryReferenceTypeFromNameResolver;
use Ecotone\Messaging\Config\MessagingSystemConfiguration;
use Ecotone\Messaging\Config\ModuleList;
use Ecotone\Messaging\Config\ServiceConfiguration;
use Ecotone\Messaging\Config\StubConfiguredMessagingSystem;
use Ecotone\Messaging\Handler\Logger\EchoLogger;
use Ecotone\Messaging\InMemoryConfigurationVariableService;

final class EcotoneTesting
{
    const CONFIGURED_MESSAGING_SYSTEM = ConfiguredMessagingSystem::class;

    /**
     * @param string[] $classesToResolve
     * @param array<string,string> $configurationVariables
     * @param ContainerInterfaceWithSet|object[] $containerOrAvailableServices
     */
    public static function boostrapAllModules(
        array                           $classesToResolve = [],
        ContainerInterfaceWithSet|array $containerOrAvailableServices = [],
        ?ServiceConfiguration           $configuration = null,
        array                           $configurationVariables = [],
    ): ConfiguredMessagingSystem
    {
        if (!$configuration) {
            $configuration = ServiceConfiguration::createWithDefaults();
        }

        return self::prepareConfiguration(ModuleList::allModules(), $containerOrAvailableServices, $configuration, $classesToResolve, $configurationVariables);
    }

    /**
     * @param string[] $classesToResolve
     * @param array<string,string> $configurationVariables
     * @param ContainerInterfaceWithSet|object[] $containerOrAvailableServices
     */
    public static function boostrapWithMessageHandlers(
        array                           $classesToResolve = [],
        ContainerInterfaceWithSet|array $containerOrAvailableServices = [],
        ?ServiceConfiguration           $configuration = null,
        array                           $configurationVariables = [],
        array $enableModules
    ): ConfiguredMessagingSystem
    {
        if (!$configuration) {
            $configuration = ServiceConfiguration::createWithDefaults();
        }

        return self::prepareConfiguration(array_merge(ModuleList::CORE_MODULES, $enableModules), $containerOrAvailableServices, $configuration, $classesToResolve, $configurationVariables);
    }

    /**
     * @param string[] $modules
     * @param string[] $classesToResolve
     * @param array<string,string> $configurationVariables
     * @param ContainerInterfaceWithSet|object[] $containerOrAvailableServices
     */
    private static function prepareConfiguration(array $modulesToEnable, ContainerInterfaceWithSet|array $containerOrAvailableServices, ServiceConfiguration $configuration, array $classesToResolve, array $configurationVariables): ConfiguredMessagingSystem
    {
        $container = $containerOrAvailableServices instanceof ContainerInterfaceWithSet ? $containerOrAvailableServices : InMemoryPSRContainerInterfaceWithSet::createFromAssociativeArray($containerOrAvailableServices);

        $modulesToEnable = array_unique($modulesToEnable);
        $configuration = $configuration->withSkippedModulePackageNames(array_diff(ModuleList::allModules(), $modulesToEnable));

        $messagingConfiguration = MessagingSystemConfiguration::prepareWithAnnotationFinder(
            InMemoryAnnotationFinder::createFrom(array_merge($classesToResolve, $modulesToEnable)),
            InMemoryReferenceTypeFromNameResolver::createFromAssociativeArray($classesToResolve),
            InMemoryConfigurationVariableService::create($configurationVariables),
            $configuration,
            false
        );

        foreach ($messagingConfiguration->getRegisteredGateways() as $gatewayProxyBuilder) {
            $container->set($gatewayProxyBuilder->getReferenceName(), ProxyGenerator::createFor(
                $gatewayProxyBuilder->getReferenceName(),
                $container,
                $gatewayProxyBuilder->getInterfaceName(),
                sys_get_temp_dir()
            ));
        }

        $messagingSystem = $messagingConfiguration->buildMessagingSystemFromConfiguration(
            new PsrContainerReferenceSearchService($container, ["logger" => new EchoLogger(), ConfiguredMessagingSystem::class => new StubConfiguredMessagingSystem()])
        );

        $container->set(self::CONFIGURED_MESSAGING_SYSTEM, $messagingSystem);

        return $messagingSystem;
    }
}