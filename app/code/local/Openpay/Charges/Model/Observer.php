<?php

/* Include OpenPay SDK */
include_once(Mage::getBaseDir('lib') . DS . 'Openpay' . DS . 'Openpay.php');

class Openpay_Charges_Model_Observer{

    protected $_openpay;

    public function __construct(){

        /* initialize openpay object */
        $this->_setOpenpayObject();
    }

    public function processOpenpayTransaction($event){

        /* TO DO check for supported currencies */
        if($event->payment->getMethod() == Mage::getModel('Openpay_Charges_Model_Method_Openpay')->getCode()){

            /* Take actions for the different checkout methods */
            $checkout_method = $event->payment->getOrder()->getQuote()->getCheckoutMethod();

            switch ($checkout_method){
                case Mage_Sales_Model_Quote::CHECKOUT_METHOD_GUEST:
                    $charge = $this->_chargeCardInOpenpay($event);
                    break;

                case Mage_Sales_Model_Quote::CHECKOUT_METHOD_LOGIN_IN:
                    // get the user, if no user create, then add payment
                    $customer = $event->getPayment()->getOrder()->getCustomer();
                    $shippingAddress = $event->getPayment()->getOrder()->getShippingAddress();

                    if (!$customer->_openpay_user_id) {
                        // create OpenPay customer
                        $openpay_user = $this->_createOpenpayCustomer($customer, $shippingAddress);
                        $customer->setOpenpayUserId($openpay_user->id);
                        $customer->save();

                        $charge = $this->_chargeOpenpayCustomer($event);
                    }else{
                        $charge = $this->_getOpenpayCustomer($customer->_openpay_user_id);
                    }
                    break;

                default:
                    $charge = $this->_chargeCardInOpenpay($event);
                    break;

            }

            // Set Openpay confirmation number as Order_Payment openpay_token
            $event->payment->setOpenpayAuthorization($charge->authorization);
            $event->payment->setOpenpayCreationDate($charge->creation_date);
            $event->payment->setOpenpayPaymentId($charge->id);
        }
        return $event;
    }

    public function customerAddressSaveAfter($event){

        $customerAddress = $event->getCustomerAddress();
        $customer = $customerAddress->getCustomer();
        $totalCustomerAddresses = count($customer->getAddressesCollection()->getItems());

        if($openpay_user_id = $customer->getOpenpayUserId()){
            if($customerAddress->isDefaultShipping || $totalCustomerAddresses == 1){
                $this->_updateOpenpayCustomerAddress($customer, $customerAddress);
            }
        }
    }

    public function customerSaveAfter($event){

        $customer = $event->getCustomer();
        if($customer->getOpenpayUserId()){
            $this->_updateOpenpayCustomerBasicInfo($customer);
        }

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

    /*
     * Set openpay object
     */
    protected function _setOpenpayObject(){
        /* Create OpenPay object */
        $this->_openpay = Openpay::getInstance(Mage::getStoreConfig('payment/charges/merchantid'), Mage::getStoreConfig('payment/charges/privatekey'));
    }
    /*
     * Charge Card using OpenPay
     */
    protected function _chargeCardInOpenpay($event){

        $order = $event->payment->getOrder();
        $paymentRequest = Mage::app()->getRequest()->getPost('payment');

        /* Populate an array with the Data */
        $chargeData = array(
            'method' => 'card',
            'source_id' => $paymentRequest['openpay_token'],
            'device_session_id' => $paymentRequest['device_session_id'],
            'amount' => (float) $order->grandTotal,
            'description' => Mage::app()->getStore()->getName() . ' Magento Store: '
                .$this->_getHelper()->__($orderFirstItem->getName())
                .(($numItems>1)?$this->_getHelper()->__('... and (%d) other items', $numItems-1): ''),
            'order_id' => $order->getIncrementId()
        );

        /* Create the request to OpenPay to charge the CC*/
        $charge = $this->_openpay->charges->create($chargeData);

        return $charge;
    }
    protected function _chargeOpenpayCustomer($event){

        $order = $event->getPayment()->getOrder();
        $orderFirstItem = $order->getItemById(0);
        $mageCustomer = $order->getCustomer();
        $numItems = $order->getTotalItemCount();
        $paymentRequest = Mage::app()->getRequest()->getPost('payment');

        $chargeData = array(

            'source_id' => $paymentRequest['openpay_token'],
            'device_session_id' => $paymentRequest['device_session_id'],
            'method' => 'card',
            'amount' => $order->getGrandTotal(),
            'description' => Mage::app()->getStore()->getName() . ' Magento Store: '
                .$this->_getHelper()->__($orderFirstItem->getName())
                .(($numItems>1)?$this->_getHelper()->__('... and (%d) other items', $numItems-1): ''),
            'order_id' => $order->getIncrementId());

        $customer = $this->_openpay->customers->get($mageCustomer->getOpenpayUserId());
        $charge = $customer->charges->create($chargeData);

        return $charge;
    }
    /*
     * Create user in OpenPay
     */
    protected function _createOpenpayCustomer($customer, $shippingAddress){

        $customerData = array(
            'name' => $customer->firstname,
            'last_name' => $customer->lastname,
            'email' => $customer->email,
            'phone_number' => $shippingAddress->telephone,
            'address' => array(
                'line1' => $shippingAddress->street,
                'postal_code' => $shippingAddress->postcode,
                'state' => $shippingAddress->region,
                'city' => $shippingAddress->city,
                'country_code' => $shippingAddress->country_id));

        $customer = $this->_openpay->customers->add($customerData);

        return $customer;
    }
    protected function _updateOpenpayCustomerAddress($customer, $customerAddress){

        $openpay_customer = $this->_openpay->customers->get($customer->getOpenpayUserId());

        $op_address = $openpay_customer->address;
        $op_address->line1 = $customerAddress->street;

        $op_address->postal_code = $customerAddress->postcode;
        $op_address->state = $customerAddress->region;
        $op_address->city = $customerAddress->city;
        $op_address->country_code = $customerAddress->country_id;
        $openpay_customer->phone_number = $customerAddress->telephone;

        //$op_address->external_id = $customerAddress->entity_id;

        return $openpay_customer->save();
    }
    protected function _updateOpenpayCustomerBasicInfo($customer){
        $openpay_customer = $this->_openpay->customers->get($customer->getOpenpayUserId());

        $openpay_customer->name = $customer->firstname;
        $openpay_customer->last_name = $customer->lastname;
        $openpay_customer->email = $customer->email;

        return $openpay_customer->save();

    }
    protected function _getOpenpayCustomer($user_token){

        $customer = $this->_openpay->customers->get($user_token);

        return $customer;
    }
}