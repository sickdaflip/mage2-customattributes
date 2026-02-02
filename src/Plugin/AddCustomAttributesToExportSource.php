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
     * This ensures our fdca_* columns appear at the correct positions based on the mapping
     *
     * @param mixed $subject
     * @param array $result
     * @return array
     */
    public function after_getHeaderColumns($subject, array $result): array
    {
        $customAttributes = $this->dataHelper->getCustomAttributeCodes();

        // Get the job's mapping configuration to determine column positions
        $mapping = $this->getMappingFromSubject($subject);

        if (empty($mapping)) {
            // No mapping found - append at end as fallback
            return array_values(array_unique(array_merge($result, $customAttributes)));
        }

        // Build ordered header based on mapping
        $orderedHeader = $this->buildOrderedHeader($result, $customAttributes, $mapping);

        return $orderedHeader;
    }

    /**
     * Extract column list configuration from export subject
     *
     * @param mixed $subject
     * @return array
     */
    private function getMappingFromSubject($subject): array
    {
        try {
            if (!method_exists($subject, 'getParameters')) {
                return [];
            }

            $parameters = $subject->getParameters();

            // The 'list' parameter contains the ordered list of attributes to export
            // This is where Firebear stores the column configuration with order
            if (isset($parameters['list']) && is_array($parameters['list'])) {
                $list = $parameters['list'];

                // Log first item structure for debugging
                if (!empty($list)) {
                    $firstItem = reset($list);
                    $this->logger->info('FlipDev_CustomAttributes: list item structure: ' . json_encode($firstItem));
                }

                return $list;
            }

            // Fallback: check behavior_data for maps
            if (isset($parameters['behavior_data']['maps']) && is_array($parameters['behavior_data']['maps'])) {
                return $parameters['behavior_data']['maps'];
            }

            $this->logger->info('FlipDev_CustomAttributes: No column list found in parameters');
        } catch (\Exception $e) {
            $this->logger->error('FlipDev_CustomAttributes: Could not get mapping: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Build header columns in the order specified by the list configuration
     *
     * @param array $existingHeader
     * @param array $customAttributes
     * @param array $list The Firebear column list configuration
     * @return array
     */
    private function buildOrderedHeader(array $existingHeader, array $customAttributes, array $list): array
    {
        // Extract attribute codes from the list in order
        // Firebear list structure can be:
        // - Simple: ['attr1', 'attr2', ...] (strings)
        // - Complex: [['system' => 'attr1', 'custom' => 'Header1'], ...]
        // - Or: [['attribute' => 'attr1'], ...]
        $orderedCodes = [];

        foreach ($list as $index => $item) {
            $code = null;

            if (is_string($item)) {
                // Simple string format
                $code = $item;
            } elseif (is_array($item)) {
                // Object format - try different possible keys
                $code = $item['system'] ?? $item['attribute'] ?? $item['source'] ?? $item['code'] ?? null;

                // If still null and item has numeric key 0, it might be ['attr_code']
                if ($code === null && isset($item[0])) {
                    $code = $item[0];
                }
            }

            if (!empty($code) && is_string($code)) {
                $orderedCodes[] = $code;
            }
        }

        // If we couldn't extract any codes, fall back to appending
        if (empty($orderedCodes)) {
            $this->logger->info('FlipDev_CustomAttributes: Could not extract attribute codes from list');
            $result = $existingHeader;
            foreach ($customAttributes as $attr) {
                if (!in_array($attr, $result)) {
                    $result[] = $attr;
                }
            }
            return array_values($result);
        }

        $this->logger->info('FlipDev_CustomAttributes: Ordered codes from list: ' . implode(', ', array_slice($orderedCodes, 0, 10)) . '...');

        // Build final header using the order from list
        // The list defines the complete column order
        $finalHeader = [];

        foreach ($orderedCodes as $code) {
            $finalHeader[] = $code;
        }

        // Add any existing header columns not in the list (shouldn't happen normally)
        foreach ($existingHeader as $col) {
            if (!in_array($col, $finalHeader)) {
                $finalHeader[] = $col;
            }
        }

        // Add any custom attributes that weren't in the list
        foreach ($customAttributes as $attr) {
            if (!in_array($attr, $finalHeader)) {
                $finalHeader[] = $attr;
            }
        }

        return array_values(array_unique($finalHeader));
    }
}
