<?php

namespace Application\ICAO;

use Application\ICAO\IcaoCoderItemq;
use Application\ICAO\IcaoDecoderItemq;

abstract class IcaoManager
{
  abstract function getICAOcoder(array $icao_code);
  abstract function getICAOdecoder($icao_xml_notam);
  abstract function makeICAOrequest($request);
}

class IcaoManagerSoapXml extends IcaoManager
{
  const SOAPSERVICE_ROCKET_URL = '';

  public function getICAOcoder(array $icao_code) {
    return new IcaoCoderItemq($icao_code);
  }

  public function getICAOdecoder($icao_xml_notam) {
    return new IcaoDecoderItemq($icao_xml_notam);
  }

  public function makeICAOrequest($request) {
    $client = new \SoapClient(self::SOAPSERVICE_ROCKET_URL);
    // Call wsdl function.
    return $client->getNotam($request);
  }
}
