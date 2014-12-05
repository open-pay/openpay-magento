<?php
/**
 * Created by PhpStorm.
 * User: Xavier de
 * Date: 31/03/14
 * Time: 04:49 PM
 */


/* Include OpenPay SDK */
include_once(Mage::getBaseDir('lib') . DS . 'Openpay' . DS . 'Openpay.php');


class Openpay_Charges_Model_Method_Openpay extends Mage_Payment_Model_Method_Cc
{
    protected $_code          = 'charges';
    protected $_openpay;
    protected $_canSaveCc     = true;

    protected $_canRefund                   = true;
    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canVoid                     = true;

    protected $_canRefundInvoicePartial     = false;

    protected $_formBlockType = 'charges/form_openpay';
    protected $_infoBlockType = 'payment/info_ccsave';

    public function __construct(){

        /* initialize openpay object */
        $this->_setOpenpayObject();
    }

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
            $paymentRequest = Mage::app()->getRequest()->getPost('payment');
            $data->addData(array(
                'cc_last4' => substr($data->cc_number, -4),
                'cc_exp_year' => '',
                'cc_exp_month' => '',
            ));
            $info = $this->getInfoInstance();

            $this->_openpay_token       = $paymentRequest['openpay_token'];
            $this->_device_session_id   = $paymentRequest['device_session_id'];

