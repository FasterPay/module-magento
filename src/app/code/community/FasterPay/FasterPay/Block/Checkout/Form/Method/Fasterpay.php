<?php

/**
 *
 * Class FasterPay_FasterPay_Block_Checkout_Form_Method_Fasterpay
 */
class FasterPay_FasterPay_Block_Checkout_Form_Method_Fasterpay extends FasterPay_FasterPay_Block_Checkout_Form_Method_Abstract
{
    /**
     * Set template for block
     * @return void
     */
    protected function _construct()
    {
        $mark = Mage::getConfig()->getBlockClassName('core/template');
        $mark = new $mark;
        $mark->setTemplate('fasterpay/checkout/form/mark.phtml');
        $mark->setPaymentTitleAlias(Mage::getModel('fasterpay/method_fasterpay')->getConfigData('title'));
        $mark->setPaymentLogoSrc($this->getSkinUrl('images/fasterpay/logo.png'));
        $mark->setVisaLogoSrc($this->getSkinUrl('images/fasterpay/visa.svg'));
        $mark->setMCLogoSrc($this->getSkinUrl('images/fasterpay/mc.svg'));

        $this->setMethodTitle('');
        $this->setMethodLabelAfterHtml($mark->toHtml());

        parent::_construct();
        $this->setPaymentModelName('fasterpay');
    }

    function getPaymentForm()
    {
        $order = $this->getOrder();
        $return = array(
            'content' => '',
            'status' => false
        );

        if ($order) {
            try {
                $model = $this->getPaymentModel();
                $paymentForm = $model->getPaymentForm($order);

                // Get widget iframe
                $return['content'] = $paymentForm;
                $return['status'] = true;
            } catch (Exception $e) {
                Mage::logException($e);
                $return['content'] = Mage::helper('fasterpay')->__('Errors, Please try again!');
            }
        } else {
            $return['content'] = Mage::helper('fasterpay')->__('Order invalid'); //should redirect back to homepage
        }

        return $return;
    }

    /**
     * Get last order
     */
    protected function getOrder()
    {
        if (!$this->_order) {
            $session = Mage::getSingleton('checkout/session');
            $this->_order = $this->loadOrderById($session->getLastRealOrderId());
        }
        return $this->_order;
    }

    protected function loadOrderById($orderId)
    {
        return Mage::getModel('sales/order')->loadByIncrementId($orderId);
    }
}