<?php

/* Include OpenPay SDK */
include_once(Mage::getBaseDir('lib') . DS . 'Openpay' . DS . 'Openpay.php');

class Openpay_Charges_Model_Observer{

    public function __construct(){
        /* initialize openpay object */
        $this->_setOpenpayObject();
    }
    
    public function getOrderConfig() {
        return Mage::getSingleton('sales/order_config');
    }
    
    public function checkoutOnepageControllerSuccessAction($order_ids){        
        $response = Mage::app()->getResponse();        
        $order_ids_list = $order_ids->getOrderIds();
        $order_id  = $order_ids_list[0];
        $order = Mage::getModel('sales/order')->load($order_id);
        
        $code = Mage::getModel('Openpay_Charges_Model_Method_Openpay')->getCode();

        if(Mage::getConfig()->getModuleConfig('Openpay_Charges')->is('active', 'true') && $order->getPayment()->getMethod() == $code){            
                        
            Mage::log('Order getStatus (BEFORE): ' . $order->getStatus());
            Mage::log('Checkout Success status (BEFORE): '.$order->getStatusLabel());    
            
            $charge = $this->getOpenpayCharge($order);            
            Mage::log('Openpay Charge Status: ' . $charge->status);
            
            if ($charge->status == 'completed' && $charge->method == 'card') {                
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                $order->setTotalPaid($charge->amount);              
                $order->save();                                                
            }
                                    
            Mage::log('Order getStatus (AFTER): ' . $order->getStatus());
            Mage::log('Checkout Success status (AFTER): '.$order->getStatusLabel());    
                        
            $configured_order_status = Mage::getStoreConfig('payment/charges/order_status');
            Mage::log('Configured Order status: '.$configured_order_status);
            Mage::log('openpay_3d_secure: '.$order->getPayment()->getData('openpay_3d_secure'));
           
            if($order->getPayment()->getData('openpay_3d_secure_url') !== null && $order->getPayment()->getData('openpay_3d_secure') == 1){                
                Mage::log('Observer Redirect URL: '.$order->getPayment()->getData('openpay_3d_secure_url'));                    
                $response->setRedirect($order->getPayment()->getData('openpay_3d_secure_url'));                
            } 
        }

        return $this;
    }
    
    private function getOpenpayCharge ($order) {
        $payment = $order->getPayment();        
                
        if (!$order->getCustomerIsGuest()) {                    
            $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
            $op_customer = $this->_openpay->customers->get($customer->getOpenpayUserId());
            return $op_customer->charges->get($payment->getOpenpayPaymentId());
        }
        
         return $this->_openpay->charges->get($payment->getOpenpayPaymentId());
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
    
    protected function _updateOpenpayCustomerAddress($customer, $customerAddress){
        return $customer;
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
        return $customer;
        $openpay_customer = $this->_openpay->customers->get($customer->getOpenpayUserId());

        $openpay_customer->name = $customer->firstname;
        $openpay_customer->last_name = $customer->lastname;
        $openpay_customer->email = $customer->email;

        return $openpay_customer->save();

    }
    /*
     * Set openpay object
     */
    protected function _setOpenpayObject(){
        /* Create OpenPay object */
        $this->_openpay = Openpay::getInstance(Mage::getStoreConfig('payment/common/merchantid'), Mage::getStoreConfig('payment/common/privatekey'));
         Openpay::setProductionMode(!Mage::getStoreConfig('payment/common/sandbox'));
    }

}