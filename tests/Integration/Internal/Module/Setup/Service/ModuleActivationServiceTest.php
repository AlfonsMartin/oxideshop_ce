<?php
declare(strict_types=1);

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Tests\Integration\Internal\Module\Setup\Service;

use OxidEsales\EshopCommunity\Internal\Adapter\Configuration\Dao\ShopConfigurationSettingDaoInterface;
use OxidEsales\EshopCommunity\Internal\Adapter\Configuration\DataObject\ShopConfigurationSetting;
use OxidEsales\EshopCommunity\Internal\Adapter\ShopAdapterInterface;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\Dao\ModuleConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\Dao\ShopConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ClassExtensionsChain;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ModuleConfiguration\TemplateBlock;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ModuleConfiguration\Template;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ShopConfiguration;
use OxidEsales\EshopCommunity\Internal\Module\Path\ModulePathResolver;
use OxidEsales\EshopCommunity\Internal\Module\Path\ModulePathResolverInterface;
use OxidEsales\EshopCommunity\Internal\Module\Setting\Setting;
use OxidEsales\EshopCommunity\Internal\Module\Setup\Service\ModuleActivationServiceInterface;
use OxidEsales\EshopCommunity\Internal\Module\State\ModuleStateServiceInterface;
use OxidEsales\EshopCommunity\Internal\Utility\ContextInterface;
use OxidEsales\EshopCommunity\Tests\Integration\Internal\ContainerTrait;
use OxidEsales\EshopCommunity\Tests\Integration\Internal\Module\TestData\TestModule\SomeModuleService;
use OxidEsales\EshopCommunity\Tests\Integration\Internal\TestContainerFactory;
use OxidEsales\TestingLibrary\Services\Library\DatabaseRestorer\DatabaseRestorer;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ModuleConfiguration\ClassExtension;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ModuleConfiguration\Controller;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ModuleConfiguration\SmartyPluginDirectory;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ModuleConfiguration\ClassWithoutNamespace;

/**
 * @internal
 */
