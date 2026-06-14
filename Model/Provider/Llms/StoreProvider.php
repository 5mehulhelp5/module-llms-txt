<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Provider\Llms;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Provider\AbstractProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Emits the spec-compliant header for llms.txt / llms-full.txt:
 *
 *   # Store Name
 *   > One-line summary as a single blockquote.
 *
 *   Optional plain-markdown context paragraph (currency, locale, base URL).
 *
 * Per llmstxt.org v1, the H1 is the ONLY required section and the blockquote
 * MUST be a prose summary, not a key:value list. The 2.x output emitted four
 * `>` lines for URL/currency/locale — semantically wrong and pushed
 * descriptive content out of the blockquote.
 *
 * Merchant override: `angeo_llms/general/store_summary` lets the merchant
 * type a custom summary. Falls back to the store's `frontend_default_meta_description`
 * (set in Stores → Configuration → General → Design → HTML Head → Default Description),
 * and finally to a generic "Online store" string.
 *
 * This provider MUST be the first registered for the Llms generators.
 *
 * @deprecated 3.2.0 Format-specific legacy provider. Superseded by the
 *             single-pass {@see \Angeo\LlmsTxt\Model\Pipeline\Provider}
 *             entity providers + format renderers. Functional while
 *             generation_mode = legacy (and via the single-pass
 *             compatibility pass); WILL BE REMOVED in 4.0.0.
 * @since 3.0.0
 */
class StoreProvider extends AbstractProvider
{
    private const XML_PATH_META_DESCRIPTION = 'design/head/default_description';

    public function __construct(
        private readonly Config $config,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function provide(OutputContextInterface $context): iterable
    {
        $store = $context->getStore();

        $name = $store->getName();

        // 1. Title — H1 (required by spec)
        yield "# {$name}\n\n";

        // 2. Summary — single blockquote (required by spec)
        $summary = $this->resolveSummary($context);
        yield "> {$summary}\n\n";

        // 3. Optional metadata paragraph (plain markdown, not a blockquote)
        $base   = $context->getBaseUrl();
        $locale = $context->getLocaleCode();
        $currency = $context->getCurrencyCode();

        yield sprintf(
            "Base URL: %s · Currency: %s · Locale: %s\n\n",
            $base,
            $currency,
            $locale
        );
    }

    private function resolveSummary(OutputContextInterface $context): string
    {
        $store = $context->getStore();

        $override = $this->config->getStoreSummary($store);
        if ($override !== '') {
            return $this->oneLine($override);
        }

        $meta = (string) $this->scopeConfig->getValue(
            self::XML_PATH_META_DESCRIPTION,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
        if (trim($meta) !== '') {
            return $this->oneLine($meta);
        }

        // Final fallback — generic but valid.
        return sprintf(
            'Online store — %s, %s',
            $context->getCurrencyCode(),
            $context->getLocaleCode()
        );
    }

    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
