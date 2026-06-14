<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Provider;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\ProviderInterface;

/**
 * Common skeleton for content providers — exposes `isApplicable()` defaulting to
 * true and provides a helper for the format/verbosity-based output mode.
 *
 * @deprecated 3.2.0 Base of the legacy provider SPI — see
 *             {@see \Angeo\LlmsTxt\Api\EntityProviderInterface}. WILL BE
 *             REMOVED in 4.0.0.
 * @since 3.0.0
 */
abstract class AbstractProvider implements ProviderInterface
{
    public function isApplicable(OutputContextInterface $context): bool
    {
        return true;
    }

    /**
     * True when this generation pass is producing JSONL records.
     */
    protected function isJsonl(OutputContextInterface $context): bool
    {
        return $context->getFormat() === OutputContextInterface::FORMAT_JSONL;
    }

    /**
     * True when this generation pass is producing the full-content txt file.
     */
    protected function isFullTxt(OutputContextInterface $context): bool
    {
        return $context->getFormat() === OutputContextInterface::FORMAT_LLMS_FULL_TXT;
    }

    /**
     * Encode a record as JSON, line-terminated. Always UTF-8 unescaped, slashes intact.
     *
     * @param array<string, mixed> $record
     */
    protected function encodeJsonl(array $record): string
    {
        $json = json_encode(
            $record,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            return '';
        }
        return $json . "\n";
    }

    /**
     * Escape markdown special characters in display text (link labels, descriptions).
     */
    protected function escapeMarkdown(string $text): string
    {
        // Square brackets, parentheses, pipes, backticks — the ones that break link syntax.
        return strtr($text, [
            '['  => '\\[',
            ']'  => '\\]',
            '('  => '\\(',
            ')'  => '\\)',
            '|'  => '\\|',
            '`'  => '\\`',
        ]);
    }
}
