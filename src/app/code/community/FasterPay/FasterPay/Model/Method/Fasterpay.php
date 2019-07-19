<?php

class FasterPay_FasterPay_Model_Method_Fasterpay extends FasterPay_FasterPay_Model_Method_Abstract {

    const MODULE_SOURCE = 'magento1';

    protected $_isInitializeNeeded = false;
    protected $_canUseInternal = false;
    protected $_canUseForMultishipping = false;
    protected $_canCapture = true;
    protected $_canAuthorize = true;
    protected $_canVoid = false;
    protected $_canReviewPayment = false;
    protected $_canCreateBillingAgreement = false;

    /**
     * Constructor method.
     * Set some internal properties
     */
    public function __construct() {
        parent::__construct('fasterpay');
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('fasterpay/payment/fasterpay', array('_secure' => true));
    }

    public function getPaymentForm(Mage_Sales_Model_Order $order) {
        $gateway = new FasterPay\Gateway(array(
            'publicKey' 	=> $this->getConfigData('fasterpay_public_key'),
            'privateKey'    => $this->getConfigData('fasterpay_private_key'),
            'isTest'        => $this->getConfigData('fasterpay_istest')
        ));
        $form = $gateway->paymentForm()->buildForm(
            array(
                'description' => 'Order id #' . $order->getIncrementId(),
                'amount' => $order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode(),
                'merchant_order_id' => $order->getIncrementId(),
                'success_url' => $this->getConfigData('fasterpay_url') ? $this->getConfigData('fasterpay_ur') : Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK) . '/checkout/onepage/success/',
                'module_source' => self::MODULE_SOURCE,
                'sign_version' => FasterPay\Services\Signature::SIGN_VERSION_2
            )
        );

        return $form;
    }
}