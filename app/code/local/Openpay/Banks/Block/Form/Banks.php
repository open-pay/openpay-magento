<?php

class Openpay_Banks_Block_Form_Banks extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('openpay/form/banks.phtml');
    }

}
