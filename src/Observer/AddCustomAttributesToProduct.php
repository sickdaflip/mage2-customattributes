<?php
/**
 * FlipDev_CustomAttributes Observer - Add Custom Attributes to Product
 *
 * Adds virtual custom attributes to single product loads
 *
 * @category  FlipDev
 * @package   FlipDev_CustomAttributes
 * @author    Philipp Breitsprecher <philippbreitsprecher@gmail.com>
 * @license   MIT License
 */

declare(strict_types=1);

namespace FlipDev\CustomAttributes\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use FlipDev\CustomAttributes\Helper\Price as PriceHelper;
use FlipDev\CustomAttributes\Helper\Url as UrlHelper;
use FlipDev\CustomAttributes\Helper\Data as DataHelper;
use Psr\Log\LoggerInterface;

class AddCustomAttributesToProduct implements ObserverInterface
{
    /**
     * @var PriceHelper
     */
    private PriceHelper $priceHelper;

    /**
     * @var UrlHelper
     */
    private UrlHelper $urlHelper;

    /**
     * @var DataHelper
     */
    private DataHelper $dataHelper;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param PriceHelper $priceHelper
     * @param UrlHelper $urlHelper
     * @param DataHelper $dataHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        PriceHelper $priceHelper,
        UrlHelper $urlHelper,
        DataHelper $dataHelper,
        LoggerInterface $logger
    ) {
        $this->priceHelper = $priceHelper;
        $this->urlHelper = $urlHelper;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
    }

    /**
     * Add custom attributes to product
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        
        if (!$product || !$product->getId()) {
            return;
        }

        try {
            // Price attributes with tax
            $product->setData(
                DataHelper::ATTRIBUTE_PRICE_INCL_TAX,
                $this->priceHelper->getPriceInclTax($product)
            );
            
            $specialPriceInclTax = $this->priceHelper->getSpecialPriceInclTax($product);
            $product->setData(
                DataHelper::ATTRIBUTE_SPECIAL_PRICE_INCL_TAX,
                $specialPriceInclTax
            );
            
            $product->setData(
                DataHelper::ATTRIBUTE_FINAL_PRICE_INCL_TAX,
                $this->priceHelper->getFinalPriceInclTax($product)
            );
            
            // URL attributes
            $product->setData(
                DataHelper::ATTRIBUTE_PRODUCT_URL,
                $this->urlHelper->getProductUrl($product)
            );
            
            $product->setData(
                DataHelper::ATTRIBUTE_IMAGE_URL,
                $this->urlHelper->getImageUrl($product)
            );
            
            $product->setData(
                DataHelper::ATTRIBUTE_CATEGORY_PATH,
                $this->urlHelper->getCategoryPath($product)
            );
            
        } catch (\Exception $e) {
            $this->logger->error(
                'FlipDev_CustomAttributes: Error adding attributes to product: ' . $e->getMessage()
            );
        }
    }
}
