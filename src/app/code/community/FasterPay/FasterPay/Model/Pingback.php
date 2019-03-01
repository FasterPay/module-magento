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
        $xapiKey = Mage::app()->getRequest()->getHeader('x-apikey');

        if (empty($xapiKey)) {
            return '';
        }

        $fasterpay = new \FasterPay\Gateway(array(
            'publicKey'     => Mage::getModel('fasterpay/method_fasterpay')->getConfigData('fasterpay_public_key'),
            'privateKey'    => Mage::getModel('fasterpay/method_fasterpay')->getConfigData('fasterpay_private_key'),
        ));

        if (!$fasterpay->pingback()->validate(array('apiKey' => $xapiKey))) {
            return '';
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::app()->getRequest()->getParam('payment_order')['merchant_order_id']);
        if (empty($order) || empty($order->getId())) {
            die("Order invalid");
        }

        if ($this->isRecurring()) {
            return $this->processPingbackRecurringProfile();
        } else {
            return $this->processPingbackOrder();
        }

        return '';
    }

    protected function processPingbackRecurringProfile()
    {
        $recurringProfile = Mage::getModel('sales/recurring_profile')->loadByInternalReferenceId($pingback->getProductId());

        if ($recurringProfile->getId()) {
            try {
                if ($pingback->isDeliverable()) {
                    $recurringProfile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE)->save();
                } elseif ($pingback->isCancelable()) {
                    $recurringProfile->cancel()->save();
                }
                return self::DEFAULT_PINGBACK_RESPONSE;
            } catch (Exception $e) {
                Mage::log($e->getMessage());
                $result = 'Internal server error';
                $result .= ' ' . $e->getMessage();
                return $result;
            }
        } else {
            return 'The Recurring Profile is invalid';
        }

    }

    protected function processPingbackOrder()
    {
        $pingbackPaymentOrder = Mage::app()->getRequest()->getParam('payment_order');
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