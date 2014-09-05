<?php
/**
 * Created by PhpStorm.
 * User: Xavier de
 * Date: 28/03/14
 * Time: 05:28 PM
 */

class Openpay_Charges_Model_Config
{
    /**
     * Return list of supported credit card types
     *
     * @return array
     */
    public function getSupportedCC()
    {
        $model = Mage::getModel('payment/source_cctype')->setAllowedTypes(array('AE', 'VI', 'MC'));
        return $model->toOptionArray();
    }
}