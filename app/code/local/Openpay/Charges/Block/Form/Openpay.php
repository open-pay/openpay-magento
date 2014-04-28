<?php

class Openpay_Charges_Block_Form_Openpay extends Mage_Payment_Block_Form_Ccsave
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('openpay/form/ccsave.phtml');
    }

}