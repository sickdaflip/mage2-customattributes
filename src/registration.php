<?php
/**
 * FlipDev_CustomAttributes Module Registration
 *
 * Adds virtual product attributes for price comparison feeds (Idealo, Billiger.de)
 *
 * @category  FlipDev
 * @package   FlipDev_CustomAttributes
 * @author    Philipp Breitsprecher <philippbreitsprecher@gmail.com>
 * @license   MIT License
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'FlipDev_CustomAttributes',
    __DIR__
);
