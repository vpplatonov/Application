<?php
/**
 * @file
 * Soap Service test NOTAM rocket.
 */

use Application\ICAO\IcaoDecoderItemq;

/**
 * Prepare marker array for Google MAP.
 *
 * @param $notam_result
 *
 * @return array
 */
function rocket_map_marker_prepare($notam_result) {
  $markers = array();
  foreach ($notam_result as $id => $items) {
    $markers[$id] = array(
      'nid' => $id,
      'title' => str_replace("\n", "<br>", $items['ITEME']),
      'location' => implode(',', IcaoDecoderItemq::notam_deg_dec_convert($items['ITEMQ'])),
    );
  }
  return $markers;
}
