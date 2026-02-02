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
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param DataHelper $dataHelper
     * @param PriceHelper $priceHelper
     * @param UrlHelper $urlHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        DataHelper $dataHelper,
        PriceHelper $priceHelper,
        UrlHelper $urlHelper,
        LoggerInterface $logger
    ) {
        $this->dataHelper = $dataHelper;
        $this->priceHelper = $priceHelper;
        $this->urlHelper = $urlHelper;
        $this->logger = $logger;
    }

    /**
     * After collecting raw data, add our virtual attributes
     *
     * @param FirebearProductExport $subject
     * @param array $result
     * @return array
     */
    public function afterCollectRawData(FirebearProductExport $subject, array $result): array
    {
        foreach ($result as $itemId => $itemByStore) {
            foreach ($itemByStore as $storeId => $item) {
                if (!is_object($item)) {
                    continue;
                }

                try {
                    // Calculate and set our virtual attributes on the product
                    $item->setData(
                        DataHelper::ATTRIBUTE_PRICE_INCL_TAX,
                        $this->priceHelper->getPriceInclTax($item)
                    );

                    $item->setData(
                        DataHelper::ATTRIBUTE_SPECIAL_PRICE_INCL_TAX,
                        $this->priceHelper->getSpecialPriceInclTax($item)
                    );

                    $item->setData(
                        DataHelper::ATTRIBUTE_FINAL_PRICE_INCL_TAX,
                        $this->priceHelper->getFinalPriceInclTax($item)
                    );

                    $item->setData(
                        DataHelper::ATTRIBUTE_PRODUCT_URL,
                        $this->urlHelper->getProductUrl($item)
                    );

                    $item->setData(
                        DataHelper::ATTRIBUTE_IMAGE_URL,
                        $this->urlHelper->getImageUrl($item)
                    );

                    $item->setData(
                        DataHelper::ATTRIBUTE_CATEGORY_PATH,
                        $this->urlHelper->getCategoryPath($item)
                    );

                } catch (\Exception $e) {
                    $this->logger->error(
                        'FlipDev_CustomAttributes: Error in Firebear export plugin: ' . $e->getMessage()
                    );
                }
            }
        }

        return $result;
    }
}
