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

require_once(_PS_MODULE_DIR_ . 'pagofacil17' . DIRECTORY_SEPARATOR .'vendor/autoload.php');
use PagoFacil\lib\Request;
use PagoFacil\lib\Transaction;

class Pagofacil17RedirectModuleFrontController extends ModuleFrontController
{
    /**
     * Do whatever you have to before redirecting the customer on the website of your payment processor.
     */
    public function postProcess()
    {
        error_log('postProcess redirect');
        $cart = $this->context->cart;
        if ($cart->id_customer == 0
        || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'pagofacil17') {
                $authorized = true;
                break;
            }
        }

        //if no customer, return to step 1 (just in case)
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        //get data
        $extra_vars = array();
        $currency = new Currency($cart->id_currency);
        $cart_amount = Context::getContext()->cart->getOrderTotal(true);
        $customer_email = Context::getContext()->customer->email;
        $token_service = Configuration::get('TOKEN_SERVICE');
        $token_secret = Configuration::get('TOKEN_SECRET');

        //setting order as pending payment
        $this->module->validateOrder(
            $cart->id,
            Configuration::get('PS_OS_PAGOFACIL_PENDING_PAYMENT'),
            $cart_amount,
            $this->module->displayName,
            null,
            $extra_vars,
            (int)$currency->id,
            false,
            $customer->secure_key
        );

        //getting order_id
        $order_id = Order::getOrderByCartId((int)($cart->id));

        $return_url = $this->context->link->getModuleLink('pagofacil17', 'confirmation') . '?id_cart=' .
          $cart->id . '&id_module=' . $this->module->id . '&id_order=' .
          $order_id . '&key=' . $customer->secure_key;
        $request = new Request();
        $request->account_id = $token_service;
        $request->amount = round($cart_amount);
        $request->currency = $currency->iso_code;
        $request->reference = $order_id;
        $request->customer_email =  $customer_email;
        $request->url_complete = $return_url;
        $request->url_cancel = $this->context->link->getModuleLink('pagofacil17', 'cancel');
        $request->url_callback =  $this->context->link->getModuleLink('pagofacil17', 'callback');
        $request->shop_country =  Context::getContext()->language->iso_code;
        $request->session_id = date('Ymdhis').rand(0, 9).rand(0, 9).rand(0, 9);

        $transaction = new Transaction($request);
        $environment = Configuration::get('ENVIRONMENT');
        $transaction->environment = $environment;
        $transaction->setToken($token_secret);
        $transaction->initTransaction($request);

        return $this->setTemplate('module:pagofacil17/views/templates/front/redirect.tpl');
    }

    protected function displayError($message, $description = false)
    {
        /**
         * Create the breadcrumb for your ModuleFrontController.
         */
        $this->context->smarty->assign('path', '
			<a href="'.$this->context->link->getPageLink('order', null, null, 'step=3').'">'.$this->module->l('Payment').'</a>
			<span class="navigation-pipe">&gt;</span>'.$this->module->l('Error'));

        /**
         * Set error message and description for the template.
         */
        array_push($this->errors, $this->module->l($message), $description);

        return $this->setTemplate('error.tpl');
    }
}
