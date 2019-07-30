<?php declare(strict_types=1);
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Tests\Integration\Internal\Module\Command;

use OxidEsales\EshopCommunity\Internal\Module\Configuration\Dao\ShopConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ShopConfiguration;
use OxidEsales\EshopCommunity\Internal\Module\State\ModuleStateServiceInterface;
use OxidEsales\EshopCommunity\Internal\Utility\ContextInterface;
use OxidEsales\TestingLibrary\Services\Library\DatabaseRestorer\DatabaseRestorer;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * @internal
 */
final class ActivateConfiguredModulesCommandTest extends ModuleCommandsTestCase
{
    /**
     * @var DatabaseRestorer
     */
    private $databaseRestorer;

    public function setUp()
    {
        $this->databaseRestorer = new DatabaseRestorer();
        $this->databaseRestorer->dumpDB(__CLASS__);

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->databaseRestorer->restoreDB(__CLASS__);

        parent::tearDown();
    }

    public function testActivateProperModulesInAllShops(): void
    {
        $this->prepareTestModuleConfigurations();

        $this->executeCommand([
            'command' => 'oe:module:activate-configured-modules',
        ]);

        $moduleStateService = $this->get(ModuleStateServiceInterface::class);

        $this->assertTrue(
            $moduleStateService->isActive('toActivate', 1)
        );

        $this->assertFalse(
            $moduleStateService->isActive('stayInactive', 1)
        );

        $this->assertTrue(
            $moduleStateService->isActive('toActivate', 2)
        );

        $this->assertFalse(
            $moduleStateService->isActive('stayInactive', 2)
        );
    }

    private function prepareTestModuleConfigurations(): void
    {
        $moduleToActivate = new ModuleConfiguration();
        $moduleToActivate
            ->setId('toActivate')
            ->setPath('any')
            ->setConfigured(true);

        $moduleToStayInactive = new ModuleConfiguration();
        $moduleToStayInactive
            ->setId('stayInactive')
            ->setPath('any')
            ->setConfigured(false);

        $shopConfiguration = new ShopConfiguration();
        $shopConfiguration->addModuleConfiguration($moduleToActivate);
        $shopConfiguration->addModuleConfiguration($moduleToStayInactive);

        $env = $this->get(ContextInterface::class)->getEnvironment();

        $shopConfigurationDao = $this->get(ShopConfigurationDaoInterface::class);
        $shopConfigurationDao->save($shopConfiguration, 1, $env);
        $shopConfigurationDao->save($shopConfiguration, 2, $env);
    }

    private function executeCommand(array $input): void
    {
        $app = $this->getApplication();

        $this->execute(
            $app,
            $this->get('oxid_esales.console.commands_provider.services_commands_provider'),
            new ArrayInput($input)
        );
    }
}
