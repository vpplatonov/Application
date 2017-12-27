<?php

namespace Application\ICAO;

abstract class IcaoDecoder
{
  protected $icao_notam;

  public function __construct($icao_notam) {
    $this->icao_notam = $icao_notam;
  }

  abstract function icaoDecode();

}


class IcaoDecoderItemq extends IcaoDecoder
{
  const VALUE = 'value';
  const NOTAM = 'NOTAM';
  const RESULT = 'RESULT';
  const MESSAGE = 'MESSAGE';
  const LEVEL = 'level';
  const CLOSE = 'close';
  const TAG = 'tag';
  const ATTRIBUTES = 'attributes';
  const ID = 'ID';
  const ERR_MESSAGE = 'Error parsing NOTAM XML response';

  public function __construct($icao_xml_notam)
  {
    parent::__construct($icao_xml_notam);
  }

  public function icaoDecode()
  {
    $p = xml_parser_create();
    xml_parse_into_struct($p, $this->icao_notam, $vals, $index);
    xml_parser_free($p);
    $notam_result = [];
    $level = 0;
    foreach ($vals as $tag => $value) {
      if ($value[IcaoDecoderItemq::LEVEL] == 1 || $value[IcaoDecoderItemq::TAG] == IcaoDecoderItemq::CLOSE) {
        continue;
      }
      if ($value[IcaoDecoderItemq::LEVEL] == 2 && $value[IcaoDecoderItemq::TAG] == IcaoDecoderItemq::RESULT) {
        if ($value[IcaoDecoderItemq::VALUE] != 0) {
          $err_mess_key = array_search(IcaoDecoderItemq::MESSAGE, array_column($vals, IcaoDecoderItemq::TAG));
          if (!empty($err_mess_key)) {
            $err_mess = $vals[$err_mess_key][IcaoDecoderItemq::VALUE];
          }
          else {
            $err_mess = IcaoDecoderItemq::ERR_MESSAGE;
          }
          throw new Exception($err_mess);
          break;
        }
      }
      if ($value[IcaoDecoderItemq::LEVEL] == 3 && $value[IcaoDecoderItemq::LEVEL] != $level
        && $value[IcaoDecoderItemq::TAG] == IcaoDecoderItemq::NOTAM && !empty($value[IcaoDecoderItemq::ATTRIBUTES])) {
        $id = $value[IcaoDecoderItemq::ATTRIBUTES][IcaoDecoderItemq::ID];
        $notam_result[$id] = [];
      }

      if ($value[IcaoDecoderItemq::LEVEL] == 4 && !empty($value[IcaoDecoderItemq::VALUE])) {
        $notam_result[$id][$value[IcaoDecoderItemq::TAG]] = $value[IcaoDecoderItemq::VALUE];
      }
      $level = $value[IcaoDecoderItemq::LEVEL];
    }

    return $notam_result;
  }


  /**
   * Helper to Convert NOTAM ItemQ to Lng Lat.
   * 'EGTT/QMXLT/IV/M /A /000/999/5050N00018W'
   * N/E => '+', S/W => '-'
   */
  static function notam_deg_dec_convert($notam = 'EGTT/QMXLT/IV/M /A /000/999/5050N00018W')
  {
    $lng_lat_array = array();
    if (!empty($notam)) {
      $pattern = "/.*\/(\d+)(N|E)(\d+)(W|S)(\d*)$/";
      preg_match($pattern, $notam, $matches);
      if (count($matches) >= 5) {
        $lng_lat_array = array(
          'lat' => ($matches[2] == 'N' ? '' : '-') . self::deg_dec_convert($matches[1]),
          'lng' => ($matches[4] == 'E' ? '' : '-') . self::deg_dec_convert($matches[3]),
          'r' => !empty($matches[5]) ? $matches[5] : 0,
        );
      }
    }

    if (empty($lng_lat_array)) {
      // @TODO : for debug - remove if it is not necessary.
      $lng_lat_array = array(
        // NL: 52° 23' N and 4° 55' E as
        // Latitude 52.389477 deg Longitude: 4.917577 deg.
        // European territory of the Netherlands is situated between
        // latitudes 51° and 54° N, and longitudes 3° and 8° E.
        'lat' => round(52 + mt_rand() / mt_getrandmax() * (53 - 52), 6),
        'lng' => round(5 + mt_rand() / mt_getrandmax() * (7 - 5), 6),
        'r' => 0,
      );
    }

    return $lng_lat_array;
  }


  /**
   * Decimal Degrees = Degrees + minutes/60 + seconds/3600.
   *
   * @param string $degree
   *
   * @return float
   */
  static function deg_dec_convert($degree = '00018')
  {
    $degree .= str_repeat('0', 6 - strlen($degree));
    $deg = substr($degree, 0, 2);
    $min = substr($degree, 2, 2);
    $sec = substr($degree, 4);

    return round($deg + $min / 60 + $sec / 3600, 6);
  }
}
