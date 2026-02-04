<?php
/**
 * FlipDev_CustomAttributes URL Helper
 *
 * Generates full product and image URLs for export feeds
 * Optimized with category caching for bulk exports
 *
 * @category  FlipDev
 * @package   FlipDev_CustomAttributes
 * @author    Philipp Breitsprecher <philippbreitsprecher@gmail.com>
 * @license   MIT License
 */

declare(strict_types=1);

namespace FlipDev\CustomAttributes\Helper;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

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
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @var UrlFinderInterface
     */
    private UrlFinderInterface $urlFinder;

    /**
     * Category name cache to avoid repeated DB queries
     * @var array<int, string>
     */
    private array $categoryNameCache = [];

    /**
     * Category path cache (category_id => full path string)
     * @var array<int, string>
     */
    private array $categoryPathCache = [];

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ImageHelper $imageHelper
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param UrlFinderInterface $urlFinder
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ImageHelper $imageHelper,
        CategoryCollectionFactory $categoryCollectionFactory,
        UrlFinderInterface $urlFinder
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->imageHelper = $imageHelper;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->urlFinder = $urlFinder;
    }

    /**
     * Get full product URL using URL rewrites (works in admin/CLI context)
     *
     * @param Product $product
     * @param int|null $storeId
     * @param bool $appendStoreCode Whether to append ?___store=STORE_CODE parameter
     * @return string
     */
    public function getProductUrl(Product $product, ?int $storeId = null, bool $appendStoreCode = false): string
    {
        try {
            $storeId = $storeId ?? (int) $product->getStoreId();

            // If storeId is 0 (admin), get the default store
            if ($storeId === 0) {
                $storeId = (int) $this->storeManager->getDefaultStoreView()->getId();
            }

            $store = $this->storeManager->getStore($storeId);
            $baseUrl = rtrim($store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB), '/');

            // Try to get URL from URL rewrites (most reliable for frontend URLs)
            $rewrite = $this->urlFinder->findOneByData([
                UrlRewrite::ENTITY_ID => $product->getId(),
                UrlRewrite::ENTITY_TYPE => \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator::ENTITY_TYPE,
                UrlRewrite::STORE_ID => $storeId,
            ]);

            $url = '';
            if ($rewrite) {
                $url = $baseUrl . '/' . $rewrite->getRequestPath();
            } else {
                // Fallback: build URL from url_key
                $urlKey = $product->getUrlKey();
                if ($urlKey) {
                    $suffix = $this->scopeConfig->getValue(
                        \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator::XML_PATH_PRODUCT_URL_SUFFIX,
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                        $storeId
                    );
                    $url = $baseUrl . '/' . $urlKey . ($suffix ?: '');
                } else {
                    // Last resort: use product ID
                    $url = $baseUrl . '/catalog/product/view/id/' . $product->getId();
                }
            }

            // Append store code parameter if requested (for multi-store setups like B2B/B2C)
            if ($appendStoreCode && !empty($url)) {
                $storeCode = $store->getCode();
                $url .= '?___store=' . $storeCode;
            }

            return $url;

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

            $store = $this->storeManager->getStore($product->getStoreId());
            $mediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

            return rtrim($mediaUrl, '/') . '/catalog/product' . $image;

        } catch (\Exception $e) {
            $this->_logger->error('FlipDev_CustomAttributes: Image URL generation failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get category path for product (for Idealo categoryPath field)
     * Optimized with caching for bulk exports
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

            // Get the deepest category (last one in the array)
            $categoryId = (int) end($categoryIds);

            // Check if we already have this path cached
            if (isset($this->categoryPathCache[$categoryId])) {
                return $this->categoryPathCache[$categoryId];
            }

            // Load category names we don't have yet
            $this->preloadCategoryNames($categoryIds);

            // Build the path using cached data
            $path = $this->buildCategoryPath($categoryId);

            // Cache the result
            $this->categoryPathCache[$categoryId] = $path;

            return $path;

        } catch (\Exception $e) {
            $this->_logger->error('FlipDev_CustomAttributes: Category path generation failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Preload category names into cache
     *
     * @param array $categoryIds
     * @return void
     */
    private function preloadCategoryNames(array $categoryIds): void
    {
        // Filter out already cached IDs
        $missingIds = array_filter($categoryIds, function ($id) {
            return !isset($this->categoryNameCache[(int) $id]);
        });

        if (empty($missingIds)) {
            return;
        }

        // Load all missing categories in one query
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'path'])
            ->addAttributeToFilter('entity_id', ['in' => $missingIds]);

        foreach ($collection as $category) {
            $this->categoryNameCache[(int) $category->getId()] = $category->getName();

            // Also cache parent category IDs from path for later use
            $pathIds = explode('/', $category->getPath());
            foreach ($pathIds as $pathId) {
                $pathIdInt = (int) $pathId;
                if ($pathIdInt > 2 && !isset($this->categoryNameCache[$pathIdInt])) {
                    // Mark as needing load
                    $this->categoryNameCache[$pathIdInt] = null;
                }
            }
        }

        // Load any parent categories we discovered from paths
        $this->loadMissingCategoryNames();
    }

    /**
     * Load category names that are marked as null (need loading)
     *
     * @return void
     */
    private function loadMissingCategoryNames(): void
    {
        $missingIds = [];
        foreach ($this->categoryNameCache as $id => $name) {
            if ($name === null) {
                $missingIds[] = $id;
            }
        }

        if (empty($missingIds)) {
            return;
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addAttributeToFilter('entity_id', ['in' => $missingIds]);

        foreach ($collection as $category) {
            $this->categoryNameCache[(int) $category->getId()] = $category->getName();
        }

        // Remove any still-null entries (categories that don't exist)
        $this->categoryNameCache = array_filter($this->categoryNameCache, function ($name) {
            return $name !== null;
        });
    }

    /**
     * Build category path string from cached data
     *
     * @param int $categoryId
     * @return string
     */
    private function buildCategoryPath(int $categoryId): string
    {
        // We need to get the path for this category
        // First, load this category if not in cache
        if (!isset($this->categoryNameCache[$categoryId])) {
            $this->preloadCategoryNames([$categoryId]);
        }

        // Get the category's path from a quick collection query
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('path')
            ->addAttributeToFilter('entity_id', $categoryId)
            ->setPageSize(1);

        $category = $collection->getFirstItem();

        if (!$category->getId()) {
            return '';
        }

        $pathIds = explode('/', $category->getPath());
        $pathNames = [];

        foreach ($pathIds as $pathId) {
            $pathIdInt = (int) $pathId;

            // Skip root (1) and default store root (2)
            if ($pathIdInt <= 2) {
                continue;
            }

            // Make sure we have this category name
            if (!isset($this->categoryNameCache[$pathIdInt])) {
                $this->preloadCategoryNames([$pathIdInt]);
            }

            if (isset($this->categoryNameCache[$pathIdInt])) {
                $pathNames[] = $this->categoryNameCache[$pathIdInt];
            }
        }

        return implode(' > ', $pathNames);
    }
}
