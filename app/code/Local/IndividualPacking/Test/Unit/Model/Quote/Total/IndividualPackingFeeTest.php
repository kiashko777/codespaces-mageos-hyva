<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Test\Unit\Model\Quote\Total;

use Local\IndividualPacking\Model\Config;
use Local\IndividualPacking\Model\Quote\Total\IndividualPackingFee;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndividualPackingFeeTest extends TestCase
{
    private IndividualPackingFee $totalModel;
    private Config|MockObject $config;

    protected function setUp(): void
    {
        $this->config     = $this->createMock(Config::class);
        $this->totalModel = new IndividualPackingFee($this->config);
    }

    private function makeAssignment(string $addressType): ShippingAssignmentInterface
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->addMethods(['getAddressType'])
            ->getMock();
        $address->method('getAddressType')->willReturn($addressType);

        $shipping = $this->createMock(ShippingInterface::class);
        $shipping->method('getAddress')->willReturn($address);

        $assignment = $this->createMock(ShippingAssignmentInterface::class);
        $assignment->method('getShipping')->willReturn($shipping);

        return $assignment;
    }

    public function testCollectSkipsBillingAddress(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $total->expects(self::never())->method('setTotalAmount');

        $this->totalModel->collect($quote, $this->makeAssignment(Address::TYPE_BILLING), $total);
    }

    public function testCollectSkipsWhenModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);

        $total->expects(self::never())->method('setTotalAmount');

        $this->totalModel->collect($quote, $this->makeAssignment(Address::TYPE_SHIPPING), $total);
    }

    public function testCollectSetsTotalForSelectedItems(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getFeePerItem')->willReturn(0.5);

        $item1 = $this->createMock(Quote\Item::class);
        $item1->method('getData')->with('individual_packing_selected')->willReturn('1');
        $item1->method('getQty')->willReturn(2.0);

        $item2 = $this->createMock(Quote\Item::class);
        $item2->method('getData')->with('individual_packing_selected')->willReturn('0');

        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item1, $item2]);

        $total = $this->createMock(Total::class);
        $total->expects(self::once())
            ->method('setTotalAmount')
            ->with('individual_packing_fee', 1.0);
        $total->expects(self::once())
            ->method('setBaseTotalAmount')
            ->with('individual_packing_fee', 1.0);

        $this->totalModel->collect($quote, $this->makeAssignment(Address::TYPE_SHIPPING), $total);
    }

    public function testCollectDoesNotSetTotalWhenNoItemsSelected(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getFeePerItem')->willReturn(0.49);

        $item = $this->createMock(Quote\Item::class);
        $item->method('getData')->with('individual_packing_selected')->willReturn('0');

        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item]);

        $total = $this->createMock(Total::class);
        $total->expects(self::never())->method('setTotalAmount');

        $this->totalModel->collect($quote, $this->makeAssignment(Address::TYPE_SHIPPING), $total);
    }

    public function testFetchReturnsEmptyWhenNoAmount(): void
    {
        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);
        $total->method('getTotalAmount')->with('individual_packing_fee')->willReturn(null);

        self::assertSame([], $this->totalModel->fetch($quote, $total));
    }

    public function testFetchReturnsCodeAndValue(): void
    {
        $this->config->method('getLabel')->willReturn('Individual Packing');

        $quote = $this->createMock(Quote::class);
        $total = $this->createMock(Total::class);
        $total->method('getTotalAmount')->with('individual_packing_fee')->willReturn(0.98);

        $result = $this->totalModel->fetch($quote, $total);

        self::assertSame('individual_packing_fee', $result['code']);
        self::assertSame(0.98, $result['value']);
    }
}
