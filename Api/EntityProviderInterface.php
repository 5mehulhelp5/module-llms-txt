<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Api;

use Angeo\LlmsTxt\Api\Data\EntityRecordInterface;

/**
 * Single-pass SPI (successor of the format-specific {@see ProviderInterface}):
 * yields format-agnostic {@see EntityRecordInterface} records exactly once per
 * entity; the pipeline renders each record into every enabled output format.
 *
 * Third-party modules extending the generated files should implement THIS
 * interface and register via di.xml (SinglePassGenerator → entityProviders).
 * Legacy {@see ProviderInterface} providers keep working through a
 * compatibility pass until 4.0.0.
 *
 * @api
 * @since 3.2.0
 */
interface EntityProviderInterface
{
    /**
     * Whether this provider has anything to contribute for the given context.
     */
    public function isApplicable(OutputContextInterface $context): bool;

    /**
     * Stream entity records. MUST be memory-bounded (cursor pagination) on
     * large data sets.
     *
     * @return iterable<EntityRecordInterface>
     */
    public function provide(OutputContextInterface $context): iterable;
}
