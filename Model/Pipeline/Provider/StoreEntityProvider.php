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
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Data\EntityRecord;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Yields the single store-meta record (H1/blockquote source for markdown,
 * `store` record for JSONL). Summary resolution mirrors the legacy
 * {@see \Angeo\LlmsTxt\Model\Provider\Llms\StoreProvider}.
 *
 * MUST be the first registered entity provider.
 *
 * @since 3.2.0
 */
class StoreEntityProvider implements EntityProviderInterface
{
    private const XML_PATH_META_DESCRIPTION = 'design/head/default_description';

    public function __construct(
        private readonly Config $config,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isApplicable(OutputContextInterface $context): bool
    {
        return true;
    }

    public function provide(OutputContextInterface $context): iterable
    {
        $store = $context->getStore();

        yield new EntityRecord(
            type: EntityRecordInterface::TYPE_STORE,
            entityId: (int) $store->getId(),
            name: (string) $store->getName(),
            url: $context->getBaseUrl(),
            summary: $this->resolveSummary($context)
        );
    }

    private function resolveSummary(OutputContextInterface $context): string
    {
        $store = $context->getStore();

        $override = $this->config->getStoreSummary($store);
        if ($override !== '') {
            return $this->oneLine($override);
        }

        $meta = (string) $this->scopeConfig->getValue(
            self::XML_PATH_META_DESCRIPTION,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
        if (trim($meta) !== '') {
            return $this->oneLine($meta);
        }

        return sprintf(
            'Online store — %s, %s',
            $context->getCurrencyCode(),
            $context->getLocaleCode()
        );
    }

    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
