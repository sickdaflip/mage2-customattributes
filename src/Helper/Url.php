<?php
/**
 * FlipDev_CustomAttributes URL Helper
 *
 * Generates full product and image URLs for export feeds
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
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Image as ImageHelper;

class Url extends AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ImageHelper
     */
    private ImageHelper $imageHelper;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ImageHelper $imageHelper
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ImageHelper $imageHelper
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->imageHelper = $imageHelper;
    }

    /**
     * Get full product URL
     *
     * @param Product $product
     * @return string
     */
    public function getProductUrl(Product $product): string
    {
        try {
            $url = $product->getProductUrl();
            
            // If URL is already absolute, return it
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            
            // Get store base URL and append product URL key
            $store = $this->storeManager->getStore($product->getStoreId());
            $baseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
            $urlKey = $product->getUrlKey();
            
            if (!$urlKey) {
                return $url;
            }
            
            // Build full URL
            return rtrim($baseUrl, '/') . 'Url.php/' . $urlKey . '.html';
            
        } catch (\Exception $e) {
            $this->_logger->error('FlipDev_CustomAttributes: URL generation failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get full image URL
     *
     * @param Product $product
     * @return string
     */
    public function getImageUrl(Product $product): string
    {
        try {
            $image = $product->getImage();
            
            if (!$image || $image === 'no_selection') {
                return '';
            }
            
            // Get full media URL
            $store = $this->storeManager->getStore($product->getStoreId());
            $mediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            
            // Build full image URL
            return rtrim($mediaUrl, '/') . '/catalog/product' . $image;
            
        } catch (\Exception $e) {
            $this->_logger->error('FlipDev_CustomAttributes: Image URL generation failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get category path for product (for Idealo categoryPath field)
     *
     * @param Product $product
     * @return string
     */
    public function getCategoryPath(Product $product): string
    {
        try {
            $categoryIds = $product->getCategoryIds();
            
            if (empty($categoryIds)) {
                return '';
            }
            
            // Get the first (or deepest) category
            $categoryId = end($categoryIds);
            
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $category = $objectManager->create(\Magento\Catalog\Model\Category::class)->load($categoryId);
            
            if (!$category->getId()) {
                return '';
            }
            
            // Build path from root to current category
            $pathIds = explode('/', $category->getPath());
            $pathNames = [];
            
            foreach ($pathIds as $pathId) {
                if ($pathId == 1 || $pathId == 2) {
                    // Skip root and default category
                    continue;
                }
                
                $pathCategory = $objectManager->create(\Magento\Catalog\Model\Category::class)->load($pathId);
                
                if ($pathCategory->getId()) {
                    $pathNames[] = $pathCategory->getName();
                }
            }
            
            // Return path separated by " > " (Idealo format)
            return implode(' > ', $pathNames);
            
        } catch (\Exception $e) {
            $this->_logger->error('FlipDev_CustomAttributes: Category path generation failed: ' . $e->getMessage());
            return '';
        }
    }
}
