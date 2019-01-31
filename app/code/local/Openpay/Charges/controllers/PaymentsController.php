<?php

/* Include OpenPay SDK */
include_once(Mage::getBaseDir('lib') . DS . 'Openpay' . DS . 'Openpay.php');

class Openpay_Charges_PaymentsController extends Mage_Core_Controller_Front_Action{

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
    
    public function confirmAction(){
        
        $request = Mage::app()->getRequest();                
        
        $charge = $this->_openpay->charges->get($request->getParam('id'));
        
        Mage::log('Openpay charge status: '.$charge->status);
        Mage::log('Openpay Order ID: '.$charge->order_id);
        
        $order = Mage::getModel('sales/order')->loadByIncrementId($charge->order_id);     
        $session = Mage::getSingleton('checkout/session');
        
        Mage::log('getLastQuoteId: '.$session->getLastQuoteId());
        Mage::log('getLastOrderId: '.$session->getLastOrderId());
        Mage::log('getLastSuccessQuoteId: '.$session->getLastSuccessQuoteId());
        Mage::log('getLastRealOrderId: '.$session->getLastRealOrderId());        
        
        
        if($charge->status == 'completed' && $charge->order_id == $session->getLastRealOrderId()) {       
                        
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
            $order->save();
                    
            $payment = $order->getPayment();
            $payment->setOpenpayAuthorization($charge->authorization);
            $payment->setData('openpay_3d_secure', 0);            
            $payment->save();        
            
            // Se actualiza el estatu de la Orden
//            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
//            $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
//            $order->setTotalPaid($charge->amount);              
//            $order->save();
                                    
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
            $this->_redirect('checkout/onepage/success');
            return;
        } else {            
            // Se cancela la orden
            if ($order->getId()) {
                $order->cancel()->save();
            }            
            $this->_redirect('checkout/onepage/failure');            
            return;
        }        
        
    }
    
    protected function _setOpenpayObject(){        
        $this->_openpay = Openpay::getInstance(Mage::getStoreConfig('payment/common/merchantid'), Mage::getStoreConfig('payment/common/privatekey'));
        Openpay::setProductionMode(!Mage::getStoreConfig('payment/common/sandbox'));
    }
    
    
}