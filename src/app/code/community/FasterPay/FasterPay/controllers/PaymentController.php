<?php

/**
 * Class FasterPay_Payment_PaymentController
 */
class FasterPay_FasterPay_PaymentController extends Mage_Core_Controller_Front_Action
{
    const ORDER_STATUS_AFTER_PINGBACK_SUCCESS = 'processing';

    /**
     * Action that handles pingback call from fasterpay system
     * @return string
     */
    public function ipnAction()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        $result = Mage::getModel('fasterpay/pingback')->handlePingback();
        $this->getResponse()->setBody($result);
    }

    public function fasterpayAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }
}