<?php
namespace PagoFacil\lib;
use  PagoFacil\lib\Operacion;

class Response extends Operacion{
  // Variables response
  public $gateway_reference; //Number
  public $result; //String
  public $timestamp; //String
  public $test; //Bool
}
