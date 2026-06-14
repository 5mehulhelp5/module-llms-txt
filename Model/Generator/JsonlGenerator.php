<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Generator;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Generates JSONL (Newline-Delimited JSON) for vector indexing / embedding pipelines.
 *
 * Each line is a valid JSON object validating against etc/jsonl-schema.json.
 *
 * Output path: media/angeo/llms/llms_{store_code}.jsonl
 * URL:         {base_url}/llms.jsonl
 *
 * @deprecated 3.2.0 Legacy per-format generator (see AbstractGenerator).
 *             File-path resolution moved to
 *             {@see \Angeo\LlmsTxt\Model\Output\FilePathResolver}.
 *             WILL BE REMOVED in 4.0.0.
 * @since 3.0.0
 */
class JsonlGenerator extends AbstractGenerator
{
    protected function getFormat(): string
    {
        return OutputContextInterface::FORMAT_JSONL;
    }

    protected function getVerbosity(): string
    {
        return OutputContextInterface::VERBOSITY_DATASET;
    }

    protected function getExtension(): string
    {
        return 'jsonl';
    }

    protected function isFormatEnabled(StoreInterface $store): bool
    {
        return $this->config->isJsonlEnabled($store);
    }
}
