<script type="text/javascript">
    //<![CDATA[
    
    OpenPay.setId('<?php echo Mage::getStoreConfig('payment/common/merchantid'); ?>');
    OpenPay.setApiKey('<?php echo Mage::getStoreConfig('payment/common/publickey'); ?>');

    <?php if (Mage::getStoreConfig('payment/common/sandbox')): ?>
        OpenPay.setSandboxMode(true);        
    <?php endif; ?>
        
    // create openpay device session id for fraud prevention        
    var deviceSessionId = OpenPay.deviceData.setup();
    console.log(deviceSessionId);
    
    var oneStepForm = document.getElementById("onestepcheckout-form");
    var oneStepFormTwo = document.getElementById("one-step-checkout-form");
    var oneStepFormThree = document.getElementById("co-form");    
    
    jQuery(document).ready(function(){        
        jQuery("#review-btn").attr('onclick', null);
        jQuery("#review-btn").click(function(){
           var form = new VarienForm('co-form');
            if (form.validator.validate()) {
                processPayment();
            }
        });
        
        var total = <?php echo Mage::helper('checkout/cart')->getQuote()->getGrandTotal() ?>;
        
        jQuery(document).on("change", "#charges_interest_free", function() {        
            var monthly_payment = 0;
            var months = parseInt(jQuery(this).val());     

            if (months > 1) {
                jQuery("#total-monthly-payment").css("display", "inline");
            } else {
                jQuery("#total-monthly-payment").css("display", "none");
            }

            monthly_payment = total/months;
            monthly_payment = monthly_payment.toFixed(2);
            
            jQuery("#monthly-payment").text(monthly_payment);
        });
        
    });
    
    var processPayment = function () {        
        var currentMethod = getPaymentMethod();
        console.log('processPayment');
        if (currentMethod === 'charges') {
            if (isCardValid()) {                
                console.log('createOpenpayToken()');
                createOpenpayToken();
            } else {                
                openpayTempSave();
            }
        } else {            
            openpayTempSave();
        }
    }

    var success_callbak = function(e) {        
        
        // set openpay values to be saved in db
        document.getElementById('charges_card_token').value = e.data.id;
        document.getElementById('charges_device_session_id').value = deviceSessionId;
        
        // Se enmascara el valor de la tarjeta de crédito que se envía al servidor
        document.getElementById('charges_cc_number').value = e.data.card.card_number;
        console.log('Masked credit card!');
        
        if (window["IWD"] && IWD.OPC) {
            openpayTempSave();
        } else if (oneStepForm != null || oneStepFormTwo != null) {
            openpayTempSave();
        } else if (oneStepFormThree != null){    
            review.save();
        } else {
            var request = new Ajax.Request(
                payment.saveUrl, {
                    method: 'post',
                    onComplete: payment.onComplete,
                    onSuccess: payment.onSave,
                    onFailure: checkout.ajaxFailure.bind(checkout),
                    parameters: Form.serialize(payment.form)
                }
            );
        }
    }

    var error_callbak = function(e) {
        // on payment error display error and reset loading button
        alert(getErrorDescription(e.data));
        if (window["IWD"] && IWD.OPC) {
            IWD.OPC.Checkout.unlockPlaceOrder();
        } else if (oneStepForm != null) {
            already_placing_order = false;
            var submitelement = $('onestepcheckout-place-order');
            submitelement.removeClassName('grey').addClassName('orange');
            submitelement.disabled = false;
            submitelement.parentNode.lastChild.remove();
            return false;
        } else if (oneStepFormTwo != null) {
            already_placing_order = false;
            var submitelement = $('onestepcheckout-button-place-order');
            submitelement.disabled = false;
            $('onestepcheckout-place-order-loading').hide();
            $('onestepcheckout-button-place-order').removeClassName('place-order-loader');
            $('onestepcheckout-button-place-order').addClassName('btn-checkout');
        } else if (oneStepFormThree != null) {            
            already_placing_order = false;            
            return false;
        } else {
            payment.resetLoadWaiting();
        }
    }

    function getErrorDescription(data) {
        console.log("Openpay response: " + JSON.stringify(data));
        if (data.error_code == 1001 || data.error_code == 1003) {
            return "La dirección esta incompleta o es incorrecta, favor de verificarla.";
        }
        if (data.error_code == 2005) {
            return "Fecha de expiración inválida.";
        }
        if (data.error_code == 2006) {
            return "El código cvv es requerido.";
        }
        if (data.error_code == 2004) {
            return "Número de tarjeta inválido.";
        }
        if (data.error_code == 1004) {
            return "Servicio no disponible";
        }
        return "Ocurrió un error interno que no permitio que la operación se completara.";
    }

    function createOpenpayToken() {
        
        console.log('createOpenpayToken');
                        
        var expiration_year = document.getElementById('charges_expiration_yr').value.toString();

        var cardNumber = new String(document.getElementById('charges_cc_number').value);
        cardNumber = cardNumber.toString().replace(/\s/g, '');

        var tokenData = {
            "card_number": cardNumber,
            "holder_name": document.getElementById('charges_cc_owner').value,
            "expiration_year": expiration_year.substr(expiration_year.length - 2),
            "expiration_month": document.getElementById('charges_expiration').value,
            "cvv2": document.getElementById('charges_cc_cid').value
        };

        var select = document.getElementById('billing:region_id');
        var state = document.getElementById('billing:region') ? document.getElementById('billing:region').value.substring(0, 45) : "";

        if (state === "" && select) {
            state = select.options[select.selectedIndex].text;
        }
         
        if(validateElement('billing:city') && validateElement('billing:street1') && validateElement('billing:postcode') && validateElement('billing:country_id') && state !== "") {
            //Obtenerlos desde el formulario
            var addressData = {
                "city": document.getElementById('billing:city').value.substring(0, 30),
                "line1": document.getElementById('billing:street1').value.substring(0, 45),
                "line2": document.getElementById('billing:street2').value.substring(0, 45),
                "postal_code": document.getElementById('billing:postcode').value,  
                "state": state,
                "country_code": document.getElementById('billing:country_id').value.substring(0, 2)             
            }            
            tokenData['address'] = addressData;
        }

        OpenPay.token.create(tokenData, success_callbak, error_callbak);
        
    }

    function isCardValid() {        
        var validator = null;
                
        if(oneStepFormThree !== null){
            validator = new Validation('co-form');            
        }else{            
            validator = oneStepFormTwo === null ? new Validation(payment.form) : new Validation('one-step-checkout-form');
        }        
        
        if (validator.validate()) {              
            return true;
        } else {
            return false;
        }
    }

    if (window["IWD"] && IWD.OPC) {
        var initSaveOrder = function () {
            IWD.OPC.saveOrderStatus = true;
            IWD.OPC.Plugin.dispatch('saveOrderBefore');
            if (IWD.OPC.Checkout.isVirtual === false) {
                IWD.OPC.Checkout.lockPlaceOrder();
                IWD.OPC.Shipping.saveShippingMethod();
            } else {
                IWD.OPC.validatePayment();
            }
        }
        var openpayTempSave = function () {
            initSaveOrder();
        }
        IWD.OPC.initSaveOrder = function () {
            $(document).on('click', '.opc-btn-checkout', function () {

                if (IWD.OPC.Checkout.disabledSave == true) {
                    return;
                }
                var addressForm = new VarienForm('billing-new-address-form');
                if (!addressForm.validator.validate()) {
                    return;
                }

                if (!$j('input[name="billing[use_for_shipping]"]').prop('checked')) {
                    var addressForm = new VarienForm('opc-address-form-shipping');
                    if (!addressForm.validator.validate()) {
                        return;
                    }
                }
                processPayment();
            });
        }
    } else if (oneStepForm != null) {
        var openpayTempSave = function () {
            var form = new VarienForm('onestepcheckout-form');
            if (form.validator.validate()) {
                $('onestepcheckout-form').submit();
            } else {
                already_placing_order = false;
                var submitelement = $('onestepcheckout-place-order');
                submitelement.removeClassName('grey').addClassName('orange');
                submitelement.disabled = false;
                submitelement.parentNode.lastChild.remove();
            }
        }
        $('.onestepcheckout-place-order').each(function (elem) {
            elem.observe('click', function (e) {
                already_placing_order = true;
                var submitelement = $('onestepcheckout-place-order');
                submitelement.removeClassName('orange').addClassName('grey');
                submitelement.disabled = true;
                var loaderelement = new Element('span').
                        addClassName('onestepcheckout-place-order-loading').
                        update('Por favor espere, estamos procesando su orden...');
                submitelement.parentNode.appendChild(loaderelement);
                processPayment();
            });
        });
    } else if (oneStepFormTwo != null) {
        var openpayTempSave = function () {
            var form = new VarienForm('one-step-checkout-form');
            if (form.validator.validate()) {
                $('one-step-checkout-form').submit();
            } else {
                already_placing_order = false;
                var submitelement = $('onestepcheckout-button-place-order');
                submitelement.disabled = false;
                $('onestepcheckout-place-order-loading').hide();
                $('onestepcheckout-button-place-order').removeClassName('place-order-loader');
                $('onestepcheckout-button-place-order').addClassName('btn-checkout');
            }
        }

        function oscPlaceOrderOpenpay(element) {
            if (checkpayment()) {
                var submitelement = $('onestepcheckout-button-place-order');
                submitelement.disabled = true;
                already_placing_order = true;
                disable_payment();
                $('onestepcheckout-place-order-loading').show();
                $('onestepcheckout-button-place-order').removeClassName('btn-checkout');
                $('onestepcheckout-button-place-order').addClassName('place-order-loader');
                processPayment();
            }
        }

    } else {
        console.log('openpayPaymentSave');
        var openpayPaymentSave = payment.save;
        var openpayTempSave = function () {
            openpayPaymentSave.apply(payment);
        }
        payment.save = function () {                  
            console.log('payment_save');
            processPayment();
        }
    }
    
    function getPaymentMethod() {
        var options = document.getElementsByName('payment[method]');
        for (var i = 0; i < options.length; i++) {
            if ($(options[i].id).checked) {
                return $(options[i].id).value;
            }
        }
        return "";
    }
    
    function validateElement(elmId) {
        var elem = document.getElementById(elmId);
        if(typeof elem !== 'undefined' && elem !== null) {
          return true;
        }
        return false;
    }

    if (typeof $ !== 'undefined') {
        (function ($) {
            $('#charges_cc_number').cardNumberInput();
            if (oneStepFormTwo != null) {
                $("#onestepcheckout-button-place-order").attr("onclick", "");
                $("#onestepcheckout-button-place-order").on("click", oscPlaceOrderOpenpay);
            }
        })(jQuery);
    }
    
    //]]>
</script>