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
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;

/**
 * Streams CMS-page records. Content is sanitized ONCE at the maximum length
 * any format needs (16000); renderers truncate down (compact excerpt: 500).
 *
 * @since 3.2.0
 */
class CmsPageEntityProvider implements EntityProviderInterface
{
    /** Max of legacy CONTENT_MAX (jsonl) and CONTENT_MAX_FULL — both 16000. */
    public const CONTENT_MAX = 16000;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SanitizerInterface $sanitizer,
        private readonly UrlResolverInterface $urlResolver,
        private readonly Config $config
    ) {
    }

    public function isApplicable(OutputContextInterface $context): bool
    {
        return $this->config->isCmsIncluded($context->getStore());
    }

    public function provide(OutputContextInterface $context): iterable
    {
        $context->setShared(OutputContextInterface::SHARED_ENTITY_TYPE, 'cms-page');

        $store    = $context->getStore();
        $storeId  = (int) $store->getId();
        $excluded = $this->config->getCmsExcludedIdentifiers($store);
        $baseUrl  = $context->getBaseUrl();

        $pages = $this->collectionFactory->create();
        $pages->addStoreFilter($storeId);
        $pages->addFieldToFilter('is_active', 1);
        if ($excluded !== []) {
            $pages->addFieldToFilter('identifier', ['nin' => $excluded]);
        }
        $pages->addFieldToSelect(['title', 'identifier', 'content', 'content_heading']);
        $pages->setOrder('sort_order', 'ASC');

        $count = 0;
        foreach ($pages as $page) {
            $title = trim((string) $page->getTitle());
            if ($title === '') {
                continue;
            }

            $url = $this->urlResolver->resolve(
                UrlResolverInterface::ENTITY_CMS_PAGE,
                (int) $page->getId(),
                $storeId
            ) ?? sprintf('%s/%s', $baseUrl, $page->getIdentifier());

            // Sanitize once — renderers only truncate.
            $content = $this->sanitizer->sanitize(
                (string) $page->getContent(),
                $context,
                self::CONTENT_MAX
            );

            yield new EntityRecord(
                type: EntityRecordInterface::TYPE_CMS_PAGE,
                entityId: (int) $page->getId(),
                name: $title,
                url: $url,
                content: $content,
                identifier: (string) $page->getIdentifier()
            );
            $count++;
        }

        $context->setShared('cms_count', $count);
    }
}
