<?php
/**
 * Payment information model
 *
 * @category   Mage
 * @package    Mage_Payment
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Openpay_Charges_Model_Payment_Info extends Mage_Payment_Model_Info
{
    /**
     * Retrieve data
     *
     * @param   string $key
     * @param   mixed $index
     * @return unknown
     */
    public function getData($key='', $index=null)
    {
        if('openpay_token'===$key){
            $this->_data['openpay_token'] = $this->getCcToken();
        }
        return parent::getData($key, $index);
    }

    /**
     * Assign data to info model instance
     *
     * @param Varien_Object|Array $data
     * @return TemplateTag_Openpay_Model_Payment
     */
    public function assignData($data)
    {
        if (is_array($data)) {
            $data = new Varien_Object($data);
        }

        try {
            $token = $this->getToken($data->getStripeToken());
            $data->addData(array(
                'cc_last4' => $token->card->last4,
                'cc_type' => $token->card->type,
                'cc_owner' => $token->card->name,
            ));
        } catch (Exception $e) {}

        return parent::assignData($data);
    }

}
