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
     * Add custom virtual attributes to the export fields list
     *
     * Plugin for Firebear\ImportExport\Model\Export\Product::getFieldsForExport()
     *
     * @param mixed $subject
     * @param array $result
     * @return array
     */
    public function afterGetFieldsForExport($subject, array $result): array
    {
        $customAttributes = $this->dataHelper->getCustomAttributeCodes();

        return array_unique(array_merge($result, $customAttributes));
    }
}
