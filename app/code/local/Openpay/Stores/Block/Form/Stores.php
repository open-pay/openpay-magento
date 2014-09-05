<?php

class Openpay_Stores_Block_Form_Stores extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('openpay/form/stores.phtml');
    }

}
