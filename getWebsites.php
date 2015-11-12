<?php 

require 'vendor/autoload.php';

use Sunra\PhpSimple\HtmlDomParser;

function populateDomLinks(&$cities, $dom, $name = 'City') {
  $ret = $dom->find('a[name=' . $name . ']', 0);
  $list = $ret->next_sibling()->next_sibling();
  foreach ($list->children() as $key => $value) {
    foreach ($value->children() as $link) {
      $cities[$link->innertext] = $link->attr['href'];
    }
  }
}

function populateCADomLinks(&$cities, $dom) {
  $ret = $dom->find('table[id=local]', 0);
  foreach ($ret->children() as $key => $value) {
    if($value->tag == 'tr' && $value->children(0)->tag == 'td') {
      $link = $value->children(0)->children(0);
      if(!empty($link->attr['href'])) {
        $cities[$link->innertext] = $link->attr['href'];
      }
    }
  }
}

function populateCODomLinks(&$cities, $dom) {
  $ret = $dom->find('div[id=tabContent] table tbody', 0);
  foreach ($ret->children() as $key => $value) {
    if($value->tag == 'tr' && $value->attr['class'] == "dataHighlighted" && $value->children(0)) {
      $link = $value->children(0)->children(0);
      if(!empty($link->attr['href'])) {
        $comma = strpos($link->innertext, ',');
        $cities[substr($link->innertext, 0, $comma)] = $link->attr['href'];
      }
    }
  }
}

function getWebsites() {
  $guzzle = new GuzzleHttp\Client();
  $row = 1;
  $headers = [];
  if (($handle = fopen("city-list.csv", "r")) !== FALSE) {
    $resultFile = fopen('result-websites.csv','w');
    // Run through csv of cities
    while (($data = fgetcsv($handle, 1000, "#")) !== FALSE) {
      $num = count($data);
      // Header row
      if($row === 1) {
        $headers = $data;
        fputcsv($resultFile, $data, "#");
        $row++;
        continue;
      }
      // We don't have a website entry
      else if(empty($data[3])) {
        $cities = [];
        // WI, WV, UT + others need other cases.  If json file exists, just use that
        if( $data[1] != 'WI'
         && $data[1] != 'WV'
         && $data[1] != 'UT'
         && !file_exists('state_pages/'.$data[1].'.json')) {
          k('writing: '.$data[1]);
          // Special case CA
          if($data[1] == 'CA') {
            $pages = [
              'A',
              'B',
              'C',
              'D_F',
              'G_K',
              'L',
              'M_O',
              'P_R',
              'S',
              'T_Z'
            ];
            foreach ($pages as $value) {
              $response = $guzzle->get('http://www.ca.gov/About/Government/Local/Cities/'.$value.'.html');
              $body = $response->getBody();
              $dom = HtmlDomParser::str_get_html((string) $body);
              populateCADomLinks($cities, $dom);
            }
          }
          // Special case CO
          else if($data[1] == 'CO') {
            $response = $guzzle->get('https://dola.colorado.gov/lgis/municipalities.jsf');
            $body = $response->getBody();
            $dom = HtmlDomParser::str_get_html((string) $body);
            populateCODomLinks($cities, $dom);
          }
          // Default
          else {
            $response = $guzzle->get('http://www.statelocalgov.net/state-'.$data[1].'.cfm');
            $body = $response->getBody();
            $dom = HtmlDomParser::str_get_html((string) $body);
            if($dom) {
              populateDomLinks($cities, $dom);
              populateDomLinks($cities, $dom, 'Town');
              populateDomLinks($cities, $dom, 'Village');
            }
          }
          if(!empty($cities)) {
            $fp = fopen('state_pages/'.$data[1].'.json', 'w');
            fwrite($fp, json_encode($cities));
            fclose($fp);
          }
        }
        else {
          k('opening: '.$data[1]);
          $fp = file_get_contents('state_pages/'.$data[1].'.json');
          $cities = json_decode($fp, true);
        }
        if(!empty($cities[$data[0]])) {
          $data[3] = str_replace('"', '', $cities[$data[0]]);
          k($data);
        }
      }
      // Write line
      fputcsv($resultFile, $data, "#");
      $row++;
    }
    fclose($handle);
    fclose($resultFile);
  }
}

getWebsites();