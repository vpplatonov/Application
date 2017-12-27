<?php

namespace Application\ICAO;

/**
 * icao_coder
 */
abstract class IcaoCoder
{
  protected $icao_code;

  public function __construct(array $icao_code) {
    $this->icao_code = $icao_code;
  }

  abstract function icaoEncode();

}

/**
 * icao_coder_itemq
 */
class IcaoCoderItemq extends IcaoCoder
{
  const SOAPSERVICE_ROCKET_USER = 'platonov@stem.net.ua';
  const SOAPSERVICE_ROCKET_PASS_CONSOLE = '';

  public function __construct(array $icao_code) {
    if (empty($icao_code)) {
      throw new Exception('empty ICAO code');
    }
    parent::__construct($icao_code);
  }

  function icaoEncode() {
    $requst = '<?xml version="1.0" encoding="UTF-8" ?>';
    $requst .= '<REQNOTAM>';
    $requst .= '<USR>' . self::SOAPSERVICE_ROCKET_USER .'</USR>';
    $requst .= '<PASSWD>' . self::SOAPSERVICE_ROCKET_PASS_CONSOLE . '</PASSWD>';

    foreach($this->icao_code as $code) {
      $requst .= '<ICAO>' . $code . '</ICAO>';
    }

    $requst .= '</REQNOTAM>';

    return $requst;
  }
}
