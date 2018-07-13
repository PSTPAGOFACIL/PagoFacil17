<?php
namespace PagoFacil\lib;
use  PagoFacil\lib\Operacion;

class Request extends Operacion{
  // Variables request
  public $customer_email; //String
  public $url_complete; //String
  public $url_cancel; //String
  public $url_callback; //String
  public $shop_country; //String
  public $session_id; //String
}
