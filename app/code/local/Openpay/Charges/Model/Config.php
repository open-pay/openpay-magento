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
        $model = Mage::getModel('payment/source_cctype')->setAllowedTypes(array('AE', 'VI', 'MC', 'CN'));
        return $model->toOptionArray();
    }
    
    public function getMonthsInterestFree()
    {
        return array(
            array('value' => '1', 'label' => 'No aceptar pagos a meses'),
            array('value' => '3', 'label' => '3 meses'),
            array('value' => '6', 'label' => '6 meses'),
            array('value' => '9', 'label' => '9 meses'),
            array('value' => '12', 'label' => '12 meses'),
            array('value' => '18', 'label' => '18 meses')            
        );        
    }
    
}