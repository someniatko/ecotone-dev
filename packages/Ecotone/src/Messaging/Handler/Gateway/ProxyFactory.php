<?php

declare(strict_types=1);

namespace Ecotone\Messaging\Handler\Gateway;

use Ecotone\Messaging\Config\EcotoneRemoteAdapter;
use Ecotone\Messaging\Config\ServiceCacheConfiguration;
use ProxyManager\Autoloader\AutoloaderInterface;
use ProxyManager\Configuration;
use ProxyManager\Factory\RemoteObject\AdapterInterface;
use ProxyManager\Factory\RemoteObjectFactory;
use ProxyManager\FileLocator\FileLocator;
use ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy;
use ProxyManager\Proxy\RemoteObjectInterface;
use ProxyManager\Signature\ClassSignatureGenerator;
use ProxyManager\Signature\SignatureGenerator;
use Psr\Container\ContainerInterface;

use function spl_autoload_register;
use function spl_autoload_unregister;

/**
 * Class LazyProxyConfiguration
 * @package Ecotone\Messaging\Handler\Gateway
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class ProxyFactory
{
    public const REFERENCE_NAME = 'gatewayProxyConfiguration';

    private static ?AutoloaderInterface $registeredAutoloader = null;
    private bool $autoloaderRegistered = false;

    private function __construct(private ServiceCacheConfiguration $serviceCacheConfiguration)
    {
    }

    public static function createWithCache(ServiceCacheConfiguration $serviceCacheConfiguration): self
    {
        return new self($serviceCacheConfiguration);
    }

    private function getConfiguration(): Configuration
    {
        $configuration = new Configuration();

        if ($this->serviceCacheConfiguration->shouldUseCache()) {
            $configuration->setProxiesTargetDir($this->serviceCacheConfiguration->getPath());
            $fileLocator = new FileLocator($configuration->getProxiesTargetDir());
            $configuration->setGeneratorStrategy(new FileWriterGeneratorStrategy($fileLocator));
            $configuration->setClassSignatureGenerator(new ClassSignatureGenerator(new SignatureGenerator()));
        }

        return $configuration;
    }

    public function createProxyClassWithAdapter(string $interfaceName, AdapterInterface $adapter): RemoteObjectInterface
    {
        $factory = new RemoteObjectFactory($adapter, $this->getConfiguration());
        $this->registerProxyAutoloader();

        return $factory->createProxy($interfaceName);
    }

    public static function createFor(string $referenceName, ContainerInterface $container, string $interface, ServiceCacheConfiguration $serviceCacheConfiguration): object
    {
        $proxyFactory = self::createWithCache($serviceCacheConfiguration);

        return $proxyFactory->createProxyClassWithAdapter(
            $interface,
            new EcotoneRemoteAdapter($container, $referenceName)
        );
    }

    public function createWithCurrentConfiguration(string $referenceName, ContainerInterface $container, string $interface): object
    {
        return $this->createProxyClassWithAdapter(
            $interface,
            new EcotoneRemoteAdapter($container, $referenceName)
        );
    }

    private function registerProxyAutoloader(): void
    {
        if ($this->autoloaderRegistered) {
            return;
        }

        if (! $this->serviceCacheConfiguration->shouldUseCache()) {
            return;
        }

        if (self::$registeredAutoloader) {
            // another ProxyFactory instance may have already registered an autoloader.
            // this should not happen normally, but just in case we will unload
            // the old autoloader.
            spl_autoload_unregister(self::$registeredAutoloader);
        }

        self::$registeredAutoloader = $this->getConfiguration()->getProxyAutoloader();
        spl_autoload_register(self::$registeredAutoloader);
        $this->autoloaderRegistered = true;
    }
}
