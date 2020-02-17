<?php

class FasterPay_FasterPay_Model_Method_Fasterpay extends FasterPay_FasterPay_Model_Method_Abstract {

    const MODULE_SOURCE = 'magento1';

    const DELIVERY_STATUS_DELIVERING = 'order_shipped';
    const DELIVERY_STATUS_ORDER_PLACED = 'order_placed';
    const DELIVERY_STATUS_DELIVERED = 'delivered';
    const DELIVERY_PRODUCT_TYPE_PHYSICAL = 'physical';
    const DELIVERY_PRODUCT_TYPE_DIGITAL = 'digital';

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
        $gateway = $this->getGateway();
        $form = $gateway->paymentForm()->buildForm(
            array(
                'description' => 'Order id #' . $order->getIncrementId(),
                'amount' => $order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode(),
                'merchant_order_id' => $order->getIncrementId(),
                'success_url' => $this->getConfigData('fasterpay_url') ? $this->getConfigData('fasterpay_url') : Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK) . '/checkout/onepage/success/',
                'module_source' => self::MODULE_SOURCE,
                'sign_version' => FasterPay\Services\Signature::SIGN_VERSION_2,
                'pingback_url' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK) . '/fasterpay/payment/ipn/'
            ),
            array(
                'autoSubmit' => true,
                'hidePayButton' => true
            )
        );

        return $form;
    }

    public function sendDeliveryInformation(Mage_Sales_Model_Order $order, $status)
    {
        $params = $this->prepareDeliveryData($order, $status);
        Mage::log('Delivery API payload: ' . json_encode($params));

        $client = new Varien_Http_Adapter_Curl();
        $client->write(
            Zend_Http_Client::POST,
            $this->getGateway()->getConfig()->getApiBaseUrl() . '/api/v1/deliveries',
            '1.1',
            [
                'content-type: application/json'
            ],
            json_encode($params)
        );
        $responseBody = $client->read();
        $client->close();
        Mage::log('Delivery API response: ' . $responseBody);
        return $responseBody;
    }

    protected function prepareDeliveryData(Mage_Sales_Model_Order $order, $status)
    {
        if (!$order->getIsVirtual()) {
            $shipmentCreatedAt = $order->getCreatedAt();
            $shippingData = $order->getShippingAddress()->getData();

            if ($order->hasShipments()) {
                $shipmentsCollection = $order->getShipmentsCollection();
                $shipments = $shipmentsCollection->getItems();
                $shipment = array_shift($shipments);
                $shipmentCreatedAt = $shipment->getCreatedAt();
                $shippingData = $shipment->getShippingAddress()->getData();
                $tracksCollection = $shipment->getTracksCollection();
                $tracks = $tracksCollection->getItems();
                $track = array_shift($tracks);
                $carrierCode = empty($track) ? '' : $track->getCarrierCode();
                $trackNumber = empty($track) ? '' : $track->getTrackNumber();
            }

            $prodtype = self::DELIVERY_PRODUCT_TYPE_PHYSICAL;
        } else {
            $shipmentCreatedAt = $order->getCreatedAt();
            $shippingData = $order->getBillingAddress()->getData();
            $prodtype = self::DELIVERY_PRODUCT_TYPE_DIGITAL; // digital products don't have shipment
        }

        // not update delivery status if physical product, status is not order_placed and empty track number
        if ($prodtype == self::DELIVERY_PRODUCT_TYPE_PHYSICAL && $status != self::DELIVERY_STATUS_ORDER_PLACED && empty($trackNumber)) {
            return;
        }

        $fasterpayReferenceId = null;
        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->setOrderFilter($order)
            ->addPaymentIdFilter($order->getPayment()->getId())
            ->addTxnTypeFilter(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE)
            ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_DESC)
            ->setOrder('transaction_id', Varien_Data_Collection::SORT_ORDER_DESC);

        foreach ($collection as $txn) {
            if ($txn->getTxnId()) {
                $fasterpayReferenceId = $txn->getTxnId();
                break;
            }
        }

        $params = [
            'payment_order_id' => $fasterpayReferenceId,
            'merchant_reference_id' => (string)$order->getIncrementId(),
            'type' => $prodtype,
            'status' => $status,
            'estimated_delivery_datetime' => date('Y-m-d H:i:s O', strtotime($shipmentCreatedAt)),
            'refundable' => true,
            'shipping_address' => [
                'country_code' => $shippingData['country_id'],
                'city' => $shippingData['city'],
                'zip' => $shippingData['postcode'],
                'state' => !empty($shippingData['region']) ? $shippingData['region'] : 'N/A',
                'street' => !empty($shippingData['street']) ? $shippingData['street'] : 'N/A',
                'phone' => $shippingData['telephone'],
                'first_name' => $shippingData['firstname'],
                'last_name' => $shippingData['lastname'],
                'email' => $shippingData['email'],
            ],
            'carrier_type' => empty($carrierCode) ? 'N/A' : $carrierCode,
            'carrier_tracking_id' => empty($trackNumber) ? 'N/A' : $trackNumber,
            'reason' => 'none',
            'attachments' => ['N/A'],
            'public_key' => $this->getConfigData('fasterpay_public_key'),
            'sign_version' => FasterPay\Services\Signature::SIGN_VERSION_2,
            'details' => 'Magento 1 delivery action'
        ];
        $params['hash'] = $this->getGateway()->signature()->calculateWidgetSignature($params);

        return $params;
    }
}