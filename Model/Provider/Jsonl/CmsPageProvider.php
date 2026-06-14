<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Provider\Jsonl;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Provider\AbstractProvider;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;

/**
 * Emits one JSONL record per CMS page.
 *
 * @deprecated 3.2.0 Format-specific legacy provider. Superseded by the
 *             single-pass {@see \Angeo\LlmsTxt\Model\Pipeline\Provider}
 *             entity providers + format renderers. Functional while
 *             generation_mode = legacy (and via the single-pass
 *             compatibility pass); WILL BE REMOVED in 4.0.0.
 * @since 3.0.0
 */
class CmsPageProvider extends AbstractProvider
{
    private const CONTENT_MAX = 16000;
    private const EMBED_MAX = 8000;

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
        // Publish the entity type so security-aware sanitizer filters
        // (e.g. CmsDirectiveFilter) can apply entity-specific policies.
        $context->setShared(OutputContextInterface::SHARED_ENTITY_TYPE, 'cms-page');

        $store = $context->getStore();
        $storeId = (int) $store->getId();
        $excluded = $this->config->getCmsExcludedIdentifiers($store);
        $baseUrl = $context->getBaseUrl();

        $pages = $this->collectionFactory->create();
        $pages->addStoreFilter($storeId);
        $pages->addFieldToFilter('is_active', 1);
        if ($excluded !== []) {
            $pages->addFieldToFilter('identifier', ['nin' => $excluded]);
        }
        $pages->addFieldToSelect(['title', 'identifier', 'content', 'content_heading']);
        $pages->setOrder('sort_order', 'ASC');

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

            $content = $this->sanitizer->sanitize(
                (string) $page->getContent(),
                $context,
                self::CONTENT_MAX
            );

            yield $this->encodeJsonl([
                'entity_type'    => 'cms_page',
                'entity_id'      => (int) $page->getId(),
                'store_code'     => $store->getCode(),
                'store_name'     => (string) $store->getName(),
                'title'          => $title,
                'identifier'     => (string) $page->getIdentifier(),
                'url'            => $url,
                'content'        => $content,
                'embedding_text' => mb_substr(trim($title . "\n" . $content), 0, self::EMBED_MAX),
            ]);
        }
    }
}
