<?php
/**
 * FlipDev_CustomAttributes Plugin for Firebear Export
 *
 * Injects virtual custom attributes into Firebear product export data
 *
 * @category  FlipDev
 * @package   FlipDev_CustomAttributes
 * @author    Philipp Breitsprecher <philippbreitsprecher@gmail.com>
 * @license   MIT License
 */

declare(strict_types=1);

namespace FlipDev\CustomAttributes\Plugin;

use Firebear\ImportExport\Model\Export\Product as FirebearProductExport;
use FlipDev\CustomAttributes\Helper\Data as DataHelper;
use FlipDev\CustomAttributes\Helper\Price as PriceHelper;
use FlipDev\CustomAttributes\Helper\Url as UrlHelper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Psr\Log\LoggerInterface;

class AddCustomAttributesToFirebearExport
{
    /**
     * @var DataHelper
     */
    private DataHelper $dataHelper;

    /**
     * @var PriceHelper
     */
    private PriceHelper $priceHelper;

    /**
     * @var UrlHelper
     */
    private UrlHelper $urlHelper;

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param DataHelper $dataHelper
     * @param PriceHelper $priceHelper
     * @param UrlHelper $urlHelper
     * @param ProductCollectionFactory $productCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        DataHelper $dataHelper,
        PriceHelper $priceHelper,
        UrlHelper $urlHelper,
        ProductCollectionFactory $productCollectionFactory,
        LoggerInterface $logger
    ) {
        $this->dataHelper = $dataHelper;
        $this->priceHelper = $priceHelper;
        $this->urlHelper = $urlHelper;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * After export data is collected, inject our virtual attribute values
     *
     * @param FirebearProductExport $subject
     * @param array $result
     * @return array
     */
    public function afterGetExportData(FirebearProductExport $subject, array $result): array
    {
        if (empty($result)) {
            return $result;
        }

        try {
            // Get the job configuration (which attributes are configured and their mapped names)
            $jobConfig = $this->getJobConfig($subject);
            $configuredAttributes = $jobConfig['list'];
            $columnMapping = $jobConfig['mapping'];

            // Only proceed if at least one of our custom attributes is configured
            $customAttributes = $this->dataHelper->getCustomAttributeCodes();
            $activeAttributes = array_intersect($customAttributes, $configuredAttributes);

            if (empty($activeAttributes)) {
                return $result;
            }

            // Collect all SKUs from export data
            // Note: The data may use mapped column names (e.g., 'aid' instead of 'sku')
            $skuColumnName = $columnMapping['sku'] ?? 'sku';

            $skus = [];
            foreach ($result as $row) {
                // Try mapped name first, then fall back to 'sku'
                $skuValue = $row[$skuColumnName] ?? ($row['sku'] ?? null);
                if (!empty($skuValue)) {
                    $skus[$skuValue] = true;
                }
            }

            if (empty($skus)) {
                return $result;
            }

            // Load all products at once for efficiency
            $productData = $this->loadProductData(array_keys($skus));

            // Add our virtual attributes to each export row
            // Only add attributes that are configured in the job
            foreach ($result as $index => $row) {
                // Use mapped column name to find SKU
                $skuValue = $row[$skuColumnName] ?? ($row['sku'] ?? null);
                if (empty($skuValue) || !isset($productData[$skuValue])) {
                    continue;
                }

                $data = $productData[$skuValue];

                // Only add data for attributes that are configured in the job
                if (in_array(DataHelper::ATTRIBUTE_PRICE_INCL_TAX, $configuredAttributes)) {
                    $key = $columnMapping[DataHelper::ATTRIBUTE_PRICE_INCL_TAX] ?? DataHelper::ATTRIBUTE_PRICE_INCL_TAX;
                    $result[$index][$key] = $data['price_incl_tax'] ?? '';
                }
                if (in_array(DataHelper::ATTRIBUTE_SPECIAL_PRICE_INCL_TAX, $configuredAttributes)) {
                    $key = $columnMapping[DataHelper::ATTRIBUTE_SPECIAL_PRICE_INCL_TAX] ?? DataHelper::ATTRIBUTE_SPECIAL_PRICE_INCL_TAX;
                    $result[$index][$key] = $data['special_price_incl_tax'] ?? '';
                }
                if (in_array(DataHelper::ATTRIBUTE_FINAL_PRICE_INCL_TAX, $configuredAttributes)) {
                    $key = $columnMapping[DataHelper::ATTRIBUTE_FINAL_PRICE_INCL_TAX] ?? DataHelper::ATTRIBUTE_FINAL_PRICE_INCL_TAX;
                    $result[$index][$key] = $data['final_price_incl_tax'] ?? '';
                }
                if (in_array(DataHelper::ATTRIBUTE_PRODUCT_URL, $configuredAttributes)) {
                    $key = $columnMapping[DataHelper::ATTRIBUTE_PRODUCT_URL] ?? DataHelper::ATTRIBUTE_PRODUCT_URL;
                    $result[$index][$key] = $data['product_url'] ?? '';
                }
                if (in_array(DataHelper::ATTRIBUTE_IMAGE_URL, $configuredAttributes)) {
                    $key = $columnMapping[DataHelper::ATTRIBUTE_IMAGE_URL] ?? DataHelper::ATTRIBUTE_IMAGE_URL;
                    $result[$index][$key] = $data['image_url'] ?? '';
                }
                if (in_array(DataHelper::ATTRIBUTE_CATEGORY_PATH, $configuredAttributes)) {
                    $key = $columnMapping[DataHelper::ATTRIBUTE_CATEGORY_PATH] ?? DataHelper::ATTRIBUTE_CATEGORY_PATH;
                    $result[$index][$key] = $data['category_path'] ?? '';
                }
            }

        } catch (\Exception $e) {
            $this->logger->error(
                'FlipDev_CustomAttributes: Error in afterGetExportData: ' . $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Get job configuration including list of attributes and column mapping
     *
     * @param FirebearProductExport $subject
     * @return array ['list' => [...], 'mapping' => [...]]
     */
    private function getJobConfig(FirebearProductExport $subject): array
    {
        $config = ['list' => [], 'mapping' => []];

        try {
            if (!method_exists($subject, 'getParameters')) {
                return $config;
            }

            $parameters = $subject->getParameters();
            $list = $parameters['list'] ?? [];
            $replaceCode = $parameters['replace_code'] ?? [];

            // Store the list of configured attributes
            $config['list'] = array_filter($list, 'is_string');

            // Build mapping from system code to export name
            foreach ($list as $index => $systemCode) {
                if (is_string($systemCode) && isset($replaceCode[$index]) && !empty($replaceCode[$index])) {
                    $config['mapping'][$systemCode] = $replaceCode[$index];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('FlipDev_CustomAttributes: Could not get job config: ' . $e->getMessage());
        }

        return $config;
    }

    /**
     * Load product data for all SKUs and calculate virtual attributes
     *
     * @param array $skus
     * @return array
     */
    private function loadProductData(array $skus): array
    {
        $productData = [];

        // Load products in batches for memory efficiency
        $batchSize = 500;
        $skuBatches = array_chunk($skus, $batchSize);

        foreach ($skuBatches as $skuBatch) {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect(['price', 'special_price', 'special_from_date', 'special_to_date', 'tax_class_id', 'url_key', 'image'])
                ->addAttributeToFilter('sku', ['in' => $skuBatch])
                ->addCategoryIds();

            foreach ($collection as $product) {
                $sku = $product->getSku();

                try {
                    $productData[$sku] = [
                        'price_incl_tax' => $this->priceHelper->getPriceInclTax($product),
                        'special_price_incl_tax' => $this->priceHelper->getSpecialPriceInclTax($product),
                        'final_price_incl_tax' => $this->priceHelper->getFinalPriceInclTax($product),
                        'product_url' => $this->urlHelper->getProductUrl($product),
                        'image_url' => $this->urlHelper->getImageUrl($product),
                        'category_path' => $this->urlHelper->getCategoryPath($product),
                    ];
                } catch (\Exception $e) {
                    $this->logger->error(
                        'FlipDev_CustomAttributes: Error calculating values for SKU ' . $sku . ': ' . $e->getMessage()
                    );
                    $productData[$sku] = [];
                }
            }

            // Clear collection to free memory
            $collection->clear();
        }

        return $productData;
    }
}
