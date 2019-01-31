<?php

class Openpay_Stores_Model_Observer {

    public function checkoutOnepageControllerSuccessAction($order_ids) {
        if (Mage::getConfig()->getModuleConfig('Openpay_Stores')->is('active', 'true')) {
            $order_ids_list = $order_ids->getOrderIds();
            $order_id = $order_ids_list[0];

            $order = Mage::getModel('sales/order')->load($order_id);

            $code = Mage::getModel('Openpay_Stores_Model_Method_Stores')->getCode();

            if ($order->getPayment()->getMethod() == $code) {
                $args = array(
                    'order' => $order->getIncrementId(),
                    'id' => $order->getPayment()->getOpenpayPaymentId(),
                );
                
                $this->sendPdfReceipt($order);

                $this->redirect('print', 'payments', $code, $args);
            }
        }

        return $this;
    }

    protected function redirect($action, $controller = null, $module = null, array $params = null) {
        $response = Mage::app()->getResponse();
        $response->setRedirect(Mage::helper('adminhtml')->getUrl($module . '/' . $controller . '/' . $action, $params));
    }

    private function getPdfReceipt($order) {
        $openpay = Openpay::getInstance(Mage::getStoreConfig('payment/common/merchantid'), Mage::getStoreConfig('payment/common/privatekey'));
        Openpay::setProductionMode(!Mage::getStoreConfig('payment/common/sandbox'));

        if ($order->customer_is_guest) {
            $charge = $openpay->charges->get($order->getPayment()->getOpenpayPaymentId());
        } else {
            $customer = Mage::getModel('customer/customer')->load($order->customer_id);

            if (!$this->userIsCurrentUser($customer->getId())) {
                throw new Exception('You must login first to see this page');
            }

            $op_customer = $openpay->customers->get($customer->getOpenpayUserId());
            $charge = $op_customer->charges->get($order->getPayment()->getOpenpayPaymentId());
        }
        
        return $this->getStoresPdfUrl().'/'.Mage::getStoreConfig('payment/common/merchantid').'/'.$charge->payment_method->reference;
    }
    
    private function userIsCurrentUser($user_id){
        $customer_session_id = Mage::getSingleton('customer/session')->getCustomer()->getId();
        if($customer_session_id == $user_id){
            return true;
        }else{
            return false;
        }
    }
    
    private function getStoresPdfUrl() {        
        return Mage::getStoreConfig('payment/common/sandbox') ? 'https://sandbox-dashboard.openpay.mx/paynet-pdf' : 'https://dashboard.openpay.mx/paynet-pdf';
    }

    private function sendPdfReceipt($order) {
        $pdf = $this->getPdfReceipt($order);
        
        Mage::log('sendPdfReceipt() => '.$pdf);
        
        $mimeType = Zend_Mime::TYPE_OCTETSTREAM;
        $disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
        $encoding = Zend_Mime::ENCODING_BASE64;
        $subject = 'Recibo de pago | Orden #'.$order->getIncrementId();
        $billing = $order->getBillingAddress();
        $sender_name = Mage::getStoreConfig('trans_email/ident_general/name');
        $sender_email = Mage::getStoreConfig('trans_email/ident_general/email');
        $html = $this->getHtmlContent($subject);
        
        Mage::log('$sender_name => '.$sender_name);
        Mage::log('$sender_email => '.$sender_email);
        Mage::log('$billing->getFirstname() => '.$billing->getFirstname());
        Mage::log('$order->getCustomerEmail() => '.$order->getCustomerEmail());      
        Mage::log('$order->getCustomerName() => '.$order->getCustomerName());        
        
        $mail = new Zend_Mail('utf-8');        
        $mail->setBodyHtml($html);
        $mail->setSubject($subject);
        $mail->setFrom($sender_email, $sender_name); 
        $mail->addTo($order->getCustomerEmail(), $order->getCustomerName());
        $mail->createAttachment(file_get_contents($pdf), $mimeType, $disposition, $encoding, 'recibo_pago.pdf');          
                
        try {
            $mail->send();
        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    }
    
    private function getHtmlContent($title) {
        $html = '<h1>'.$title.'</h1><p>A continuación encontrarás como archivo adjunto a este correo el recibo de pago con el cual podrás presentarte en tu tienda más cercana para realizar tu pago.</p>';        
        return $html;
    }

}
