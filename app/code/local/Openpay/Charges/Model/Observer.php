<?php


/* Include OpenPay SDK */
include_once(Mage::getBaseDir('lib') . DS . 'Openpay' . DS . 'Openpay.php');
class Openpay_Charges_Model_Observer{

    public function __construct(){
        /* initialize openpay object */
        $this->_setOpenpayObject();

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
    /*
     * Set openpay object
     */
    protected function _setOpenpayObject(){
        /* Create OpenPay object */
        $this->_openpay = Openpay::getInstance(Mage::getStoreConfig('payment/charges/merchantid'), Mage::getStoreConfig('payment/charges/privatekey'));
    }

}