<?php
/**
 * Created by PhpStorm.
 * User: Xavier de
 * Date: 31/03/14
 * Time: 04:49 PM
 */

class Openpay_Charges_Model_Method_Openpay extends Mage_Payment_Model_Method_Ccsave
{
    protected $_code          = 'charges';
    protected $_canSaveCc     = true;
    protected $_formBlockType = 'charges/form_openpay';
    protected $_infoBlockType = 'payment/info_ccsave';
    protected $_canRefund = true;




    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if (is_array($data)) {
            $data = new Varien_Object($data);
        }

        try {
            $data->addData(array(
                'cc_last4' => substr($data->cc_number, -4),
                'cc_exp_year' => '',
                'cc_exp_month' => '',
            ));
        } catch (Exception $e) {}
        /*try {
            $data->addData(array(
                'cc_last4' => 4444,
                'cc_exp_year' => 03,
                'cc_exp_month' => 12,
            ));
        } catch (Exception $e) {}*/


        //$data->setData('stripe_test', $this->_getHelper()->getTest());

        return parent::assignData($data);
    }

    /**
     * Parepare info instance for save
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function prepareSave()
    {
        $info = $this->getInfoInstance();
        if ($this->_canSaveCc) {
            $info->setCcNumberEnc($info->encrypt($info->getCcNumber()));
        }
        //$info->setCcCidEnc($info->encrypt($info->getCcCid()));
        $info->setCcCid('')
            ->setCcExpYear('')
            ->setCcCidEnc('')
            ->setCcExpMonth('');
        return $this;

    }

    public function validate(){

        $info = $this->getInfoInstance();
        $errorMsg = false;
        $availableTypes = explode(',',$this->getConfigData('cctypes'));

        $ccNumber = $info->getCcNumber();

        // remove credit card number delimiters such as "-" and space
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);
        $info->setCcNumber($ccNumber);

        /* CC_number validation is not done because it should not get into the server */
        if (!in_array($info->getCcType(), $availableTypes)){
            $errorMsg = Mage::helper('payment')->__('Credit card type is not allowed for this payment method.');
        }

        // Verify they are not sending sensitive information
        if($info->getCcExpYear() <> null || $info->getCcExpMonth() <> null || $info->getCcCidEnc() <> null){
            $errorMsg = Mage::helper('payment')->__('Your checkout form is sending sensitive information to the server. Please contact your developer to fix this security leak.');
        }

        if($errorMsg){
            Mage::throwException($errorMsg);
        }

        //This must be after all validation conditions
        if ($this->getIsCentinelValidationEnabled()) {
            $this->getCentinelValidator()->validate($this->getCentinelValidationData());
        }

        return $this;
    }

}