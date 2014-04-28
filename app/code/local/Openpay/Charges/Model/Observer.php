<?php

/* Include OpenPay SDK */
include_once(Mage::getBaseDir('lib') . DS . 'Openpay' . DS . 'Openpay.php');

class Openpay_Charges_Model_Observer{

    public function processOpenpayTransaction($event){

        /* Take actions for the different checkout methods */
        //$event->payment->getOrder()->getQuote()->getCheckoutMethod()
        // Mage_Sales_Model_Quote::CHECKOUT_METHOD_REGISTER
        // Mage_Sales_Model_Quote::CHECKOUT_METHOD_GUEST
        // Mage_Sales_Model_Quote::CHECKOUT_METHOD_LOGIN_IN
        /* TO DO check for supported currencies */
        if($event->payment->getMethod() == Mage::getModel('Openpay_Charges_Model_Method_Openpay')->getCode()){

            /* Create OpenPay object */
            $openpay = Openpay::getInstance(Mage::getStoreConfig('payment/charges/merchantid'), Mage::getStoreConfig('payment/charges/privatekey'));

            /* Populate an array with the Data */
            $chargeData = array(
                'method' => 'card',
                'source_id' => $_POST['payment']['openpay_token'],
                'device_session_id' => $_POST['payment']['device_session_id'],
                'amount' => (float) $event->payment->getOrder()->grandTotal,
                'description' => $this->_getHelper()->__('Payment from Magento Store'),
                'order_id' => $event->payment->getOrder()->getIncrementId()
            );

            /* Create the request to OpenPay to charge the CC*/
            $charge = $openpay->charges->create($chargeData);

            // Set Openpay confirmation number as Order_Payment openpay_token
            $event->payment->setOpenpayAuthorization($charge->authorization);
            $event->payment->setOpenpayCreationDate($charge->creation_date);
            $event->payment->setOpenpayPaymentId($charge->id);
        }
        return $event;
    }

    /**
     * Retrieve model helper
     *
     * @return Openpay_Charges_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('charges');
    }
}