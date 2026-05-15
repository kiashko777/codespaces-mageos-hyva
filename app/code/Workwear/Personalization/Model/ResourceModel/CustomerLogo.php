<?php

declare(strict_types=1);

namespace Workwear\Personalization\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Workwear\Personalization\Model\CustomerLogo as CustomerLogoModel;

class CustomerLogo extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('workwear_customer_logo', 'entity_id');
    }

    public function loadByFileHash(CustomerLogoModel $model, string $fileHash): static
    {
        return $this->load($model, $fileHash, 'file_hash');
    }

    public function loadByFileHashAndCustomer(
        CustomerLogoModel $model,
        string $fileHash,
        ?int $customerId
    ): static {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('file_hash = ?', $fileHash);

        if ($customerId === null) {
            $select->where('customer_id IS NULL');
        } else {
            $select->where('customer_id = ?', $customerId);
        }

        $select->limit(1);

        $data = $connection->fetchRow($select);
        if ($data) {
            $model->setData($data);
            $model->setOrigData();
        }

        return $this;
    }
}
