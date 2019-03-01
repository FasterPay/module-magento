<?php

require_once Mage::getBaseDir('lib') . '/fasterpay-php/lib/autoload.php';


/**
 * Class FasterPay_FasterPay_Model_Method_Abstract
 */
class FasterPay_FasterPay_Model_Method_Abstract extends Mage_Payment_Model_Method_Abstract {

    protected $_code;
    protected $_logFile = 'fasterpay.log';

    /**
     * @param string $code
     */
    public function __construct($code = '') {
        if ($code) {
            $this->_code = 'fasterpay_' . $code;
        }

        $this->_formBlockType = 'fasterpay/checkout_form_method_' . $code;
        $this->_infoBlockType = 'fasterpay/checkout_info_method_' . $code;
        $this->setData('original_code', $code);
    }

    public function initFasterpayConfig($pingback = false) {
        $gateway = new FasterPay\Gateway(array(
            'publicKey' 	=> $this->getConfigData('fasterpay_public_key'),
            'privateKey'	=> $this->getConfigData('fasterpay_private_key'),
        ));
    }

    public function getMethodCode() {
        return $this->_code;
    }

    /**
     * Make invoice for paid order
     * @param $refId
     * @throws Exception
     * @throws bool
     */
    public function makeInvoice($refId) {
        $order = $this->getCurrentOrder();
        if ($order) {

            $payment = $order->getPayment();
            $payment->setTransactionId($refId)
                ->setPreparedMessage('Invoice created by FasterPay module')
                ->setShouldCloseParentTransaction(true)
                ->setIsTransactionClosed(0)
                ->capture(null);
            $order->save();

            // notify customer
            $invoice = $payment->getCreatedInvoice();
            if ($invoice && !$order->getEmailSent() && !Mage::getStoreConfig('system/smtp/disable')) {
                $order->sendNewOrderEmail()
                    ->addStatusHistoryComment(Mage::helper('fasterpay')->__('Notified customer about invoice #%s.', $invoice->getIncrementId()))
                    ->setIsCustomerNotified(true)
                    ->save();
            }
        }
    }

    /**
     * @param $refId
     * @param $invoice
     */
    public function payInvoice($refId, Mage_Sales_Model_Order_Invoice $invoice) {
        $order = $this->getCurrentOrder();

        if ($order) {
            $payment = $order->getPayment();
            $message = Mage::helper('sales')->__('Captured amount of %s online.', $order->getBaseCurrency()->formatTxt($invoice->getBaseGrandTotal()));

            $invoice->setTransactionId($refId)
                ->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->getOrder()->addStatusHistoryComment($message)->setIsCustomerNotified(true);

            $payment->setTransactionId($refId)
                ->setLastTransId($refId)
                ->setCurrencyCode($order->getOrderCurrencyCode())
                ->setPreparedMessage('Payment approved by FasterPay')
                ->setShouldCloseParentTransaction(true)
                ->setIsTransactionClosed(0)
                ->registerCaptureNotification($invoice->getBaseGrandTotal());
            $invoice->pay();
            $order->setState('processing', true, "Payment has been received", false)->save();

            // notify customer
            if ($invoice && !$order->getEmailSent() && !Mage::getStoreConfig('system/smtp/disable')) {
                $order->sendNewOrderEmail()
                    ->addStatusHistoryComment(Mage::helper('fasterpay')->__('Notified customer about invoice #%s.', $invoice->getIncrementId()))
                    ->setIsCustomerNotified(true)
                    ->save();
            }
        }
    }

    /**
     * Log Function
     * @param $message
     */
    public function log($message, $section = '') {
        if ($this->getConfigData('debug_mode')) {
            if (!is_string($message)) {
                $message = var_export($message, true);
            }
            $message = "\n/********** " . $this->getCode() . ($section ? " " . $section : "") . " **********/\n" . $message;
            Mage::log($message, null, $this->_logFile);
        }
    }

}