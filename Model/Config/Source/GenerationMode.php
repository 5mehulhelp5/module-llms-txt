<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Config\Source;

use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options for angeo_llms/performance/generation_mode.
 *
 * @since 3.2.0
 */
class GenerationMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::MODE_LEGACY,
                'label' => __('Legacy — one catalog pass per format (deprecated, removed in 4.0)'),
            ],
            [
                'value' => Config::MODE_SINGLE_PASS,
                'label' => __('Single pass — one catalog pass renders all formats (recommended)'),
            ],
        ];
    }
}
