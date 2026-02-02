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
     * Extract mapping configuration from export subject
     *
     * @param mixed $subject
     * @return array
     */
    private function getMappingFromSubject($subject): array
    {
        try {
            // Try to get parameters from the export object
            if (method_exists($subject, 'getParameters')) {
                $parameters = $subject->getParameters();

                // Debug: Log the parameter keys to understand the structure
                $this->logger->info('FlipDev_CustomAttributes: Parameter keys: ' . implode(', ', array_keys($parameters)));

                // Check all possible locations for mapping data
                $possibleKeys = ['maps', 'map', 'mapping', 'export_filter', 'list'];
                foreach ($possibleKeys as $key) {
                    if (isset($parameters[$key]) && is_array($parameters[$key])) {
                        $this->logger->info('FlipDev_CustomAttributes: Found mapping in parameters[' . $key . ']');
                        return $parameters[$key];
                    }
                    if (isset($parameters['behavior_data'][$key]) && is_array($parameters['behavior_data'][$key])) {
                        $this->logger->info('FlipDev_CustomAttributes: Found mapping in behavior_data[' . $key . ']');
                        return $parameters['behavior_data'][$key];
                    }
                }

                // Debug: Log behavior_data keys if it exists
                if (isset($parameters['behavior_data']) && is_array($parameters['behavior_data'])) {
                    $this->logger->info('FlipDev_CustomAttributes: behavior_data keys: ' . implode(', ', array_keys($parameters['behavior_data'])));
                }
            }

            // Try alternative method to get mapping
            if (method_exists($subject, 'getMaps')) {
                $maps = $subject->getMaps();
                if (!empty($maps)) {
                    $this->logger->info('FlipDev_CustomAttributes: Got mapping from getMaps()');
                    return $maps;
                }
            }

            $this->logger->info('FlipDev_CustomAttributes: No mapping found');
        } catch (\Exception $e) {
            $this->logger->error('FlipDev_CustomAttributes: Could not get mapping: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Build header columns in the order specified by mapping
     *
     * @param array $existingHeader
     * @param array $customAttributes
     * @param array $mapping
     * @return array
     */
    private function buildOrderedHeader(array $existingHeader, array $customAttributes, array $mapping): array
    {
        // Create a map of system attribute code to its position in the mapping
        $positionMap = [];
        $mappedSystemCodes = [];

        foreach ($mapping as $index => $mapItem) {
            $systemCode = $mapItem['system'] ?? ($mapItem['source'] ?? '');
            if (!empty($systemCode)) {
                $positionMap[$systemCode] = (int)$index;
                $mappedSystemCodes[] = $systemCode;
            }
        }

        // Check which custom attributes are in the mapping
        $customInMapping = [];
        $customNotInMapping = [];

        foreach ($customAttributes as $attr) {
            if (isset($positionMap[$attr])) {
                $customInMapping[$attr] = $positionMap[$attr];
            } else {
                $customNotInMapping[] = $attr;
            }
        }

        // If none of our custom attributes are in the mapping, just append
        if (empty($customInMapping)) {
            $result = $existingHeader;
            foreach ($customNotInMapping as $attr) {
                if (!in_array($attr, $result)) {
                    $result[] = $attr;
                }
            }
            return array_values($result);
        }

        // Build header in mapping order
        // Start with all mapped system codes in order
        $orderedByMapping = [];
        foreach ($mapping as $mapItem) {
            $systemCode = $mapItem['system'] ?? ($mapItem['source'] ?? '');
            if (!empty($systemCode)) {
                $orderedByMapping[] = $systemCode;
            }
        }

        // Add any existing header columns that are not in the mapping (at the end)
        $finalHeader = $orderedByMapping;
        foreach ($existingHeader as $col) {
            if (!in_array($col, $finalHeader)) {
                $finalHeader[] = $col;
            }
        }

        // Add custom attributes not in mapping at the end
        foreach ($customNotInMapping as $attr) {
            if (!in_array($attr, $finalHeader)) {
                $finalHeader[] = $attr;
            }
        }

        return array_values(array_unique($finalHeader));
    }
}
