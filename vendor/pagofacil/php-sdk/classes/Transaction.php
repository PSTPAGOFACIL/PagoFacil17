<?php
namespace PagoFacil\lib;

/*
* Clase que contiene las funciones para generar un anueva transaccion
*/
class Transaction{

  public $environment; // String

  /* Variables privadas */
  private $token_secret; //String
  private $request; //Request
  private $status = array(
    'COMPLETADA',
    'FALLIDA',
    'ANULADA',
    'PENDIENTE'
  );
  private $urls = array(
    'DESARROLLO' => 'https://gw-dev.pagofacil.cl/initTransaction',
    'BETA'       => 'https://gw-beta.pagofacil.cl/initTransaction',
    'PRODUCCION' => 'https://gw.pagofacil.cl/initTransaction'
  );

  public function setToken($token_secret){
    $this->token_secret = $token_secret;
  }

  function __construct($request = null) {
        $this->request = $request;
  }
  /**
  * Iniciar una transaccion
  * @param $request contiene los datos principales de la peticion
  */
  public function initTransaction($request){
      $data = array();
      foreach ($request as $key => $value) {
        $data['x_'.$key] = $value;
      }

      // Generar Firma
      $this->generarFirma($data);
      $response = $this->_initTransaction($data);
  }

  /**
  * Genera firma de transaccion
  * @param $data contiene arreglo con los datos a enviar
  */
  public function generarFirma(&$data){
    /*Se elimina firma anterior*/
    unset($data['x_signature']);

    /* Ordenar alfabeticamente la data */
    ksort($data);

    /* Crear mensaje a firmar */
    $message = '';
    foreach ($data as $key => $value) {
        $message .= $key . $value;
    }

    /* Firmar mensaje*/
    $data['x_signature'] = hash_hmac('sha256', $message, $this->token_secret);
  }

  /**
  * Valida firma y monto de response
  * @param $data contiene los datos con los cuales se genera la firma
  */
  public function validate($data){
    /* Si no tiene firma se devuleve como error*/
    if(empty($data['x_signature'])){
      return false;
    }

    $signature = $data['x_signature'];

    /*Se genera la firma*/
    $this->generarFirma($data);

    return  $data['x_signature'] == $signature;
  }

  /**
  * Realiza el llamado a initTransaction
  * @param $request contiene los datos a enviar en la peticion
  */
  function _initTransaction($request){

    // Dispara formulario POST
    $html = '';

    $html .= '<html>';
    $html .= '  <head>  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script></head>';
    $html .= '  <body>';
    $html .= '    <form name="requestForm" id="requestForm" action='.$this->urls[$this->environment].' method="POST">';
    foreach ($request as $key => $value) {
      $html .= '    <input type="hidden" name="' . $key . '" value="' . $value . '" />';
    }
    $html .= '    </form>';
    $html .= '    <script type="text/javascript">';
    $html .= '      $(document).ready(function () {';
    $html .= '        $("#requestForm").submit(); ';
    $html .= '      });';
    $html .= '    </script>';
    $html .= '  </body>';
    $html .= '</html>';

    echo $html;
  }

  /**
  * Funcion que recibe la respuesta de la peticion
  */
  public function response(){
    if($this->validate($request, $response)){
      return $response;
    } else{
      $error = array(
        'Error'  => 'Transacción ' . $this->status[1],
        'Detail' => 'Error de validación de firma'
      );
      return $error;
    }
  }
}
