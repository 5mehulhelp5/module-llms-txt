<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Adminhtml\Generate;

use Angeo\LlmsTxt\Service\GenerationService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Psr\Log\LoggerInterface;

/**
 * Admin "Generate Now" — synchronous, in-thread, POST + CSRF protected.
 *
 * Suitable for catalogs up to a few thousand products. For larger stores, prefer
 * the {@see \Angeo\LlmsTxt\Controller\Adminhtml\Generate\Schedule} action which
 * queues the generation for the next cron tick.
 *
 * @since 3.0.0
 */
class Index extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Angeo_LlmsTxt::generate';

    public function __construct(
        Context $context,
        private readonly GenerationService $generationService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $summaries = $this->generationService->generateAll();

            $totalBytes = 0;
            $totalItems = 0;
            $anyFailures = false;
            foreach ($summaries as $summary) {
                $totalBytes += $summary->getTotalBytes();
                $totalItems += $summary->getTotalItems();
                if ($summary->hasFailures()) {
                    $anyFailures = true;
                }
            }

            if ($anyFailures) {
                $this->messageManager->addWarningMessage(
                    __(
                        'Generation completed with errors. See var/log/system.log for details. '
                        . 'Generated %1 KB across %2 items.',
                        number_format($totalBytes / 1024, 1),
                        $totalItems
                    )
                );
            } else {
                $this->messageManager->addSuccessMessage(
                    __(
                        'Generated %1 KB across %2 items for all eligible stores.',
                        number_format($totalBytes / 1024, 1),
                        $totalItems
                    )
                );
            }
        } catch (\Throwable $e) {
            // Do not surface raw exception internals (paths, SQL) in the UI.
            $this->logger->error(
                '[Angeo LlmsTxt] Admin generation failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
            $this->messageManager->addErrorMessage(
                __('Generation failed. See var/log/system.log for details.')
            );
        }

        return $this->resultRedirectFactory->create()->setPath(
            'adminhtml/system_config/edit/section/angeo_llms'
        );
    }
}