            $info->setOpenpayToken($paymentRequest['openpay_token'])
                ->setDeviceSessionId($paymentRequest['device_session_id']);
        } catch (Exception $e) {}

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

         Mage::log("Numero de tarjeta ". $ccNumber);
        Mage::log("Tarjetas soportadas ". $this->getConfigData('cctypes'));
        Mage::log("Tarjetas seleccionada ". $info->getCcType());

        if (!in_array($info->getCcType(), $availableTypes)){
            $errorMsg = Mage::helper('payment')->__('Tipo de arjeta no soportada');
        }

        // Verify they are not sending sensitive information
        if($info->getCcExpYear() <> null || $info->getCcExpMonth() <> null || $info->getCcCidEnc() <> null){
            $errorMsg = Mage::helper('payment')->__('Your checkout form is sending sensitive information to the server. Please contact your developer to fix this security leak.');
        }

        if($errorMsg){
            Mage::throwException($errorMsg);
        }

        return $this;
    }

    /**
     * Authorize payment abstract method
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        // Leave the transaction opened so it can later be captured in backend
        $payment->setIsTransactionClosed(false);

        $this->_doOpenpayTransaction($payment, $amount, false);

        return $this;
    }

    public function capture(Varien_Object $payment, $amount){

        Mage::log('capture:'.$payment);
        if(!$payment->hasOpenpayPaymentId()){
            $this->_doOpenpayTransaction($payment, $amount, true);
        }else{
            $this->_captureOpenpayTransaction($payment, $amount);
        }

        return $this;
    }

    public function refund(Varien_Object $payment, $amount){

        $order = $payment->getOrder();
        $is_guest = $order->getCustomerIsGuest();

        if($payment->getAmountPaid() <> $amount){
            throw new Exception($this->_getHelper()->__('OpenPay currently does not allow refunding partial or higher amounts.'));
        }
        if($is_guest){
            $this->_refundOrder($payment);
        }else{
            $this->_refundCustomer($payment);
        }

        return $this;
    }
    public function cancel(Varien_Object $payment){

        // void the order if canceled
        $this->void($payment);

        return $this;
    }
    public function void(Varien_Object $payment){

        $order = $payment->getOrder();
        $is_guest = $order->getCustomerIsGuest();

        if($is_guest){
            $this->_refundOrder($payment);
        }else{
            $this->_refundCustomer($payment);
        }

        return $this;
    }
    protected function _doOpenpayTransaction(Varien_Object $payment, $amount, $capture = true){

        /* Take actions for the different checkout methods */
        $checkout_method = $payment->getOrder()->getQuote()->getCheckoutMethod();
        $paymentRequest = Mage::app()->getRequest()->getPost('payment');
        $token = $paymentRequest['openpay_token'];
        $device_session_id = $paymentRequest['device_session_id'];

        try {
            switch ($checkout_method){
                case Mage_Sales_Model_Quote::CHECKOUT_METHOD_GUEST:
                    $charge = $this->_chargeCardInOpenpay($payment, $amount, $token, $device_session_id, $capture);
                    break;

                case Mage_Sales_Model_Quote::CHECKOUT_METHOD_LOGIN_IN:
                    // get the user, if no user create, then add payment
                    $customer = $payment->getOrder()->getCustomer();
                    $shippingAddress = $payment->getOrder()->getShippingAddress();

                    if (!$customer->openpay_user_id) {
                        // create OpenPay customer
                        $openpay_user = $this->_createOpenpayCustomer($customer, $shippingAddress);
                        $customer->setOpenpayUserId($openpay_user->id);
                        $customer->save();

                        $charge = $this->_chargeOpenpayCustomer($payment, $amount, $token, $openpay_user->id, $device_session_id, $capture);
                    }else{
                        $openpay_user = $this->_getOpenpayCustomer($customer->openpay_user_id);
                        $charge = $this->_chargeOpenpayCustomer($payment, $amount, $token, $openpay_user->id, $device_session_id, $capture);
                    }
                    break;

                default:
                    $charge = $this->_chargeCardInOpenpay($payment, $amount, $token, $device_session_id, $capture);
                    break;
            }
        } catch (OpenpayApiTransactionError $e) {
            Mage::throwException(Mage::helper('payment')->__('La tarjeta fue declinada, por favor verifique la información o intente con otra tarjeta'));
        } catch (OpenpayApiRequestError $e) {
           Mage::throwException(Mage::helper('payment')->__($e->getMessage()));
        } catch (OpenpayApiConnectionError $e) {
            Mage::throwException(Mage::helper('payment')->__($e->getMessage()));
        } catch (OpenpayApiAuthError $e) {
            Mage::throwException(Mage::helper('payment')->__($e->getMessage()));
        } catch (OpenpayApiError $e) {
            Mage::throwException(Mage::helper('payment')->__($e->getMessage()));
        }

        // Set Openpay confirmation number as Order_Payment openpay_token
        $payment->setOpenpayAuthorization($charge->authorization);
        $payment->setOpenpayCreationDate($charge->creation_date);
        $payment->setOpenpayPaymentId($charge->id);
        $payment->setTransactionId($charge->id);

        return $this;
    }

    /*
     * Set openpay object
     */
    protected function _setOpenpayObject(){
        /* Create OpenPay object */
        $this->_openpay = Openpay::getInstance(Mage::getStoreConfig('payment/common/merchantid'), Mage::getStoreConfig('payment/common/privatekey'));
         Openpay::setProductionMode(!Mage::getStoreConfig('payment/common/sandbox'));
    }

    /**
     * Retrieve model helper
     *
     * @return Openpay_Charges_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('charges');
    }

    /*
     * Charge Card using OpenPay
     */
    protected function _chargeCardInOpenpay($payment, $amount, $token, $device_session_id, $capture){

        $order = $payment->getOrder();
        $orderFirstItem = $order->getItemById(0);
        $numItems = $order->getTotalItemCount();

        /* Populate an array with the Data */
        $chargeData = array(
            'method' => 'card',
            'source_id' => $token,
            'device_session_id' => $device_session_id,
            'amount' => (float) $amount,
            'description' => $this->_getHelper()->__($orderFirstItem->getName())
                .(($numItems>1)?$this->_getHelper()->__('... and (%d) other items', $numItems-1): ''),
            'order_id' => $order->getIncrementId(),
            'capture' => $capture
        );

        $billingAddress = $payment->getOrder()->getBillingAddress();
        $shippingAddress = $payment->getOrder()->getShippingAddress();

        $chargeCustomer = array(          
            'name' => $shippingAddress->getFirstname(),
            'last_name' => $shippingAddress->getLastname(),
            'email' => $billingAddress->getEmail(),
            'requires_account' => false,
            'phone_number' => $shippingAddress->getTelephone(),
            'address' => array(
                'line1' => implode(' ', $shippingAddress->getStreet()),
                'state' => $shippingAddress->getRegion(),
                'city' => $shippingAddress->getCity(),
                'postal_code' => $shippingAddress->getPostcode(),
                'country_code' => $shippingAddress->getCountry_id()
            )
         );
              
        $chargeData['customer'] = $chargeCustomer;

        /* Create the request to OpenPay to charge the CC*/
        $charge = $this->_openpay->charges->create($chargeData);

        return $charge;
    }
    protected function _chargeOpenpayCustomer($payment, $amount, $token, $user_id, $device_session_id, $capture = true){

        $order = $payment->getOrder();
        $orderFirstItem = $order->getItemById(0);
        $numItems = $order->getTotalItemCount();

        $chargeData = array(

            'source_id' => $token,
            'device_session_id' => $device_session_id,
            'method' => 'card',
            'amount' => $amount,
            'description' => $this->_getHelper()->__($orderFirstItem->getName())
                .(($numItems>1)?$this->_getHelper()->__('... and (%d) other items', $numItems-1): ''),
            'order_id' => $order->getIncrementId(),
            'capture' => $capture);

        $customer = $this->_openpay->customers->get($user_id);
        $charge = $customer->charges->create($chargeData);

        return $charge;
    }

    /*
     * Create user in OpenPay
     */
    protected function _createOpenpayCustomer($customer, $shippingAddress){

        $customerData = array(
            'name' => $customer->firstname,
            'last_name' => $customer->lastname,
            'email' => $customer->email,
            'phone_number' => $shippingAddress->telephone,
            'requires_account' => false,
            'address' => array(
                'line1' => $shippingAddress->street,
                'postal_code' => $shippingAddress->postcode,
                'state' => $shippingAddress->region,
                'city' => $shippingAddress->city,
                'country_code' => $shippingAddress->country_id));

        $customer = $this->_openpay->customers->add($customerData);

        return $customer;
    }

    protected function _getOpenpayCustomer($user_token){

        $customer = $this->_openpay->customers->get($user_token);

        return $customer;
    }
    protected function _refundOrder($payment){

        $refundData = array(
            'description' => $this->_getHelper()->__('Refunded')
        );

        $charge = $this->_openpay->charges->get($payment->getLastTransId());

        $charge->refund($refundData);
    }
    protected function _captureOpenpayTransaction($payment, $amount){

        $order = $payment->getOrder();
        $openpay_payment_id = $payment->getOpenpayPaymentId();

        if($order->hasCustomerId()){
            $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
            $op_customer = $this->_openpay->customers->get($customer->getOpenpayUserId());
            $charge = $op_customer->charges->get($openpay_payment_id);

            $captureData = array('amount' => $amount );
            $charge->capture($captureData);
        }else{
            $charge = $this->_openpay->charges->get($openpay_payment_id);

            $captureData = array('amount' => $amount );
            $charge->capture($captureData);
        }

        return $charge;

    }
    protected function _refundCustomer($payment){
        $order = $payment->getOrder();
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());

        $refundData = array(
            'description' => $this->_getHelper()->__('Refunded')
        );

        $op_customer = $this->_openpay->customers->get($customer->getOpenpayUserId());
        $charge = $op_customer->charges->get($payment->getOpenpayPaymentId());

        $charge->refund($refundData);
    }
}