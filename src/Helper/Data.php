<?php
/**
 * FlipDev_CustomAttributes Data Helper
 *
 * Main helper class for module configuration and utilities
 *
 * @category  FlipDev
 * @package   FlipDev_CustomAttributes
 * @author    Philipp Breitsprecher <philippbreitsprecher@gmail.com>
 * @license   MIT License
 */

declare(strict_types=1);

namespace FlipDev\CustomAttributes\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
    /**
     * Custom attribute codes that this module provides
     * Prefix: fdca_ (FlipDev CustomAttributes)
     */
    public const ATTRIBUTE_PRICE_INCL_TAX = 'fdca_price_incl_tax';
    public const ATTRIBUTE_SPECIAL_PRICE_INCL_TAX = 'fdca_special_price_incl_tax';
    public const ATTRIBUTE_FINAL_PRICE_INCL_TAX = 'fdca_final_price_incl_tax';
    public const ATTRIBUTE_PRODUCT_URL = 'fdca_product_url';
    public const ATTRIBUTE_IMAGE_URL = 'fdca_image_url';
    public const ATTRIBUTE_CATEGORY_PATH = 'fdca_category_path';

    /**
     * Get all custom attribute codes
     *
     * @return array
     */
    public function getCustomAttributeCodes(): array
    {
        return [
            self::ATTRIBUTE_PRICE_INCL_TAX,
            self::ATTRIBUTE_SPECIAL_PRICE_INCL_TAX,
            self::ATTRIBUTE_FINAL_PRICE_INCL_TAX,
            self::ATTRIBUTE_PRODUCT_URL,
            self::ATTRIBUTE_IMAGE_URL,
            self::ATTRIBUTE_CATEGORY_PATH,
        ];
    }
}
