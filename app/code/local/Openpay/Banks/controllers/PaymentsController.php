<?php


/* Include OpenPay SDK */
include_once(Mage::getBaseDir('lib') . DS . 'Openpay' . DS . 'Openpay.php');

class Openpay_Banks_PaymentsController extends Mage_Core_Controller_Front_Action{

    protected $_openpay;

    /**
     * Initialize action
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    protected function _construct(){
        // initialize openpay object
        $this->_setOpenpayObject();
    }

    public function printAction(){
        
        /**
         * Magento utiliza el timezone UTC, por lo tanto sobreescribimos este 
         * por la configuración que se define en el administrador         
         */
        $store_tz = Mage::getStoreConfig('general/locale/timezone');
        date_default_timezone_set($store_tz);  

        $request = Mage::app()->getRequest();

        $order = Mage::getModel('sales/order')->loadByIncrementId($request->order);

        if($order->customer_is_guest){
            if($order->getPayment()->getOpenpayPaymentId() <> $request->id){
                throw new Exception('You do not have enough permissions to see this page');
            }
            $charge = $this->_openpay->charges->get($order->getPayment()->openpay_payment_id);

        }else{
            $customer = Mage::getModel('customer/customer')->load($order->customer_id);

            if(!$this->_userIsCurrentUser($customer->getId())){
                throw new Exception('You must login first to see this page');
            }

            $op_customer = $this->_openpay->customers->get($customer->openpay_user_id);
            $charge = $op_customer->charges->get($order->getPayment()->openpay_payment_id);
        }

        if(isset($charge->due_date) && !trim($charge->due_date)==='' && strtotime($charge->due_date) < time()){
            throw new Exception('This payment sheet has expired, please place a new order');
        }
        $this->loadLayout();

        $block = $this->getLayout()->getBlock('root');
        $block->setPdfUrl($this->getSpeiPdfUrl().'/'.Mage::getStoreConfig('payment/common/merchantid').'/'.$charge->id);
        $block->setTemplate('openpay/banks_print.phtml');

        $this->renderLayout();
    }

    public function confirmAction(){
        $request = Mage::app()->getRequest();

        $post_body = $request->getRawBody();
        $post_body_obj = json_decode($post_body);

        // Check the request is for a store payment and it has been applied
        if($this->_shouldCaptureStorePayment($post_body_obj)){

            $order = Mage::getModel('sales/order')->loadByIncrementId($post_body_obj->transaction->order_id);

            if(!$order->canInvoice()){
                Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
            }

            // Double check payment in OpenPay
            $charge = $this->_getOpenpayCharge($order);

            if($charge->status == 'completed' && $order->getTotalDue() == $charge->serializableData['amount']){

                /**
                 * Create invoice
                 * The invoice will be in 'Pending' state
                 */
                $invoiceId = Mage::getModel('sales/order_invoice_api')->create($order->getIncrementId(), array());

                /**
                 * Pay invoice
                 * i.e. the invoice state is now changed to 'Paid'
                 */
                $invoice = Mage::getModel('sales/order_invoice')->loadByIncrementId($invoiceId);
                $invoice->capture()->save();

                $order->getPayment()->capture($invoice);
                $order->getPayment()->setOpenpayAuthorization($charge->authorization);
                $order->save();

                Mage::getModel('core/config')->deleteConfig('payment/banks/verification_code');
            }


        }elseif($post_body_obj->type == 'verification'){
            Mage::getModel('core/config')->saveConfig('payment/common/verification_code', $post_body_obj->verification_code);
            Mage::app()->getCacheInstance()->cleanType('config');
        }
    }

    protected function _shouldCaptureStorePayment($post_body_obj){
        if($post_body_obj->type <> 'charge.succeeded') return false;
        if($post_body_obj->transaction->method <> '') return false;
        if($post_body_obj->transaction->status <> 'completed') return false;
        return true;
    }
    /*
    * Set openpay object
    */
    protected function _setOpenpayObject(){
        /* Create OpenPay object */
        $this->_openpay = Openpay::getInstance(Mage::getStoreConfig('payment/common/merchantid'), Mage::getStoreConfig('payment/common/privatekey'));
         Openpay::setProductionMode(!Mage::getStoreConfig('payment/common/sandbox'));
    }

    protected function _userIsCurrentUser($user_id){

        $customer_session_id = Mage::getSingleton('customer/session')->getCustomer()->getId();

        if($customer_session_id == $user_id){
            return true;
        }else{
            return false;
        }
    }

    protected function _lastOrderId(){
        return Mage::getSingleton('checkout/session')->getLastOrderId();
    }

    protected function _getOpenpayCharge($order){
        $op_charge_id = $order->getPayment()->getOpenpayPaymentId();
        if($order->customer_is_guest){
            $charge = $this->_openpay->charges->get($op_charge_id);
        }else{
            $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
            $op_customer = $this->_openpay->customers->get($customer->getOpenpayUserId());
            $charge = $op_customer->charges->get($op_charge_id);
        }

        return $charge;
    }
    
    private function getLongGlobalDateFormat($date){
        $time = strtotime($date);        
        $month_number = date('n', $time);
        $months_array = array(
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        );
        return date('j', $time).' de '.$months_array[$month_number].' de '.date('Y', $time).', a las '.date('g:i A', $time);
    }
    
    private function getSpeiPdfUrl() {
        return Mage::getStoreConfig('payment/common/sandbox') ? 'https://sandbox-dashboard.openpay.mx/spei-pdf' : 'https://dashboard.openpay.mx/spei-pdf';        
    }
    
}