<?php
/**
 * FlipDev_CustomAttributes Plugin
 *
 * Adds virtual custom attributes to Firebear export attribute dropdown
 *
 * @category  FlipDev
 * @package   FlipDev_CustomAttributes
 * @author    Philipp Breitsprecher <philippbreitsprecher@gmail.com>
 * @license   MIT License
 */

declare(strict_types=1);

namespace FlipDev\CustomAttributes\Plugin;

use FlipDev\CustomAttributes\Helper\Data as DataHelper;

class AddCustomAttributesToExportSource
{
    /**
     * @var DataHelper
     */
    private DataHelper $dataHelper;

    /**
     * @param DataHelper $dataHelper
     */
    public function __construct(DataHelper $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    /**
     * Add custom virtual attributes to the export attribute list
     *
     * @param mixed $subject
     * @param array $result
     * @return array
     */
    public function afterToOptionArray($subject, array $result): array
    {
        $customAttributes = [
            [
                'value' => DataHelper::ATTRIBUTE_PRICE_INCL_TAX,
                'label' => __('Price Incl. Tax (FlipDev)')
            ],
            [
                'value' => DataHelper::ATTRIBUTE_SPECIAL_PRICE_INCL_TAX,
                'label' => __('Special Price Incl. Tax (FlipDev)')
            ],
            [
                'value' => DataHelper::ATTRIBUTE_FINAL_PRICE_INCL_TAX,
                'label' => __('Final Price Incl. Tax (FlipDev)')
            ],
            [
                'value' => DataHelper::ATTRIBUTE_PRODUCT_URL,
                'label' => __('Product URL (FlipDev)')
            ],
            [
                'value' => DataHelper::ATTRIBUTE_IMAGE_URL,
                'label' => __('Image URL (FlipDev)')
            ],
            [
                'value' => DataHelper::ATTRIBUTE_CATEGORY_PATH,
                'label' => __('Category Path (FlipDev)')
            ],
        ];

        return array_merge($result, $customAttributes);
    }
}