class ModuleActivationServiceTest extends TestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;
    private $shopId = 1;
    private $testModuleId = 'testModuleId';
    private $databaseRestorer;

    use ContainerTrait;

    public function setUp()
    {
        $this->container = $this->setupAndConfigureContainer();

        $this->databaseRestorer = new DatabaseRestorer();
        $this->databaseRestorer->dumpDB(__CLASS__);

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->databaseRestorer->restoreDB(__CLASS__);

        parent::tearDown();
    }

    public function testActivation()
    {
        $this->persistModuleConfiguration($this->getTestModuleConfiguration());

        $moduleStateService = $this->container->get(ModuleStateServiceInterface::class);
        $moduleActivationService = $this->container->get(ModuleActivationServiceInterface::class);

        $moduleActivationService->activate($this->testModuleId, $this->shopId);

        $this->assertTrue($moduleStateService->isActive($this->testModuleId, $this->shopId));

        $moduleActivationService->deactivate($this->testModuleId, $this->shopId);

        $this->assertFalse($moduleStateService->isActive($this->testModuleId, $this->shopId));
    }

    public function testSetConfiguredInModuleConfiguration()
    {
        $this->persistModuleConfiguration($this->getTestModuleConfiguration());

        $moduleConfigurationDao = $this->container->get(ModuleConfigurationDaoInterface::class);
        $moduleActivationService = $this->container->get(ModuleActivationServiceInterface::class);

        $moduleActivationService->activate($this->testModuleId, $this->shopId);
        $moduleConfiguration = $moduleConfigurationDao->get($this->testModuleId, $this->shopId);

        $this->assertTrue($moduleConfiguration->isConfigured());

        $moduleActivationService->deactivate($this->testModuleId, $this->shopId);
        $moduleConfiguration = $moduleConfigurationDao->get($this->testModuleId, $this->shopId);

        $this->assertFalse($moduleConfiguration->isConfigured());
    }

    public function testClassExtensionChainUpdate()
    {
        $shopConfigurationSettingDao = $this->container->get(ShopConfigurationSettingDaoInterface::class);

        $moduleConfiguration = $this->getTestModuleConfiguration();
        $moduleConfiguration->addClassExtension(new ClassExtension('originalClassNamespace', 'moduleClassNamespace'));

        $this->persistModuleConfiguration($moduleConfiguration);

        $moduleActivationService = $this->container->get(ModuleActivationServiceInterface::class);
        $moduleActivationService->activate($this->testModuleId, $this->shopId);

        $moduleClassExtensionChain = $shopConfigurationSettingDao->get(
            ShopConfigurationSetting::MODULE_CLASS_EXTENSIONS_CHAIN,
            $this->shopId
        );

        $this->assertSame(
            ['originalClassNamespace' => 'moduleClassNamespace'],
            $moduleClassExtensionChain->getValue()
        );

        $moduleActivationService->deactivate($this->testModuleId, $this->shopId);

        $moduleClassExtensionChain = $shopConfigurationSettingDao->get(
            ShopConfigurationSetting::MODULE_CLASS_EXTENSIONS_CHAIN,
            $this->shopId
        );

        $this->assertSame(
            [],
            $moduleClassExtensionChain->getValue()
        );
    }

    public function testActivationOfModuleServices()
    {
        $moduleConfiguration = $this->getTestModuleConfiguration();
        $this->persistModuleConfiguration($moduleConfiguration);

        $moduleActivationService = $this->container->get(ModuleActivationServiceInterface::class);
        $moduleActivationService->activate($this->testModuleId, $this->shopId);

        $this->assertInstanceOf(
            SomeModuleService::class,
            $this->setupAndConfigureContainer()->get(SomeModuleService::class)
        );
    }

    /**
     * @return ShopAdapterInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getModulePathResolverMock()
    {
        $modulePathResolverMock = $this
            ->getMockBuilder(ModulePathResolverInterface::class)
            ->getMock();

        $modulePathResolverMock
            ->method('getFullModulePathFromConfiguration')
            ->willReturn(__DIR__ . '/../../TestData/TestModule');

        return $modulePathResolverMock;
    }

    private function getTestModuleConfiguration(): ModuleConfiguration
    {
        $moduleConfiguration = new ModuleConfiguration();
        $moduleConfiguration->setId($this->testModuleId);
        $moduleConfiguration->setPath('TestModule');

        $setting = new Setting();
        $setting
            ->setName('test')
            ->setValue([1, 2])
            ->setType('aarr')
            ->setGroupName('group')
            ->setPositionInGroup(7)
            ->setConstraints([1, 2]);

        $templateBlock = new TemplateBlock(
            'extendedTemplatePath',
            'testBlock',
            'filePath'
        );
        $templateBlock->setTheme('flow_theme');
        $templateBlock->setPosition(3);

        $moduleConfiguration->addModuleSetting($setting);

        $moduleConfiguration
            ->addController(
                new Controller(
                    'originalClassNamespace',
                    'moduleClassNamespace'
                )
            )->addController(
                new Controller(
                    'otherOriginalClassNamespace',
                    'moduleClassNamespace'
                )
            )
            ->addTemplate(new Template('originalTemplate', 'moduleTemplate'))
            ->addTemplate(new Template('otherOriginalTemplate', 'moduleTemplate'))
            ->addSmartyPluginDirectory(
                new SmartyPluginDirectory(
                    'SmartyPlugins/directory1'
                )
            )->addSmartyPluginDirectory(
                new SmartyPluginDirectory(
                    'SmartyPlugins/directory2'
                )
            )
            ->addTemplateBlock($templateBlock)
            ->addClassExtension(
                new ClassExtension(
                    'originalClassNamespace',
                    'moduleClassNamespace'
                )
            )
            ->addClassExtension(
                new ClassExtension(
                    'otherOriginalClassNamespace',
                    'moduleClassNamespace'
                )
            )->addClassWithoutNamespace(
                new ClassWithoutNamespace(
                    'class1',
                    'class1.php'
                )
            )->addClassWithoutNamespace(
                new ClassWithoutNamespace(
                    'class2',
                    'class2.php'
                )
            );

        $setting = new Setting();
        $setting
            ->setName('grid')
            ->setValue('row')
            ->setType('str')
            ->setGroupName('frontend');
        $moduleConfiguration->addModuleSetting($setting);

        $setting = new Setting();
        $setting
            ->setName('array')
            ->setValue(['1', '2'])
            ->setType('arr')
            ->setGroupName('frontend');
        $moduleConfiguration->addModuleSetting($setting);

        return $moduleConfiguration;
    }

    /**
     * @param ModuleConfiguration $moduleConfiguration
     */
    private function persistModuleConfiguration(ModuleConfiguration $moduleConfiguration)
    {
        $chain = new ClassExtensionsChain();
        $chain->setChain([
            'originalClassNamespace' => ['moduleClassNamespace'],
        ]);

        $shopConfiguration = new ShopConfiguration();
        $shopConfiguration->setClassExtensionsChain($chain);
        $shopConfiguration->addModuleConfiguration($moduleConfiguration);

        $shopConfigurationDao = $this->container->get(ShopConfigurationDaoInterface::class);
        $shopConfigurationDao->save($shopConfiguration, $this->shopId, $this->getEnvironment());
    }

    /**
     * We need to replace services in the container with a mock
     *
     * @return \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    private function setupAndConfigureContainer()
    {
        $container = (new TestContainerFactory())->create();

        $container->set(ModulePathResolverInterface::class, $this->getModulePathResolverMock());
        $container->autowire(ModulePathResolverInterface::class, ModulePathResolver::class);

        $container->compile();

        return $container;
    }

    private function getEnvironment(): string
    {
        return $this->get(ContextInterface::class)->getEnvironment();
    }
}
