# Integrating The Module

To integrate the DNS Gateway Module on your cpanel or server please follow the instructions below.

##### Pre-requisites

- Access to WHMCS admin area.
- Gateway account with API access. (Sign up on https://portal.dns.business, read and accept all terms and condition and then request live or OT&E server credentials.) 

NOTE: DNS Gateway has a production as well as a test server environment. The test server environment is called O&TE. We urge you to test the WHMCS Registrar module in our O&TE environment, before pointing it to production. 

## Integrating the Module on a CPANEL

##### Step 1


Note: Module names should be a single word, consisting of only lowercase letters and numbers. The name must start with a letter and must be unique.

- Download the Module (Gateway-WHMCS-master.zip) archive

- Extract Gateway-WHMCS-master.zip and rename the Gateway-WHMCS-master to dns_gateway and zip it again.


##### Step 2

Go to
```
 File Manager -> public_html -> modules -> registrars
```

##### Step 4

Upload the dns_gateway.zip on the registrars directory

##### Step 5 

Reload the page to view the changes




## Integrating the Module on a Server

##### Step 1 

Go to your registrars directory
```
$cd /var/www/html/modules/registrars/
```
##### Step 2

Clone the module
```
sudo git clone https://github.com/DomainNameServices/Gateway-WHMCS.git
```
##### Step 3

Change the module name
```
sudo mv Gateway-WHMCS/ dns_gateway
```
##### Step 4

Restart apache 
```
sudo systemctl restart apache2.service
```

## Configuration

To configure WHMCS for use with DNS Gateway, please follow the steps below.

1. Login to your **WHMCS admin** panel.
2. Click on **Setup** menu, select **Products/Services** and click on **Domain Registrars**.
3. Click on **Activate** next to DNS Gateway in the list:
![Activate Plugin](https://github.com/calebtech/pictures/blob/master/Screenshot%20from%202019-05-16%2009-09-24.png)

4. Enter your DNS Gateway API credentials, If you wish to test the module before you go live, you can use your DNS Gateway O&TE API credentials to corresponding text boxes (O&TE API) and check the "Enable OTE Testing Mode".
![Activate Plugin](https://github.com/calebtech/pictures/blob/master/Screenshot%20from%202019-05-16%2009-36-04.png)

5. Optional Settings
 - If you encounter any issues with the module it is recommended that you enable **DebugMode** and check the logs under Utilities > Logs > Module Log. If this option is disabled the module will only be logging errors returned by the module.
 
6. Click save changes

The DNS Gateway plug-in is now ready for use and will function just like any other built-in WHMCS registrar module. You can now change DNS Gateway to be the automatic registrar and configure TLDs and services for all your customers. 

To perform these actions, click on the Setup menu, select Products/Services and click on Domain Pricing in your WHMCS admin panel:

For more information please refer to http://docs.whmcs.com/Domains_Configuration.

