<?php

class Openpay_Charges_Block_Form_Openpay extends Mage_Payment_Block_Form_Ccsave {
    
    protected $openpay;

    protected function _construct() {
        parent::_construct();
        
        // initialize openpay object
        $this->setOpenpayObject();
        
        $this->setTemplate('openpay/form/ccsave.phtml');
    }
    
    public function getTestData() {
        return 'Federico getTestData';
    }
    
    public function getCreditCardList() {       
        Mage::log('getCreditCardList()');
        
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            return array(array('value' => 'new', 'label' => 'Nueva tarjeta'));
        }
        
        $customer = Mage::getSingleton('customer/session')->getCustomer();        
        if (!$customer->getOpenpayUserId()) {
            return array(array('value' => 'new', 'label' => 'Nueva tarjeta'));
        }
        
        try {
            $list = array(array('value' => 'new', 'label' => 'Nueva tarjeta'));
            $op_customer = $this->openpay->customers->get($customer->getOpenpayUserId());        
            $cards = $this->getCreditCards($op_customer);

            foreach ($cards as $card) {                
                array_push($list, array('value' => $card->id, 'label' => strtoupper($card->brand).' '.$card->card_number));
            }
            
            Mage::log('getCreditCardList() => '. json_encode($list));

            return $list;   
        } catch (Exception $e) {
            Mage::log('#ERROR getCreditCardList() => '.$e->getMessage());
            Mage::throwException(Mage::helper('payment')->__($e->getMessage()));
        }
    }
    
    private function getCreditCards($customer) {                
        try {
            return $customer->cards->getList(array(
                'offset' => 0,
                'limit' => 10
            ));            
        } catch (Exception $e) {            
            Mage::throwException(Mage::helper('payment')->__($e->getMessage()));            
        }        
    }
    
    
    /**
     * 
     * Valida que los clientes pueda guardar sus TC
     * 
     * @return boolean
     */
    public function canSaveCC() {
        return Mage::getStoreConfig('payment/charges/save_cc');        
    }
    
    public function isLoggedIn() {
        return Mage::getSingleton('customer/session')->isLoggedIn();
    }

    protected function setOpenpayObject(){
        /* Create OpenPay object */
        $this->openpay = Openpay::getInstance(Mage::getStoreConfig('payment/common/merchantid'), Mage::getStoreConfig('payment/common/privatekey'));
        Openpay::setProductionMode(!Mage::getStoreConfig('payment/common/sandbox'));
    }
    
}
