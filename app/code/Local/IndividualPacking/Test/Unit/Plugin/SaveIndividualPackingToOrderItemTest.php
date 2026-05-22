<?php
declare(strict_types=1);

namespace Local\IndividualPacking\Test\Unit\Plugin;

use Local\IndividualPacking\Model\Config;
use Local\IndividualPacking\Plugin\SaveIndividualPackingToOrderItem;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveIndividualPackingToOrderItemTest extends TestCase
{
    private SaveIndividualPackingToOrderItem $plugin;
    private Config|MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->plugin = new SaveIndividualPackingToOrderItem($this->config);
    }

    public function testDoesNotAddOptionsWhenNotSelected(): void
    {
        $subject   = $this->createMock(ToOrderItem::class);
        $orderItem = $this->createMock(OrderItem::class);
        $quoteItem = $this->createMock(QuoteItem::class);

        $quoteItem->method('getData')
            ->with('individual_packing_selected')
            ->willReturn('0');

        $orderItem->expects(self::never())->method('setProductOptions');

        $result = $this->plugin->afterConvert($subject, $orderItem, $quoteItem);

        self::assertSame($orderItem, $result);
    }

    public function testAddsAdditionalOptionsWhenSelected(): void
    {
        $this->config->method('getFeePerItem')->willReturn(0.49);

        $subject   = $this->createMock(ToOrderItem::class);
        $quoteItem = $this->createMock(QuoteItem::class);
        $quoteItem->method('getData')
            ->with('individual_packing_selected')
            ->willReturn('1');
        $quoteItem->method('getQty')->willReturn(2.0);

        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProductOptions')->willReturn([]);

        $captured = null;
        $orderItem->method('setProductOptions')
            ->willReturnCallback(function (array $opts) use (&$captured, $orderItem) {
                $captured = $opts;
                return $orderItem;
            });

        $this->plugin->afterConvert($subject, $orderItem, $quoteItem);

        $additional = $captured['additional_options'];
        self::assertCount(2, $additional);
        self::assertSame('Individual Packing', $additional[0]['label']);
        self::assertStringContainsString('Yes', $additional[0]['value']);
        self::assertSame('Individual Packing Fee', $additional[1]['label']);
        self::assertStringContainsString('£0.98', $additional[1]['value']);
        self::assertStringContainsString('£0.49', $additional[1]['value']);
        self::assertStringContainsString('× 2', $additional[1]['value']);
    }

    public function testPreservesExistingAdditionalOptions(): void
    {
        $this->config->method('getFeePerItem')->willReturn(0.49);

        $subject   = $this->createMock(ToOrderItem::class);
        $quoteItem = $this->createMock(QuoteItem::class);
        $quoteItem->method('getData')
            ->with('individual_packing_selected')
            ->willReturn('1');
        $quoteItem->method('getQty')->willReturn(1.0);

        $existing  = [['label' => 'Personalisation', 'value' => 'LEFT_CHEST']];
        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProductOptions')
            ->willReturn(['additional_options' => $existing]);

        $captured = null;
        $orderItem->method('setProductOptions')
            ->willReturnCallback(function (array $opts) use (&$captured, $orderItem) {
                $captured = $opts;
                return $orderItem;
            });

        $this->plugin->afterConvert($subject, $orderItem, $quoteItem);

        self::assertCount(3, $captured['additional_options']);
        self::assertSame('Personalisation', $captured['additional_options'][0]['label']);
        self::assertSame('Individual Packing', $captured['additional_options'][1]['label']);
    }
}
