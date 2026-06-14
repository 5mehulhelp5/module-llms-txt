<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Pipeline\Renderer;

use Angeo\LlmsTxt\Api\FormatRendererInterface;
use Angeo\LlmsTxt\Model\Text\Truncator;

/**
 * Shared helpers for format renderers — markdown escaping, JSONL encoding,
 * word-boundary truncation. Output rules replicate the legacy providers
 * byte-for-byte.
 *
 * @since 3.2.0
 */
abstract class AbstractRenderer implements FormatRendererInterface
{
    public function __construct(
        protected readonly Truncator $truncator
    ) {
    }

    public function reset(): void
    {
        // Stateless by default; markdown renderers override.
    }

    /**
     * Escape markdown special characters in display text (link labels).
     * Identical character set to the legacy AbstractProvider.
     */
    protected function escapeMarkdown(string $text): string
    {
        return strtr($text, [
            '['  => '\\[',
            ']'  => '\\]',
            '('  => '\\(',
            ')'  => '\\)',
            '|'  => '\\|',
            '`'  => '\\`',
        ]);
    }

    /**
     * Encode a record as JSON, line-terminated. Identical flags to the legacy
     * AbstractProvider::encodeJsonl().
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
}
