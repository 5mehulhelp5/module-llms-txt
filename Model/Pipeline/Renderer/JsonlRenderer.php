<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Pipeline\Renderer;

use Angeo\LlmsTxt\Api\Data\EntityRecordInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;

/**
 * JSONL renderer. Record shapes are identical to the legacy
 * Jsonl\{Store,Category,CmsPage,Product}Provider output and conform to
 * etc/jsonl-schema.json.
 *
 * @since 3.2.0
 */
class JsonlRenderer extends AbstractRenderer
{
    private const PRODUCT_SHORT_MAX = 2000;
    private const EMBED_MAX         = 8000;

    public function render(EntityRecordInterface $record, OutputContextInterface $context): iterable
    {
        $store = $context->getStore();

        switch ($record->getType()) {
            case EntityRecordInterface::TYPE_STORE:
                yield $this->encodeJsonl([
                    'entity_type'    => 'store',
                    'entity_id'      => $record->getEntityId(),
                    'store_code'     => $store->getCode(),
                    'store_name'     => (string) $store->getName(),
                    'url'            => $context->getBaseUrl(),
                    'currency'       => $context->getCurrencyCode(),
                    'locale'         => $context->getLocaleCode(),
                    'embedding_text' => trim($store->getName() . ' ' . $context->getBaseUrl()),
                ]);
                return;

            case EntityRecordInterface::TYPE_CATEGORY:
                $description = $record->getContent();
                yield $this->encodeJsonl([
                    'entity_type'    => 'category',
                    'entity_id'      => $record->getEntityId(),
                    'store_code'     => $store->getCode(),
                    'store_name'     => (string) $store->getName(),
                    'name'           => $record->getName(),
                    'url'            => $record->getUrl(),
                    'description'    => $description,
                    'embedding_text' => mb_substr(
                        trim($record->getName() . "\n" . $description),
                        0,
                        self::EMBED_MAX
                    ),
                ]);
                return;

            case EntityRecordInterface::TYPE_CMS_PAGE:
                $content = $record->getContent();
                yield $this->encodeJsonl([
                    'entity_type'    => 'cms_page',
                    'entity_id'      => $record->getEntityId(),
                    'store_code'     => $store->getCode(),
                    'store_name'     => (string) $store->getName(),
                    'title'          => $record->getName(),
                    'identifier'     => (string) $record->getIdentifier(),
                    'url'            => $record->getUrl(),
                    'content'        => $content,
                    'embedding_text' => mb_substr(
                        trim($record->getName() . "\n" . $content),
                        0,
                        self::EMBED_MAX
                    ),
                ]);
                return;

            case EntityRecordInterface::TYPE_PRODUCT:
                // Legacy jsonl truncated short_description at 2000; record carries
                // up to 5000 (full-txt max) — truncate down identically.
                $short = $this->truncator->truncate($record->getShortContent(), self::PRODUCT_SHORT_MAX);
                $desc  = $record->getContent();
                yield $this->encodeJsonl([
                    'entity_type'       => 'product',
                    'entity_id'         => $record->getEntityId(),
                    'store_code'        => $store->getCode(),
                    'store_name'        => (string) $store->getName(),
                    'sku'               => (string) $record->getSku(),
                    'name'              => $record->getName(),
                    'url'               => $record->getUrl(),
                    'price'             => (float) $record->getPrice(),
                    'currency'          => $context->getCurrencyCode(),
                    'short_description' => $short,
                    'description'       => $desc,
                    'embedding_text'    => mb_substr(
                        trim($record->getName() . "\n" . $short . "\n" . $desc),
                        0,
                        self::EMBED_MAX
                    ),
                ]);
                return;
        }
    }

    public function finish(OutputContextInterface $context): iterable
    {
        return [];
    }
}
