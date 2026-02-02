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
use Psr\Log\LoggerInterface;

class AddCustomAttributesToExportSource
{
    /**
     * @var DataHelper
     */
    private DataHelper $dataHelper;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param DataHelper $dataHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        DataHelper $dataHelper,
        LoggerInterface $logger
    ) {
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
    }

    /**
     * Add custom virtual attributes to the export fields list (dropdown)
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

    /**
     * Add custom virtual attributes to the CSV header columns respecting mapping order
     *
     * Plugin for Magento\CatalogImportExport\Model\Export\Product::_getHeaderColumns()
     * This ensures our fdca_* columns appear at the correct positions with correct names
     *
     * @param mixed $subject
     * @param array $result
     * @return array
     */
    public function after_getHeaderColumns($subject, array $result): array
    {
        $customAttributes = $this->dataHelper->getCustomAttributeCodes();

        // Get the job's configuration (list + replace_code for column names)
        $config = $this->getExportConfigFromSubject($subject);

        if (empty($config['list'])) {
            // No config found - append at end as fallback
            return array_values(array_unique(array_merge($result, $customAttributes)));
        }

        // Build ordered header with mapped column names
        $orderedHeader = $this->buildOrderedHeader($result, $customAttributes, $config);

        return $orderedHeader;
    }

    /**
     * Extract export configuration from subject
     *
     * @param mixed $subject
     * @return array Returns ['list' => [...], 'replace_code' => [...]]
     */
    private function getExportConfigFromSubject($subject): array
    {
        $config = ['list' => [], 'replace_code' => []];

        try {
            if (!method_exists($subject, 'getParameters')) {
                return $config;
            }

            $parameters = $subject->getParameters();

            // Get the ordered list of system attribute codes
            if (isset($parameters['list']) && is_array($parameters['list'])) {
                $config['list'] = $parameters['list'];
            }

            // Get the export column names (parallel array to list)
            if (isset($parameters['replace_code']) && is_array($parameters['replace_code'])) {
                $config['replace_code'] = $parameters['replace_code'];
            }

        } catch (\Exception $e) {
            $this->logger->error('FlipDev_CustomAttributes: Could not get export config: ' . $e->getMessage());
        }

        return $config;
    }

    /**
     * Build header columns using export names from replace_code
     *
     * @param array $existingHeader
     * @param array $customAttributes
     * @param array $config Contains 'list' (system codes) and 'replace_code' (export names)
     * @return array
     */
    private function buildOrderedHeader(array $existingHeader, array $customAttributes, array $config): array
    {
        $list = $config['list'];
        $replaceCode = $config['replace_code'];

        // Build mapping from system code to export name
        // list and replace_code are parallel arrays
        $systemToExport = [];
        foreach ($list as $index => $systemCode) {
            if (is_string($systemCode)) {
                // Use the export name from replace_code if available, otherwise use system code
                $exportName = isset($replaceCode[$index]) && !empty($replaceCode[$index])
                    ? $replaceCode[$index]
                    : $systemCode;
                $systemToExport[$systemCode] = $exportName;
            }
        }

        // Build final header using export names in the order from list
        $finalHeader = [];
        foreach ($list as $systemCode) {
            if (is_string($systemCode) && isset($systemToExport[$systemCode])) {
                $finalHeader[] = $systemToExport[$systemCode];
            }
        }

        // Add any existing header columns not in our list (with their original names)
        foreach ($existingHeader as $col) {
            if (!in_array($col, $finalHeader) && !in_array($col, $list)) {
                $finalHeader[] = $col;
            }
        }

        // Add any custom attributes that weren't in the list (shouldn't happen if configured correctly)
        foreach ($customAttributes as $attr) {
            if (!isset($systemToExport[$attr]) && !in_array($attr, $finalHeader)) {
                $finalHeader[] = $attr;
            }
        }

        return array_values(array_unique($finalHeader));
    }
}
