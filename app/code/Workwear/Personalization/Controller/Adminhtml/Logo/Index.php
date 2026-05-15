<?php

declare(strict_types=1);

namespace Workwear\Personalization\Controller\Adminhtml\Logo;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Workwear_Personalization::logo_moderation';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Workwear_Personalization::logo_moderation');
        $resultPage->getConfig()->getTitle()->prepend(__('Logo Moderation'));
        return $resultPage;
    }
}
