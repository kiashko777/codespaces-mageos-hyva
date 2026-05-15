<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\ResourceModel\CustomerLogo\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;
use Workwear\Personalization\Model\ResourceModel\CustomerLogo as CustomerLogoResource;

class Collection extends SearchResult
{
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'workwear_customer_logo',
        string $resourceModel = CustomerLogoResource::class,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $connection,
            $resource
        );
    }

    protected function _initSelect(): void
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['ce' => $this->getTable('customer_entity')],
            'main_table.customer_id = ce.entity_id',
            ['customer_email' => new \Zend_Db_Expr("COALESCE(ce.email, 'Guest')")]
        );
    }

    public function addFieldToFilter($field, $condition = null): static
    {
        if ($field === 'customer_email') {
            $field = 'ce.email';
        }
        return parent::addFieldToFilter($field, $condition);
    }
}
