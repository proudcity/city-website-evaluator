<?php 

ini_set('max_execution_time', 30000);

require 'vendor/autoload.php';

use Sunra\PhpSimple\HtmlDomParser;

function populateDomLinks(&$cities, $dom, $name = 'City') {
  $ret = $dom->find('a[name=' . $name . ']', 0);
  if($ret) {
    $list = $ret->next_sibling()->next_sibling();
    foreach ($list->children() as $key => $value) {
      foreach ($value->children() as $link) {
        $cities[$link->innertext] = $link->attr['href'];
      }
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

function populateUrlFromWikipedia($dom) {
  //sitebar
  $ret = $dom->find('div[id="mw-content-text"] table[class="geography"] tbody', 0);
  if($ret) {
    foreach ($ret->children() as $key => $value) {
      $th = $value->children(0);
      if($th->innertext == "Website") {
        $td = $th->next_sibling();
        if(!empty($td->children)) {
          $child = $td->children(0);
          $going = true;
          while($going) {
            if($child->tag == 'a' && !empty($child->attr['href'])) {
              return $child->attr['href'];
            }
            try {
              $child = $child->children(0);
            }
            catch(Exception $e) {
              $going = false;
              return false;
            }
          }
        }
        else {
          $url = $td->innertext;
          if(filter_var($url, FILTER_VALIDATE_URL)) {
            return $td->innertext;
          }
          $url = 'http://' . $url;
          if(filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
          }
        }
      }
    }
  }
  return false;
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
         && !file_exists('state_website_pages/'.$data[1].'.json')) {
          //k('writing: '.$data[1]);
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
              try {
                $response = $guzzle->get('http://www.ca.gov/About/Government/Local/Cities/'.$value.'.html');
              }
              catch(GuzzleHttp\Exception\ClientException $e) {
                $response = false;
              }
              if($response) {
                $body = $response->getBody();
                $dom = HtmlDomParser::str_get_html((string) $body);
                populateCADomLinks($cities, $dom);
              }
            }
          }
          // Special case CO
          else if($data[1] == 'CO') {
            try {
              $response = $guzzle->get('https://dola.colorado.gov/lgis/municipalities.jsf');
            }
            catch(GuzzleHttp\Exception\ClientException $e) {
              $response = false;
            }
            if($response) {
              $body = $response->getBody();
              $dom = HtmlDomParser::str_get_html((string) $body);
              populateCODomLinks($cities, $dom);
            }
          }
          // Default
          else {
            try {
              $response = $guzzle->get('http://www.statelocalgov.net/state-'.$data[1].'.cfm');
            }
            catch(GuzzleHttp\Exception\ClientException $e) {
              $response = false;
            }
            if($response) {
              $body = $response->getBody();
              $dom = HtmlDomParser::str_get_html((string) $body);
              if($dom) {
                populateDomLinks($cities, $dom);
                populateDomLinks($cities, $dom, 'Town');
                populateDomLinks($cities, $dom, 'Village');
              }
            }
          }
          if(!empty($cities)) {
            $fp = fopen('state_website_pages/'.$data[1].'.json', 'w');
            fwrite($fp, json_encode($cities));
            fclose($fp);
          }
        }
        else {
          //k('opening: '.$data[1]);
          $fp = file_get_contents('state_website_pages/'.$data[1].'.json');
          $cities = json_decode($fp, true);
        }
        if(!empty($cities[$data[0]])) {
          $data[3] = str_replace('"', '', $cities[$data[0]]);
        }
      }
      // We still don't have a website entry, try wikipedia
      if(empty($data[3]) || (!empty($data[6]) && empty($data[7]))) {
        $response;
        try {
          $response = $guzzle->get('https://en.wikipedia.org/wiki/'
                    . str_replace(' ', '_', $data[0]) 
                    . ',_' . $data[1]);
        }
        catch(GuzzleHttp\Exception\ClientException $e) {
          $response = false;
        }
        if(!empty($response)){
          $body = $response->getBody();
          if($body) {
            $dom = HtmlDomParser::str_get_html((string) $body);
            $url = populateUrlFromWikipedia($dom);
            if($url) {
              $data[3] =  $url;
            }
          }
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