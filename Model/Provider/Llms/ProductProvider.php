<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Provider\Llms;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Provider\AbstractProvider;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;

/**
 * Emits the products section (`## Products` or under `## Optional`) for llms.txt
 * and llms-full.txt.
 *
 * Streaming: yields one line at a time. Uses entity_id-cursor pagination so new
 * products inserted mid-run cannot cause duplicates or skips.
 *
 * Spec-compliance: when products_under_optional = yes (default per llmstxt.org),
 * products go under `## Optional` so context-budget-constrained LLMs can drop
 * them without losing the more semantically-important categories and pages.
 *
 * Performance (3.1.1):
 *  - Out-of-stock filtering is a SQL join (StockHelper::addIsInStockFilterToCollection)
 *    instead of one StockRegistry round-trip per product — removes N+1.
 *  - Prices come from the price index via Collection::addPriceData() (already
 *    group-aware, special/tier-price aware) instead of invoking the PHP price
 *    calculation chain per product (which lazy-loads children for configurable
 *    and bundle products). A per-product fallback remains for rows missing from
 *    the index.
 *
 * @deprecated 3.2.0 Format-specific legacy provider. Superseded by the
 *             single-pass {@see \Angeo\LlmsTxt\Model\Pipeline\Provider}
 *             entity providers + format renderers. Functional while
 *             generation_mode = legacy (and via the single-pass
 *             compatibility pass); WILL BE REMOVED in 4.0.0.
 * @since 3.0.0
 */
class ProductProvider extends AbstractProvider
{
    private const DESC_MAX_COMPACT = 500;
    private const DESC_MAX_FULL    = 5000;

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
        // Publish the entity type so security-aware sanitizer filters
        // (e.g. CmsDirectiveFilter) can apply entity-specific policies.
        $context->setShared(OutputContextInterface::SHARED_ENTITY_TYPE, 'product');

        $store    = $context->getStore();
        $storeId  = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        $pageSize = $this->config->getCollectionPageSize($store);
        $limit    = $this->config->getProductLimit($store);
        $excludeOos = $this->config->isExcludeOutOfStock($store);
        $lastId   = 0;
        $emitted  = 0;
        $headerYielded = false;

        $underOptional = $this->config->areProductsUnderOptional($store)
            && !$this->isFullTxt($context); // full file ignores ## Optional — every section is verbose.

        while (true) {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToSelect(['name', 'price', 'short_description', 'description', 'sku', 'url_key']);
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

            // Group-aware final price from the price index — one JOIN, zero
            // per-product PHP price calculation.
            $collection->addPriceData($context->getCustomerGroupId(), $websiteId);

            if ($excludeOos) {
                // SQL-level stock filter — zero extra queries per product.
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

                if (!$headerYielded) {
                    if ($underOptional) {
                        yield "## Optional\n\n";
                        yield "### Products\n\n";
                    } else {
                        yield "## Products\n\n";
                    }
                    $headerYielded = true;
                }

                yield $this->renderProduct($product, $url, $context);

                $emitted++;
                if ($limit > 0 && $emitted >= $limit) {
                    if ($headerYielded) {
                        yield "\n";
                    }
                    $context->setShared('product_count', $emitted);
                    return;
                }
            }

            $collection->clear();

            if (!$hasRows) {
                break;
            }
        }

        if ($headerYielded) {
            yield "\n";
        }
        $context->setShared('product_count', $emitted);
    }

    private function renderProduct(
        \Magento\Catalog\Model\Product $product,
        string $url,
        OutputContextInterface $context
    ): string {
        $name  = $this->escapeMarkdown(trim((string) $product->getName()));
        $price = $this->resolvePrice($product, $context);

        if ($this->isFullTxt($context)) {
            $rawShort = (string) $product->getShortDescription();
            $rawDesc  = (string) $product->getDescription();

            $short = $this->sanitizer->sanitize($rawShort, $context, self::DESC_MAX_FULL);
            // Merchants often duplicate short_description into description —
            // don't pay for a second sanitization pass when input is identical.
            $desc = ($rawDesc === $rawShort)
                ? $short
                : $this->sanitizer->sanitize($rawDesc, $context, self::DESC_MAX_FULL);

            $out = "### {$name}\n\n{$url}\n\n";
            if ($price !== null) {
                $out .= sprintf("Price: %s %s\n\n", $price, $context->getCurrencyCode());
            }
            if ($short !== '') {
                $out .= $short . "\n\n";
            }
            if ($desc !== '' && $desc !== $short) {
                $out .= $desc . "\n\n";
            }
            return $out;
        }

        // Compact mode (llms.txt)
        $shortDesc = $this->sanitizer->sanitize(
            (string) $product->getShortDescription(),
            $context,
            self::DESC_MAX_COMPACT
        );
        $line = "- [{$name}]({$url})";
        $parts = [];
        if ($shortDesc !== '') {
            $parts[] = $shortDesc;
        }
        if ($price !== null) {
            $parts[] = sprintf('%s %s', $price, $context->getCurrencyCode());
        }
        if ($parts !== []) {
            $line .= ': ' . implode(' — ', $parts);
        }
        return $line . "\n";
    }

    /**
     * Final price, group-aware. Primary source: the price-index column joined by
     * addPriceData(). Fallback (index row missing, e.g. reindex pending): the
     * legacy per-product calculation.
     */
    private function resolvePrice(
        \Magento\Catalog\Model\Product $product,
        OutputContextInterface $context
    ): ?string {
        $indexed = $product->getData('final_price');
        if ($indexed !== null && (float) $indexed > 0.0) {
            return number_format((float) $indexed, 2, '.', '');
        }

        // Fallback: full PHP price chain (slow) — only for products the index
        // doesn't cover yet.
        $product->setCustomerGroupId($context->getCustomerGroupId());
        $price = $product->getFinalPrice();
        if ($price === null || (float) $price <= 0.0) {
            return null;
        }
        return number_format((float) $price, 2, '.', '');
    }
}
