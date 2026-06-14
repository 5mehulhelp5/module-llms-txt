<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Sanitizer;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerFilterInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Model\Text\Truncator;
use Psr\Log\LoggerInterface;

/**
 * Default {@see SanitizerInterface} implementation — a pipeline of
 * {@see SanitizerFilterInterface} stages applied in order.
 *
 * Filters are injected via di.xml (see etc/di.xml → type=Sanitizer → argument=filters).
 * A failing filter does NOT abort the pipeline; it is logged and the previous
 * stage's output is forwarded as-is. This is deliberate — sanitization is best-effort
 * and a single broken filter should never strand a generation pass.
 *
 * @since 3.0.0
 */
class Sanitizer implements SanitizerInterface
{
    /**
     * @param SanitizerFilterInterface[] $filters  Ordered by di.xml.
     */
    private readonly Truncator $truncator;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $filters = [],
        ?Truncator $truncator = null
    ) {
        // Optional with a default so existing unit tests / integrations keep
        // constructing the class without DI. Behavior is identical either way.
        $this->truncator = $truncator ?? new Truncator();
    }

    public function sanitize(
        string $rawContent,
        OutputContextInterface $context,
        ?int $maxLength = null
    ): string {
        if ($rawContent === '') {
            return '';
        }

        $current = $rawContent;
        foreach ($this->filters as $name => $filter) {
            if (!$filter instanceof SanitizerFilterInterface) {
                continue;
            }
            try {
                $current = $filter->filter($current, $context);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    '[Angeo LlmsTxt] Sanitizer filter "%s" failed for store %s: %s',
                    is_string($name) ? $name : $filter::class,
                    $context->getStore()->getCode(),
                    $e->getMessage()
                ));
                // continue with previous value
            }
        }

        if ($maxLength !== null && $maxLength > 0) {
            // Delegated to the shared Truncator (3.2.0) so the single-pass
            // renderers truncate with byte-identical semantics.
            $current = $this->truncator->truncate($current, $maxLength);
        }

        return $current;
    }
}
