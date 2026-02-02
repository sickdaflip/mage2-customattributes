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
            // Collect all SKUs from export data
            $skus = [];
            foreach ($result as $row) {
                if (isset($row['sku']) && !empty($row['sku'])) {
                    $skus[$row['sku']] = true;
                }
            }

            if (empty($skus)) {
                return $result;
            }

            // Load all products at once for efficiency
            $productData = $this->loadProductData(array_keys($skus));

            // Add our virtual attributes to each export row
            foreach ($result as $index => $row) {
                if (!isset($row['sku']) || !isset($productData[$row['sku']])) {
                    continue;
                }

                $data = $productData[$row['sku']];

                // Add our virtual attribute values
                $result[$index][DataHelper::ATTRIBUTE_PRICE_INCL_TAX] = $data['price_incl_tax'] ?? '';
                $result[$index][DataHelper::ATTRIBUTE_SPECIAL_PRICE_INCL_TAX] = $data['special_price_incl_tax'] ?? '';
                $result[$index][DataHelper::ATTRIBUTE_FINAL_PRICE_INCL_TAX] = $data['final_price_incl_tax'] ?? '';
                $result[$index][DataHelper::ATTRIBUTE_PRODUCT_URL] = $data['product_url'] ?? '';
                $result[$index][DataHelper::ATTRIBUTE_IMAGE_URL] = $data['image_url'] ?? '';
                $result[$index][DataHelper::ATTRIBUTE_CATEGORY_PATH] = $data['category_path'] ?? '';
            }

        } catch (\Exception $e) {
            $this->logger->error(
                'FlipDev_CustomAttributes: Error in afterGetExportData: ' . $e->getMessage()
            );
        }

        return $result;
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
