<?php
/**
 * FlipDev_CustomAttributes Price Helper
 *
 * Calculates prices including tax based on product tax class
 *
 * @category  FlipDev
 * @package   FlipDev_CustomAttributes
 * @author    Philipp Breitsprecher <philippbreitsprecher@gmail.com>
 * @license   MIT License
 */

declare(strict_types=1);

namespace FlipDev\CustomAttributes\Helper;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Magento\Store\Model\StoreManagerInterface;

class Price extends AbstractHelper
{
    /**
     * @var TaxCalculation
     */
    private TaxCalculation $taxCalculation;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Context $context
     * @param TaxCalculation $taxCalculation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        TaxCalculation $taxCalculation,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->taxCalculation = $taxCalculation;
        $this->storeManager = $storeManager;
    }

    /**
     * Get tax rate for product
     *
     * @param Product $product
     * @return float
     */
    public function getTaxRate(Product $product): float
    {
        try {
            $store = $this->storeManager->getStore($product->getStoreId());
            $taxRateRequest = $this->taxCalculation->getRateRequest(
                null,
                null,
                null,
                $store
            );
            $taxRateRequest->setProductClassId($product->getTaxClassId());
            
            return (float) $this->taxCalculation->getRate($taxRateRequest);
        } catch (\Exception $e) {
            $this->_logger->error('FlipDev_CustomAttributes: Tax calculation failed: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate price including tax
     *
     * @param float $price
     * @param float $taxRate
     * @return float
     */
    public function calculatePriceInclTax(float $price, float $taxRate): float
    {
        return round($price * (1 + $taxRate / 100), 2);
    }

    /**
     * Get price including tax for product
     *
     * @param Product $product
     * @return float
     */
    public function getPriceInclTax(Product $product): float
    {
        $price = (float) $product->getPrice();
        $taxRate = $this->getTaxRate($product);
        
        return $this->calculatePriceInclTax($price, $taxRate);
    }

    /**
     * Get special price including tax for product
     *
     * @param Product $product
     * @return float|null
     */
    public function getSpecialPriceInclTax(Product $product): ?float
    {
        $specialPrice = $product->getSpecialPrice();
        
        if (!$specialPrice || !$this->isSpecialPriceActive($product)) {
            return null;
        }
        
        $taxRate = $this->getTaxRate($product);
        
        return $this->calculatePriceInclTax((float) $specialPrice, $taxRate);
    }

    /**
     * Get final price including tax (uses special price if active)
     *
     * @param Product $product
     * @return float
     */
    public function getFinalPriceInclTax(Product $product): float
    {
        $specialPrice = $this->getSpecialPriceInclTax($product);
        
        if ($specialPrice !== null) {
            return $specialPrice;
        }
        
        return $this->getPriceInclTax($product);
    }

    /**
     * Check if special price is currently active
     *
     * @param Product $product
     * @return bool
     */
    private function isSpecialPriceActive(Product $product): bool
    {
        $specialFromDate = $product->getSpecialFromDate();
        $specialToDate = $product->getSpecialToDate();
        $now = time();
        
        if ($specialFromDate && strtotime($specialFromDate) > $now) {
            return false;
        }
        
        if ($specialToDate && strtotime($specialToDate) < $now) {
            return false;
        }
        
        return true;
    }
}
