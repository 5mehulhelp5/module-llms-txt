<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Api;

/**
 * Content provider SPI for the llms.txt / llms-full.txt / JSONL pipelines.
 *
 * Implementations contribute a section (or set of records) to the generated file.
 * Providers run in the order declared in di.xml inside a frontend-emulated store
 * scope; one failing provider does NOT abort the generation — the abstract
 * generator catches exceptions per-provider and logs them.
 *
 * <b>BREAKING CHANGE in 3.0.0:</b> {@see provide()} now returns an iterable of
 * strings (typically a PHP generator yielding chunks) instead of a single
 * concatenated string. This lets the generator stream output to disk one chunk
 * at a time, keeping memory bounded regardless of catalog size.
 *
 * Recommended chunk granularity:
 *   - markdown providers: yield one header chunk, then one chunk per line
 *   - JSONL providers:    yield one chunk per JSON record (terminated with "\n")
 *
 * Implementations MUST NOT accumulate the full output in an array and return it as
 * a single string — that would defeat the streaming design.
 *
 * @api
 * @deprecated 3.2.0 Legacy format-specific SPI. Implement
 *             {@see \Angeo\LlmsTxt\Api\EntityProviderInterface} instead:
 *             one format-agnostic record per entity, rendered into every
 *             enabled format in a single catalog pass. Existing
 *             implementations keep working in BOTH modes until 4.0.0
 *             (single-pass runs them through a compatibility pass).
 * @see \Angeo\LlmsTxt\Api\EntityProviderInterface
 * @since 3.0.0
 */
interface ProviderInterface
{
    /**
     * Generate content for the given output context.
     *
     * @param OutputContextInterface $context
     * @return iterable<string>  Each yielded value is a chunk of the output.
     *                           Yield empty strings to no-op gracefully when there
     *                           is nothing to contribute for this store/format.
     */
    public function provide(OutputContextInterface $context): iterable;

    /**
     * Whether this provider applies to the given context.
     *
     * Allows a single provider to be registered once and decide at runtime whether
     * it has anything to contribute for the current format/verbosity. The default
     * implementation should return true; override to skip e.g. a "products" provider
     * when {@see Config::isProductsIncluded()} is false.
     */
    public function isApplicable(OutputContextInterface $context): bool;
}
