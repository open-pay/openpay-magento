<?php

class Openpay_Charges_Block_Payment_Info_Ccsave extends Mage_Payment_Block_Info_Ccsave
{
    protected function _prepareSpecificInformation($transport = null){
        $transport = parent::_prepareSpecificInformation();

        $info = $this->getInfo();        
        
        // Add OpenPay Specific information in case user selected this payment method
        if($info->getMethod() == Mage::getModel('Openpay_Charges_Model_Method_Openpay')->getCode() && $info->getAdditionalData() != null){
            $transport->addData(array(
                Mage::helper('payment')->__('Información adicional:') => $info->getAdditionalData(),            
            ));
        }
        return $transport;
    }        
    
}
