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
 * Generates the FULL llms-full.txt — the same structure as llms.txt but with
 * each entity's full sanitized description rendered inline as markdown,
 * not just as a one-liner.
 *
 * This is the variant LLMs prefer when they have spare context budget. Spec-wise,
 * `/llms-full.txt` exists for exactly this purpose; 2.x served the same file as
 * `/llms.txt`, which was misleading.
 *
 * Output path: media/angeo/llms/llms-full_{store_code}.txt
 * URL:         {base_url}/llms-full.txt
 *
 * @deprecated 3.2.0 Legacy per-format generator (see AbstractGenerator).
 *             File-path resolution moved to
 *             {@see \Angeo\LlmsTxt\Model\Output\FilePathResolver}.
 *             WILL BE REMOVED in 4.0.0.
 * @since 3.0.0
 */
class LlmsFullTxtGenerator extends AbstractGenerator
{
    protected function getFormat(): string
    {
        return OutputContextInterface::FORMAT_LLMS_FULL_TXT;
    }

    protected function getVerbosity(): string
    {
        return OutputContextInterface::VERBOSITY_FULL;
    }

    protected function getExtension(): string
    {
        return 'txt';
    }

    protected function getFileBaseName(): string
    {
        return 'llms-full';
    }

    protected function isFormatEnabled(StoreInterface $store): bool
    {
        return $this->config->isLlmsFullTxtEnabled($store);
    }
}
