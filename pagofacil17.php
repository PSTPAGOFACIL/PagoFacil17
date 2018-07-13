<?php
/**
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Pagofacil17 extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'pagofacil17';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Pago Fácil SPA';
        $this->need_instance = 1;
        $this->module_key = 'ffdce70775ba802ba870a3312e6941a5';

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Pago Facil');
        $this->description = $this->l('Sell ​​with several means of payment instantaneously with Pago Fácil.');

        $this->confirmUninstall = $this->l('When uninstalling, you will not be able to receive payments.Are you sure?');

        $this->limited_countries = array('CL');

        $this->limited_currencies = array('CLP');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        if (!isset($this->token_secret) || !isset($this->token_service)) {
            $this->warning = $this->l('Token Service and Token Secret must be configured to continue.');
        }
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false) {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        Configuration::updateValue('PAGOFACIL17_LIVE_MODE', false);
        /*
        * We generate the new order state
        */
        if (!$this->installOrderState()) {
            return false;
        }

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('payment') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('paymentOptions');
    }

    public function uninstall()
    {
        Configuration::deleteByName('PAGOFACIL17_LIVE_MODE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitPagofacil17Module')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPagofacil17Module';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
          'form' => array(
            'legend' => array(
              'title' => $this->l('Settings'),
            ),
            'input' => array(

              array(
                'type' => 'text',
                'label' => $this->l('Token Service'),
                'name' => 'TOKEN_SERVICE',
                'size' => 80,
                'required' => true
              ),
              array(
                'type' => 'text',
                'label' => $this->l('Token Secret'),
                'name' => 'TOKEN_SECRET',
                'size' => 80,
                'required' => true
              ),
              array(
                'type' => 'radio',
                'label' => $this->l('Environment:'),
                'name' => 'ENVIRONMENT',
                'desc' => $this->l('Select Environment'),
                'required' => true,
                'class' => 't',
                'is_bool' => true,
                'values' => array(
                  array(
                    'id' => 'active_on',
                    'value' => 'DESARROLLO',
                    'label' => $this->l('Development')
                  ),
                  array(
                    'id' => 'active_ff',
                    'value' => 'BETA',
                    'label' => $this->l('Beta')
                    ),
                    array(
                    'id' => 'active_off',
                    'value' => 'PRODUCCION',
                    'label' => $this->l('Production')
                    )
                    ),
                    ),
                    array(
                    'label' => '',
                    'desc' => $this->l('Pago Fácil - https://www.pagofacil.cl'),
                    )
                    ),
                    'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                    )
          )
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
          'TOKEN_SERVICE' => Configuration::get('TOKEN_SERVICE'),
          'TOKEN_SECRET' => Configuration::get('TOKEN_SECRET'),
          'ENVIRONMENT' => Configuration::get('ENVIRONMENT'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false) {
            return;
        }

        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
            $this->smarty->assign('status', 'ok');
        }

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
        ));

        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $currency = new Currency($params['cart']->id_currency);
        $currency->iso_code;

        if (in_array($currency->iso_code, $this->limited_currencies) == false) {
            return false;
        }

        $payment_options = array($this->showAllPaymentPlatforms());

        return $payment_options;
    }

    public function showAllPaymentPlatforms()
    {
        $module = $this->context->smarty->fetch('module:pagofacil17/views/templates/front/payment_infos.tpl');
        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay with Pago Fácil'))
          ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true))
          ->setAdditionalInformation($module);

        return $externalOption;
    }
    /*
    * Function for generate the order state
    */
    public function installOrderState()
    {
        if (Configuration::get('PS_OS_PAGOFACIL_PENDING_PAYMENT') < 1) {
            $order_state = new OrderState();
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->invoice = false;
            $order_state->color = '#98c3ff';
            $order_state->logable = true;
            $order_state->shipped = false;
            $order_state->unremovable = false;
            $order_state->delivery = false;
            $order_state->hidden = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            $lang = (int)Configuration::get('PS_LANG_DEFAULT') ;
            $order_state->name = array($lang => pSQL($this->l('Pago Facil - Pending payment')));
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue('PS_OS_PAGOFACIL_PENDING_PAYMENT', $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }
}
