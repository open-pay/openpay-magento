![Openpay Magento](http://www.openpay.mx/img/github/magento.jpg)

Requirements
--------------
PHP 5.0.3 or greater

Supported Versions
-------------------
- Magento Community Edition 1.7.0.2
- Magento Community Edition 1.8.0.0 
- Magento Community Edition 1.8.1.0
- Magento Community Edition 1.9.0.0
- Magento Community Edition 1.9.0.1 

Installation
------------

- Option A (Recommended)

1. Download latest [.tar.gz file](https://github.com/open-pay/openpay-magento/raw/master/Openpay_Charges-0.1.7.tgz)
2. In your admin go to **System > Magento Connect > Magento Connect Manager** 
3. Update the .tar.gz file using **Direct package file upload**

![Direct Package Upload](https://s3.amazonaws.com/images.openpay/direct-package-file-upload.png)

You will see the result at the end of the page

![Result Direct Package Upload](https://s3.amazonaws.com/images.openpay/result-direct-package-file-upload.png)

- Option B

Install with [modman](https://github.com/colinmollenhour/modman)

    $ cd /path/to/magento
    $ modman init
    $ modman clone https://github.com/open-pay/openpay-magento.git

- Option C

1. Copy the folders **app, lib** to the Magento root installation. Make sure to keep the Magento folders structure intact.
2. In your admin go to **System > Cache Management** and clear all caches.
3. Go to **System > IndexManagement** and select all fields. Then click in Reindex Data.

Configuration
--------------
1. Go to **System > Configuration > Sales > Payment Methods**. Select Openpay.
3. Set your PUBLIC_KEY, MERCHANT_ID, PRIVATE_KEY (Get this values from the dashboard) on "Openpay Common Resources" section.
![magento configuration](https://s3.amazonaws.com/images.openpay/magento-config.png)
4. Enable the payments methods that you want to have
5. Save your config

Configure state as required
--------------------
Go to **System > General > States Options** and select all allowed countries

Payment Notifications (Stores and Banks)
--------------------
For receive payment notifications at the moment the pay was done, you need to configure a webhook notification in the Openpay Dashboard.

**Note:** Configure one webhook for store and other for bank.

1.- Go to "Configuraciones" -> "Agregar Webhook" and configure the URL and the events

    For Store
    http://mymagento-store.com/stores/payments/confirm
    For Bank
    http://mymagento-store.com/banks/payments/confirm
    
    Select "Personalizar Eventos" and check all the options in "Cargos" section.
![webhook configuration](https://s3.amazonaws.com/images.openpay/webhook_configuration.png)

2.- You will receive a verification code in your magento store, to see your code go to "Openpay Common Resources" and copy it. Use this code to verify your webhook in the Openpay Dashboard.


