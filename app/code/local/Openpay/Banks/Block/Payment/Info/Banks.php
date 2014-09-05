<?php

class Openpay_Banks_Block_Payment_Info_Banks extends Mage_Payment_Block_Info
{
    protected function _prepareSpecificInformation($transport = null){
        $transport = parent::_prepareSpecificInformation();

        $info = $this->getInfo();

        // Add OpenPay Specific information in case user selected this payment method
        if($info->getMethod() == Mage::getModel('Openpay_Banks_Model_Method_Banks')->getCode()){
            if (!$this->getIsSecureMode()) {
                $transport->addData(array(
                    Mage::helper('payment')->__('Openpay Confirmation Number') => $info->getOpenpayAuthorization(),
                    Mage::helper('payment')->__('Openpay Creation Date') => $info->getOpenpayCreationDate(),
                ));
            }
        }
        return $transport;
    }
}
