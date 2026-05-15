<?php

declare(strict_types=1);

namespace Workwear\Personalization\Controller\Adminhtml\Logo;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Ui\Component\MassAction\Filter;
use Workwear\Personalization\Model\CustomerLogo;
use Workwear\Personalization\Model\ResourceModel\CustomerLogo as CustomerLogoResource;
use Workwear\Personalization\Model\ResourceModel\CustomerLogo\CollectionFactory;

class MassApprove extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Workwear_Personalization::logo_moderation';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly EventManager $eventManager,
        private readonly CustomerLogoResource $logoResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $updated = 0;

        /** @var CustomerLogo $logo */
        foreach ($collection->getItems() as $logo) {
            $logo->setData('status', 1);
            $this->logoResource->save($logo);
            $this->eventManager->dispatch('workwear_logo_status_approved', ['logo' => $logo]);
            $updated++;
        }

        $this->messageManager->addSuccessMessage(__('%1 logo(s) approved.', $updated));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
