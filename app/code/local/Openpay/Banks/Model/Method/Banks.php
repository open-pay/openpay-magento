<?php
/**
 * Created by PhpStorm.
 * User: Xavier de
 * Date: 12/05/14
 * Time: 06:21 PM
 */

/* Include OpenPay SDK */
include_once(Mage::getBaseDir('lib') . DS . 'Openpay' . DS . 'Openpay.php');

class Openpay_Banks_Model_Method_Banks extends Mage_Payment_Model_Method_Abstract
{
    protected $_code                    = 'banks';
    protected $_isGateway               = true;
    protected $_canOrder                = true;

    protected $_canAuthorize            = false;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
    protected $_canVoid                 = false;

    protected $_isInitializeNeeded          = true;

    protected $_formBlockType = 'banks/form_banks';
    protected $_infoBlockType = 'banks/payment_info_banks';



    public function __construct(){

        /* initialize openpay object */
        $this->_setOpenpayObject();
    }

    /**
     * Order payment abstract method
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function order(Varien_Object $payment, $amount)
    {
        if (!$this->canOrder()) {
            Mage::throwException(Mage::helper('payment')->__('Order action is not available.'));
        }else{
            $this->_doOpenpayTransaction($payment, $amount);
        }
        return $this;
    }

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function initialize($paymentAction, $stateObject)
    {
        $order = $this->getInfoInstance()->getOrder();
        $payment = $order->getPayment();
        $amount = $order->getBaseTotalDue();
        $this->_doOpenpayTransaction($payment, $amount);

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

    protected function _doOpenpayTransaction(Varien_Object $payment, $amount){
        /* Take actions for the different checkout methods */
        $checkout_method = $payment->getOrder()->getQuote()->getCheckoutMethod();

        switch ($checkout_method){
            case Mage_Sales_Model_Quote::CHECKOUT_METHOD_GUEST:
                $charge = $this->_prepareStorePaymentSheetInOpenpay($payment, $amount);
                break;

            case Mage_Sales_Model_Quote::CHECKOUT_METHOD_LOGIN_IN:
                // get the user, if no user create, then add payment
                $customer = $payment->getOrder()->getCustomer();

                if (!$customer->openpay_user_id) {
                    // create OpenPay customer
                    $openpay_user = $this->_createOpenpayCustomer($payment);
                    $customer->setOpenpayUserId($openpay_user->id);
                    $customer->save();

                    $charge = $this->_prepareStorePaymentSheetForCustomer($payment, $amount, $openpay_user->id);
                }else{
                    $openpay_user = $this->_getOpenpayCustomer($customer->openpay_user_id);
                    $charge = $this->_prepareStorePaymentSheetForCustomer($payment, $amount, $openpay_user->id);
                }
                break;

            default:
                $charge = $this->_prepareStorePaymentSheetInOpenpay($payment, $amount);
                break;

        }

        // Set Openpay confirmation number as Order_Payment openpay_token
        $payment->setOpenpayCreationDate($charge->creation_date);
        $payment->setOpenpayPaymentId($charge->id);
        $payment->setTransactionId($charge->id);
        $payment->setOpenpayBarcode($charge->payment_method->reference);

        return $this;
    }

    /*
     * Create Payment sheet information including
     * BarCode using OpenPay
     */
    protected function _prepareStorePaymentSheetInOpenpay(Varien_Object $payment, $amount){

        $order = $payment->getOrder();
        $orderFirstItem = $order->getItemById(0);
        $numItems = $order->getTotalItemCount();

        $hoursBeforeCancel = Mage::getStoreConfig('payment/banks/hours_active');

        /* Populate an array with the Data */
        $chargeData = array(
            'method' => 'bank_account', 
            'amount' => (float) $amount,
            'description' => Mage::app()->getStore()->getName() . ' Magento Store: '
                .$this->_getHelper()->__($orderFirstItem->getName())
                .(($numItems>1)?$this->_getHelper()->__('... and (%d) other items', $numItems-1): ''),
            'order_id' => $order->getIncrementId(),

        );

        if($hoursBeforeCancel){
            $chargeData['due_date'] = date('c', $this->_addHoursToTime(time(), $hoursBeforeCancel));
        }

        /* Create the request to OpenPay to charge the CC*/
        $charge = $this->_openpay->charges->create($chargeData);

        return $charge;
    }

    protected function _getOpenpayCustomer($user_token){

        $customer = $this->_openpay->customers->get($user_token);

        return $customer;
    }

    /*
     * Create user in OpenPay
     */
    protected function _createOpenpayCustomer($payment){
        $order = $payment->getOrder();
        $customer = $order->getCustomer();
        $shippingAddress = $order->getShippingAddress();

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
    protected function _prepareStorePaymentSheetForCustomer($payment, $amount, $user_id){

        $order = $payment->getOrder();
        $orderFirstItem = $order->getItemById(0);
        $numItems = $order->getTotalItemCount();

        $hoursBeforeCancel = Mage::getStoreConfig('payment/banks/hours_active');

        $chargeData = array(
            'method' => 'bank_account',
            'amount' => $amount,
            'description' => Mage::app()->getStore()->getName() . ' Magento Store: '
                .$this->_getHelper()->__($orderFirstItem->getName())
                .(($numItems>1)?$this->_getHelper()->__('... and (%d) other items', $numItems-1): ''),
            'order_id' => $order->getIncrementId(),
        );

        if($hoursBeforeCancel){
            $chargeData['due_date'] = date('c', $this->_addHoursToTime(time(), $hoursBeforeCancel));
        }

        $customer = $this->_openpay->customers->get($user_id);
        $charge = $customer->charges->create($chargeData);

        return $charge;
    }
    protected function _addHoursToTime($time, $hours){
        $seconds = $hours * 60 * 60;
        $newTime = $time + $seconds;
        return $newTime;
    }
}