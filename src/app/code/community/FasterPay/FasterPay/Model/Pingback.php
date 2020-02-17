<?php

require_once Mage::getBaseDir('lib') . '/fasterpay-php/lib/autoload.php';


class FasterPay_FasterPay_Model_Pingback extends Mage_Core_Model_Abstract
{
    const DEFAULT_PINGBACK_RESPONSE = 'OK';

    /**
     * Handle pingback
     * @return string
     */
    public function handlePingback()
    {
        $signVersion = Mage::app()->getRequest()->getHeader('x-fasterpay-signature-version');
        if (empty($signVersion)) {
            $signVersion = \FasterPay\Services\Signature::SIGN_VERSION_1;
        }

        $pingbackData = null;

        switch ($signVersion) {
            case \FasterPay\Services\Signature::SIGN_VERSION_1:
                $validationParams = array('apiKey' => Mage::app()->getRequest()->getHeader('x-apikey'));
                $pingbackData = Mage::app()->getRequest()->getParams();
                break;
            case \FasterPay\Services\Signature::SIGN_VERSION_2:
                $validationParams = [
                    'pingbackData' => Mage::app()->getRequest()->getRawBody(),
                    'signVersion' => $signVersion,
                    'signature' => Mage::app()->getRequest()->getHeader('x-fasterpay-signature'),
                ];
                $pingbackData = json_decode(Mage::app()->getRequest()->getRawBody(), 1);
                break;
            default:
                exit('NOK');
        }

        if (empty($pingbackData)) {
            exit('NOK');
        }

        $fasterpay = Mage::getModel('fasterpay/method_fasterpay')->getGateway();

        if (!$fasterpay->pingback()->validate($validationParams)) {
            exit('NOK');
        }

        if ($pingbackData['event'] != 'payment') {
            exit('OK');
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($pingbackData['payment_order']['merchant_order_id']);
        if (empty($order) || empty($order->getId())) {
            die("Order invalid");
        }

        return $this->processPingbackOrder($pingbackData);

    }

    protected function processPingbackOrder($pingbackData = array())
    {
        $pingbackPaymentOrder = $pingbackData['payment_order'];
        $order = Mage::getModel('sales/order')->loadByIncrementId($pingbackPaymentOrder['merchant_order_id']);
        if ($order->getId()) {

            $payment = $order->getPayment();
            $invoice = $order->getInvoiceCollection()
                ->addAttributeToSort('created_at', 'DSC')
                ->setPage(1, 1)
                ->getFirstItem();

            try {
                if ($pingbackPaymentOrder['status'] == 'successful') {
                    if (
                        $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING
                        || $order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE
                    ) {
                        return self::DEFAULT_PINGBACK_RESPONSE;
                    }

                    $paymentModel = $payment->getMethodInstance();
                    $paymentModel->setCurrentOrder($order);

                    if ($invoice->getId()) {
                        $paymentModel->payInvoice($pingbackPaymentOrder['id'], $invoice);
                    } else {
                        $paymentModel->makeInvoice($pingbackPaymentOrder['id']);
                    }

                    if ($order->getIsVirtual()) {
                        $deliveryStatus = FasterPay_FasterPay_Model_Method_Fasterpay::DELIVERY_STATUS_DELIVERED;
                    } else {
                        $deliveryStatus = FasterPay_FasterPay_Model_Method_Fasterpay::DELIVERY_STATUS_ORDER_PLACED;
                    }

                    Mage::getModel('fasterpay/method_fasterpay')->sendDeliveryInformation($order, $deliveryStatus);
                }

                return self::DEFAULT_PINGBACK_RESPONSE;
            } catch (Exception $e) {
                Mage::log($e->getMessage());
                $result = 'Internal server error';
                $result .= ' ' . $e->getMessage();
                return $result;
            }
        } else {
            return 'The Order is invalid';
        }
    }

    protected function isRecurring()
    {
        return false;
    }
}