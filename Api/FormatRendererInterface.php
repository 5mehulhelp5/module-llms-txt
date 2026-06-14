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
 * Serializes {@see EntityRecordInterface} records into one output format
 * (llms.txt / llms-full.txt / llms.jsonl) inside the single-pass pipeline.
 *
 * Renderers are stateful within one store pass (section headers, separators);
 * the pipeline calls {@see reset()} before each pass.
 *
 * @api
 * @since 3.2.0
 */
interface FormatRendererInterface
{
    /** Clear per-pass state. Called once before each store generation pass. */
    public function reset(): void;

    /**
     * Render one record into zero or more output chunks.
     *
     * @return iterable<string>
     */
    public function render(EntityRecordInterface $record, OutputContextInterface $context): iterable;

    /**
     * Emit trailing output (e.g. closing section separator) at end of pass.
     *
     * @return iterable<string>
     */
    public function finish(OutputContextInterface $context): iterable;
}
