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
    protected $_canSaveCc     = false;

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
                'cc_last4' => '',
                'cc_exp_year' => '',
                'cc_exp_month' => '',
            ));
            $info = $this->getInfoInstance();

            $this->_openpay_token       = $paymentRequest['openpay_token'];
            $this->_device_session_id   = $paymentRequest['device_session_id'];
            $this->_interest_free = null;
            
            if(isset($paymentRequest['interest_free'])){
                $this->_interest_free = $paymentRequest['interest_free'];
            }

            $info->setOpenpayToken($paymentRequest['openpay_token'])->setDeviceSessionId($paymentRequest['device_session_id'])->setInterestFree($paymentRequest['interest_free']);
            
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

        $info->setCcCid(null)
            ->setCcExpYear(null)
            ->setCcCidEnc(null)
            ->setCcExpMonth(null);
        return $this;
    }

    public function validate() {
        
        //parent::validate();
        /* we call parent's validate() function!
         * if the code got this far, it means the cc type is none of the supported
         * ones implemented by Magento and now it gets interesting
         */

        $info = $this->getInfoInstance();
        $errorMsg = false;
        $availableTypes = explode(',',$this->getConfigData('cctypes'));
        
        /** CC_number validation is not done because it should not get into the server **/
        if (!in_array($info->getCcType(), $availableTypes)){
            $errorMsg = Mage::helper('payment')->__('Credit card type is not allowed for this payment method.');
        }

        /** Verify they are not sending sensitive information **/
        //if($info->getCcExpYear() <> null || $info->getCcExpMonth() <> null || $info->getCcCidEnc() <> null || $info->getCcNumber() <> null){
        if($info->getCcExpYear() <> null || $info->getCcExpMonth() <> null || $info->getCcCidEnc() <> null){
            $errorMsg = Mage::helper('payment')->__('Your checkout form is sending sensitive information to the server. Please contact your developer to fix this security leak.');
        }

        // Carnet
        $ccNumber = $info->getCcNumber();
        /* this is Carnet regex pattern.
         * it's different for every cc type, so beware
         */
        if($info->getCcType()=='CN' && !preg_match('/^[0-9]{16}$/', $ccNumber)){
            Mage::throwException($this->_getHelper()->__('Credit card number mismatch with credit card type.'));
        }
        // now we retrieve our CCV regex pattern and validate against it
        $verificationRegex = $this->getVerificationRegEx();
        if(!preg_match($verificationRegex[$info->getCcType()], $info->getCcCid())) {
            $errorMsg = Mage::getHelper()->__('Please enter a valid credit card verification number.');            
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
    
    protected function _doOpenpayTransaction(Varien_Object $payment, $amount, $capture = true, $use_3d_secure = false){

        /* Take actions for the different checkout methods */        
        $checkout_method = $payment->getOrder()->getQuote()->getCheckoutMethod();
        $paymentRequest = Mage::app()->getRequest()->getPost('payment');
        $token = $paymentRequest['openpay_token'];
        $device_session_id = $paymentRequest['device_session_id'];
        $interest_free = null;
        $use_card_points = $paymentRequest['use_card_points'];
        $additional_data = null;
        
        if(isset($paymentRequest['interest_free'])){
            $interest_free = $paymentRequest['interest_free'];
        }
        
        if(Mage_Sales_Model_Quote::CHECKOUT_METHOD_LOGIN_IN) {
            $customer = $payment->getOrder()->getCustomer();            
            $shippingAddress = $payment->getOrder()->getShippingAddress();
            
            if ($customer->openpay_user_id) {
                try {
                    $this->_getOpenpayCustomer($customer->openpay_user_id);    
                } catch (Exception $e) {                    
                    $openpay_user = $this->_createOpenpayCustomer($customer, $shippingAddress); 
                    $customer->setOpenpayUserId($openpay_user->id);
                    $customer->save();                    
                }
            } else {
                $openpay_user = $this->_createOpenpayCustomer($customer, $shippingAddress); 
                $customer->setOpenpayUserId($openpay_user->id);
                $customer->save();
            }            
        }
        
        
        try {
            switch ($checkout_method){
                case Mage_Sales_Model_Quote::CHECKOUT_METHOD_GUEST:
                    $charge = $this->_chargeCardInOpenpay($payment, $amount, $token, $device_session_id, $capture, $interest_free, $use_3d_secure, $use_card_points);
                    break;

                case Mage_Sales_Model_Quote::CHECKOUT_METHOD_LOGIN_IN:
                    // get the user, if no user create, then add payment
                    $customer = $payment->getOrder()->getCustomer();
                    $openpay_user = $this->_getOpenpayCustomer($customer->openpay_user_id);    
                    
                    $charge = $this->_chargeOpenpayCustomer($payment, $amount, $token, $openpay_user->id, $device_session_id, $capture, $interest_free, $use_3d_secure, $use_card_points);
                    break;

                default:
                    $charge = $this->_chargeCardInOpenpay($payment, $amount, $token, $device_session_id, $capture);
                    break;
            }
            
            // Set Openpay confirmation number as Order_Payment openpay_token        
            $payment->setOpenpayAuthorization($charge->authorization);
            $payment->setOpenpayCreationDate($charge->creation_date);
            $payment->setOpenpayPaymentId($charge->id);
            $payment->setTransactionId($charge->id);
            $payment->setData('cc_last4', substr($charge->card->card_number, -4));
            
            if($charge->payments) {
                $additional_data = 'Pago a '.$charge->payments.' MSI. ';                  
            }
            
            if($charge->card_points) {
                $additional_data = 'Se utilizaron '.$charge->card_points->used.' puntos '.ucfirst($charge->card->points_type).' para la compra. '.$charge->card_points->caption;                
            }            
            
            // Se guarda información adicional: MSI y uso de puntos
            $payment->setData('additional_data', $additional_data);

            // Si la transacción utiliza 3D Secure se realiza el redireccionamiento
            if($charge->status == 'charge_pending' && ($charge->payment_method && $charge->payment_method->type == 'redirect')) {
                Mage::log('Redirect URL: '.$charge->payment_method->url);                        
                $payment->setData('openpay_3d_secure', 1);
                $payment->setData('openpay_3d_secure_url', $charge->payment_method->url);      
                $payment->save();                    
            }       

            return $this;                        
        } catch (Exception $e) {            
            $use_3d_secure = Mage::getStoreConfig('payment/charges/use_3d_secure') == '1' ? true : false;            
            if($e->getErrorCode() == '3005' && $use_3d_secure) {                      
                $this->_doOpenpayTransaction($payment, $amount, $capture, $use_3d_secure);                
            } else {                
                $this->error($e);            
            }                        
        }                
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
    protected function _chargeCardInOpenpay($payment, $amount, $token, $device_session_id, $capture, $interest_free, $use_3d_secure, $use_card_points){

        $order = $payment->getOrder();
        $orderFirstItem = $order->getItemById(0);
        $numItems = $order->getTotalItemCount();

        /* Populate an array with the Data */
        $chargeData = array(
            'method' => 'card',
            'source_id' => $token,
            'device_session_id' => $device_session_id,
            'amount' => (float) $amount,
            'description' => $this->_getHelper()->__($orderFirstItem->getName()).(($numItems>1)?$this->_getHelper()->__('... and (%d) other items', $numItems-1): ''),            
            'order_id' => $order->getIncrementId(),
            'use_card_points' => $use_card_points,
            'capture' => $capture
        );
        
        if($interest_free > 1){
            $chargeData['payment_plan'] = array('payments' => (int)$interest_free);
        }        
        
        if($use_3d_secure) {
            $chargeData['use_3d_secure'] = true;
            $chargeData['redirect_url'] = Mage::getBaseUrl().'charges/payments/confirm';
        }
        

        $billingAddress = $payment->getOrder()->getBillingAddress();
        //$shippingAddress = $payment->getOrder()->getShippingAddress();
        
        $chargeCustomer = array(          
            'name' => $billingAddress->getFirstname(),
            'last_name' => $billingAddress->getLastname(),
            'email' => $billingAddress->getEmail(),
            'requires_account' => false,
            'phone_number' => $billingAddress->getTelephone()            
         );
                
        // Validate all required data for Customer's Address object
        if($billingAddress->getStreet() && $billingAddress->getRegion() && $billingAddress->getCity() && $billingAddress->getCountry_id() && $billingAddress->getPostcode()){
            $chargeCustomer['address'] =  array(
                'line1' => implode(' ', $billingAddress->getStreet()),
                'state' => $billingAddress->getRegion(),
                'city' => $billingAddress->getCity(),
                'postal_code' => $billingAddress->getPostcode(),
                'country_code' => $billingAddress->getCountry_id()
            );
        }
              
        $chargeData['customer'] = $chargeCustomer;

        /* Create the request to OpenPay to charge the CC*/
        $charge = $this->_openpay->charges->create($chargeData);

        return $charge;
    }
    
    protected function _chargeOpenpayCustomer($payment, $amount, $token, $user_id, $device_session_id, $capture, $interest_free, $use_3d_secure, $use_card_points){

        $order = $payment->getOrder();
        $orderFirstItem = $order->getItemById(0);
        $numItems = $order->getTotalItemCount();

        $chargeData = array(
            'source_id' => $token,
            'device_session_id' => $device_session_id,
            'method' => 'card',
            'amount' => $amount,
            'description' => $this->_getHelper()->__($orderFirstItem->getName()).(($numItems > 1) ? $this->_getHelper()->__('... and (%d) other items', $numItems - 1) : ''),            
            'order_id' => $order->getIncrementId(),   
            'use_card_points' => $use_card_points,
            'capture' => $capture
        );
        
        if($interest_free > 1){
            $chargeData['payment_plan'] = array('payments' => (int)$interest_free);
        }        
        
        if($use_3d_secure) {
            $chargeData['use_3d_secure'] = true;
            $chargeData['redirect_url'] = Mage::getBaseUrl().'charges/payments/confirm';
        }

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
            'requires_account' => false
        );
        
        if($shippingAddress->street && $shippingAddress->postcode && $shippingAddress->region && $shippingAddress->city && $shippingAddress->country_id) {
            $customerData['address'] = array(
                'line1' => $shippingAddress->street,
                'postal_code' => $shippingAddress->postcode,
                'state' => $shippingAddress->region,
                'city' => $shippingAddress->city,
                'country_code' => $shippingAddress->country_id
            );
        }        

        return $this->_openpay->customers->add($customerData);
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
    
    public function error($e)
    {        
        switch ($e->getErrorCode()) {
             /* ERRORES GENERALES */
            case '1000':
            case '1004':
            case '1005':
                $msg = 'Servicio no disponible.';
                break;
            /* ERRORES TARJETA */
            case '3001':
            case '3004':
            case '3005':
            case '3007':
                $msg = 'La tarjeta fue rechazada.';
                break;
            case '3002':
                $msg = 'La tarjeta ha expirado.';
                break;
            case '3003':
                $msg = 'La tarjeta no tiene fondos suficientes.';
                break;
            case '3006':
                $msg = 'La operación no esta permitida para este cliente o esta transacción.';
                break;
            case '3008':
                $msg = 'La tarjeta no es soportada en transacciones en línea.';
                break;
            case '3009':
                $msg = 'La tarjeta fue reportada como perdida.';
                break;
            case '3010':
                $msg = 'El banco ha restringido la tarjeta.';
                break;
            case '3011':
                $msg = 'El banco ha solicitado que la tarjeta sea retenida. Contacte al banco.';
                break;
            case '3012':
                $msg = 'Se requiere solicitar al banco autorización para realizar este pago.';
                break;
            default: /* Demás errores 400 */
                //$msg = 'La petición no pudo ser procesada.';
                $msg = $e->getDescription();
                break;
        }
        
        $error = 'ERROR '.$e->getErrorCode().'. '.$msg;        
        Mage::throwException(Mage::helper('payment')->__($error));
        
    }
    
    // Carnet CCV
    public function getVerificationRegEx() {
        return array_merge(parent::getVerificationRegEx(), array(
            'CN' => '/^[0-9]{3}$/' // Carnet CCV
       ));
    }
    
    // Carnet CCV
    public function OtherCcType($type) {
        return in_array($type, array('DC'));
    }
     
}