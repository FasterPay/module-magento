<?php

class FasterPay_FasterPay_Model_Observer
{
    public function updateDeliveryData(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        $eventName = $event->getName();

        $model = Mage::getModel('fasterpay/method_fasterpay');

        if ($eventName == 'sales_order_shipment_track_save_after') {
            $track = $event->getTrack();
            $shipment = $track->getShipment();
            $order = $shipment->getOrder();
        }

        if ($eventName == 'sales_order_save_after') {
            $order = $event->getOrder();
        }

        if (empty($order) || $order->getIsVirtual()) {
            return;
        }

        $paymentMethod = $order->getPayment()->getMethod();
        if ($paymentMethod == $model->getMethodCode() && $order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE) {
            $model->sendDeliveryInformation($order, FasterPay_FasterPay_Model_Method_Fasterpay::DELIVERY_STATUS_DELIVERING);
        }
    }
}