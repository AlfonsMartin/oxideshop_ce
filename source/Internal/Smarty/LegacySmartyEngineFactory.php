<?php declare(strict_types=1);
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Internal\Smarty;

use OxidEsales\EshopCommunity\Internal\Adapter\ShopAdapterInterface;
use OxidEsales\EshopCommunity\Internal\Smarty\Bridge\SmartyEngineBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Templating\TemplateEngineFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Templating\TemplateEngineInterface;

/**
 * Class LegacySmartyEngineFactory
 * @package OxidEsales\EshopCommunity\Internal\Smarty
 */
class LegacySmartyEngineFactory implements TemplateEngineFactoryInterface
{
    /**
     * @var ShopAdapterInterface
     */
    private $shopAdapter;

    /**
     * @var SmartyEngineBridgeInterface
     */
    private $smartyBridge;

    /**
     * LegacySmartyEngineFactory constructor.
     *
     * @param ShopAdapterInterface        $shopAdapter
     * @param SmartyEngineBridgeInterface $smartyBridge
     */
    public function __construct(ShopAdapterInterface $shopAdapter, SmartyEngineBridgeInterface $smartyBridge)
    {
        $this->shopAdapter = $shopAdapter;
        $this->smartyBridge = $smartyBridge;
    }

    /**
     * @return TemplateEngineInterface
     */
    public function getTemplateEngine(): TemplateEngineInterface
    {
        $smarty = $this->shopAdapter->getSmartyInstance();

        //TODO Event for smarty object configuration

        return new LegacySmartyEngine($smarty, $this->smartyBridge);
    }
}
