<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Pipeline\Provider;

use Angeo\LlmsTxt\Api\Data\EntityRecordInterface;
use Angeo\LlmsTxt\Api\EntityProviderInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Data\EntityRecord;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;

/**
 * THE single catalog pass: each product is loaded and sanitized exactly once;
 * the resulting record is rendered into every enabled format. Combined with
 * the 3.1.1 SQL-level stock filter and price-index pricing this is the core
 * win of single-pass mode (legacy mode iterates the catalog once per format).
 *
 * @since 3.2.0
 */
class ProductEntityProvider implements EntityProviderInterface
{
    /** Max of legacy short maxes: jsonl 2000, full 5000 → sanitize once at 5000. */
    public const SHORT_MAX = 5000;
    /** Max of legacy description maxes: jsonl 5000, full 5000. */
    public const DESC_MAX  = 5000;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SanitizerInterface $sanitizer,
        private readonly UrlResolverInterface $urlResolver,
        private readonly StockHelper $stockHelper,
        private readonly Config $config
    ) {
    }

    public function isApplicable(OutputContextInterface $context): bool
    {
        return $this->config->isProductsIncluded($context->getStore());
    }

    public function provide(OutputContextInterface $context): iterable
    {
        $context->setShared(OutputContextInterface::SHARED_ENTITY_TYPE, 'product');

        $store     = $context->getStore();
        $storeId   = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        $pageSize  = $this->config->getCollectionPageSize($store);
        $limit     = $this->config->getProductLimit($store);
        $excludeOos = $this->config->isExcludeOutOfStock($store);
        $lastId    = 0;
        $emitted   = 0;

        while (true) {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToSelect([
                'sku', 'name', 'price', 'short_description', 'description', 'url_key',
            ]);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', [
                'in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH,
                ],
            ]);
            $collection->addAttributeToFilter('entity_id', ['gt' => $lastId]);
            $collection->setOrder('entity_id', 'ASC');
            $collection->setPageSize($pageSize);
            $collection->setCurPage(1);
            $collection->addPriceData($context->getCustomerGroupId(), $websiteId);

            if ($excludeOos) {
                $this->stockHelper->addIsInStockFilterToCollection($collection);
            }

            $hasRows = false;
            foreach ($collection as $product) {
                $hasRows = true;
                $lastId  = (int) $product->getId();

                $url = $this->urlResolver->resolve(
                    UrlResolverInterface::ENTITY_PRODUCT,
                    (int) $product->getId(),
                    $storeId
                );
                if ($url === null) {
                    continue;
                }

                $rawShort = (string) $product->getShortDescription();
                $rawDesc  = (string) $product->getDescription();

                // Sanitize ONCE per field; dedupe identical raw inputs.
                $short = $this->sanitizer->sanitize($rawShort, $context, self::SHORT_MAX);
                $desc  = ($rawDesc === $rawShort)
                    ? $short
                    : $this->sanitizer->sanitize($rawDesc, $context, self::DESC_MAX);

                yield new EntityRecord(
                    type: EntityRecordInterface::TYPE_PRODUCT,
                    entityId: (int) $product->getId(),
                    name: trim((string) $product->getName()),
                    url: $url,
                    content: $desc,
                    shortContent: $short,
                    sku: (string) $product->getSku(),
                    price: $this->resolvePrice($product, $context)
                );

                $emitted++;
                if ($limit > 0 && $emitted >= $limit) {
                    $context->setShared('product_count', $emitted);
                    return;
                }
            }

            $collection->clear();

            if (!$hasRows) {
                break;
            }
        }

        $context->setShared('product_count', $emitted);
    }

    private function resolvePrice(
        \Magento\Catalog\Model\Product $product,
        OutputContextInterface $context
    ): float {
        $indexed = $product->getData('final_price');
        if ($indexed !== null) {
            return (float) $indexed;
        }
        $product->setCustomerGroupId($context->getCustomerGroupId());
        return (float) $product->getFinalPrice();
    }
}
