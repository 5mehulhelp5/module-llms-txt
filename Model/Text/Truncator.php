<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Text;

/**
 * Word-boundary truncation shared by the Sanitizer (legacy pipeline) and the
 * format renderers (single-pass pipeline). Behavior is byte-identical to the
 * pre-3.2.0 Sanitizer::truncateOnWordBoundary().
 *
 * @api
 * @since 3.2.0
 */
class Truncator
{
    /**
     * Truncate at the last whitespace before $maxLength, appending an ellipsis.
     * Falls back to a hard truncate if no suitable whitespace is found.
     */
    public function truncate(string $text, int $maxLength): string
    {
        if ($maxLength <= 0 || mb_strlen($text) <= $maxLength) {
            return $text;
        }
        $hard = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($hard, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength * 0.7) {
            $hard = mb_substr($hard, 0, $lastSpace);
        }
        return rtrim($hard) . '…';
    }
}
