![Openpay Magento](http://www.openpay.mx/img/github/magento.jpg)

# Requirements

PHP 5.4 or greater


# Supported Version
- Magento Community Edition 1.9.4.0


# Installation

- Option A (Recommended)

1. Download latest [.tar.gz file](https://github.com/open-pay/openpay-magento/raw/master/Openpay_Charges-2.0.0.tgz)
2. In your admin go to **System > Magento Connect > Magento Connect Manager** 
3. Update the .tar.gz file using **Direct package file upload**

![Direct Package Upload](https://s3.amazonaws.com/images.openpay/direct-package-file-upload.png)

You will see the result at the end of the page

![Result Direct Package Upload](https://s3.amazonaws.com/images.openpay/result-direct-package-file-upload.png)


- Option B

1. Copy the folders **app, lib**, **skin** to the Magento root installation. Make sure to keep the Magento folders structure intact.
2. In your admin go to **System > Cache Management** and clear all caches.
3. Go to **System > IndexManagement** and select all fields. Then click in Reindex Data.



# Configuration

1. Go to **System > Configuration > Sales > Payment Methods**. Select Openpay.

2. Set your PUBLIC_KEY, MERCHANT_ID, PRIVATE_KEY (Get this values from the dashboard) on "Openpay Common Resources" section.
![](https://s3.amazonaws.com/openpay-plugins-screenshots/magento/magento_conf_gral.png)

3. Enable the payments methods that you want to have

![](https://s3.amazonaws.com/openpay-plugins-screenshots/magento/magento_conf_cards.png)

![](https://s3.amazonaws.com/openpay-plugins-screenshots/magento/magento_conf_offline.png)


4. Save your configuration

# Configure state as required
Go to **System > General > States Options** and select all allowed countries
