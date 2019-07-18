<?php declare(strict_types=1);
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Tests\Integration\Internal\Smarty\Bridge;

use OxidEsales\EshopCommunity\Internal\Application\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Smarty\Bridge\SmartyTemplateRendererBridge;
use OxidEsales\EshopCommunity\Internal\Templating\TemplateRendererInterface;

class SmartyTemplateRendererBridgeTest extends \PHPUnit\Framework\TestCase
{
    public function testSetGetEngine()
    {
        $smarty = new \Smarty();
        $renderer = ContainerFactory::getInstance()->getContainer()->get(TemplateRendererInterface::class);
        $bridge = new SmartyTemplateRendererBridge($renderer);
        $bridge->setEngine($smarty);

        $this->assertEquals($smarty, $bridge->getEngine());
    }
}
