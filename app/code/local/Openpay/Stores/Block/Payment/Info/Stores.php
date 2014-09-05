<?php

class Openpay_Stores_Block_Payment_Info_Stores extends Mage_Payment_Block_Info
{
    protected function _prepareSpecificInformation($transport = null){
        $transport = parent::_prepareSpecificInformation();

        $info = $this->getInfo();

        // Add OpenPay Specific information in case user selected this payment method
        if($info->getMethod() == Mage::getModel('Openpay_Stores_Model_Method_Stores')->getCode()){
            if (!$this->getIsSecureMode()) {
                $transport->addData(array(
                    Mage::helper('payment')->__('Openpay Reference Number') => $info->getOpenpayBarcode(),
                    Mage::helper('payment')->__('Openpay Confirmation Number') => $info->getOpenpayAuthorization(),
                    Mage::helper('payment')->__('Openpay Creation Date') => $info->getOpenpayCreationDate(),
                ));
            }
        }
        return $transport;
    }
}
