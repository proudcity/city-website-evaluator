<?php

ini_set('max_execution_time', 30000);

require 'vendor/autoload.php';

use GuzzleHttp\Ring\Core;
use GuzzleHttp\Ring\Exception\ConnectException;
use GuzzleHttp\Ring\Exception\RingException;
use GuzzleHttp\Ring\Future\CompletedFutureArray;

$file = file_get_contents('.env.json');
$env = json_decode($file, true);

function pageSpeedRequest($url) {

  global $env;
  $guzzle = new GuzzleHttp\Client();

  return $guzzle->get('https://www.googleapis.com/pagespeedonline/v2/runPagespeed', [
    'query' => [
      'url' => $url,
      'strategy' => 'mobile',
      'key' => $env['API_KEY']
    ]
  ]);
}

function getStats() {
  $row = 1;
  $headers = [];
  if (($handle = fopen("result-websites.csv", "r")) !== FALSE) {
    $resultFile = fopen('result-stats.csv','w');
    while (($data = fgetcsv($handle, 1000, "#")) !== FALSE) {
      $num = count($data);
      // Header row
      if($row === 1) {
        $headers = $data;
        fputcsv($resultFile, $data, "#");
        $row++;
        continue;
      }
      for ($i=0; $i < 14; $i++) { 
        $data[$i] = !empty($data[$i]) ? $data[$i] : '';
      }
      // Site inspector
      if(!empty($data[3]) && empty($data[6])) {

        // Run site inspector
        $json = shell_exec('site-inspector inspect ' . $data[3] . ' --json');
        if(!empty($json)) {
          $inspect = json_decode($json);
          // https
          $data[6] = !empty($inspect->https) 
                         ? 'Yes' 
                         : 'No';
          // No framework data
          $data[8] = !empty($inspect->canonical_endpoint->sniffer->framework)
                  ? $inspect->canonical_endpoint->sniffer->framework
                  : 'Unknown';
          // Ipv6 support
          $data[10] = !empty($inspect->canonical_endpoint->dns->ipv6)
                  ? 'Yes'
                  : 'No';
        }
      }
      // Try to grab Wappalyzer info
      if(!empty($data[3]) && (empty($data[8]) || ($data[8] == 'Unknown' || $data[8] == 'wordpress' || $data[8] == 'drupal'))) {
        $json = shell_exec('~/workspace/phantomjs/bin/phantomjs ./Wappalyzer/src/drivers/phantomjs/driver.js ' . $data[3]);
        if(!empty($json)) {
          $json = json_decode($json);
          if(!empty($json->applications)) {
            foreach($json->applications as $app) {
              if(in_array('cms', $app->categories) && $app->confidence >= 50) {
                // Build drupal version
                if($app->name == 'Drupal') {
                  $data[8] = $app->name . ' '
                           . (!empty($app->version) ? $app->version : '6 or other');
                }
                // Wordpress versions
                else if($app->name == 'WordPress') {
                  $data[8] = $app->name . ' '
                          . (!empty($app->version) ? substr($app->version, 1, 2) . 'x' : 'other');
                }
                // Just print
                else {
                  $data[8] = strtolower($app->name);
                }
              }
            }
          }
        }
      }
      // Google page speed
      if(!empty($data[3]) && empty($data[7])) {
        // Check mobile ready
        $mobile = FALSE;
        try {
          $response = pageSpeedRequest($data[3]);
        }
        catch(GuzzleHttp\Exception\ClientException $e) {
          $mobile = 'FAILED';
        }
        catch(GuzzleHttp\Exception\ServerException $e) {
          $mobile = 'FAILED';
        }
        catch(Exception $e) {
          $mobile = 'FAILED';
        }
        if(!$mobile) {
          $json = $response->json();
          $mobile = 'Unknown';
          if(isset($json['formattedResults']['ruleResults']['SizeContentToViewport']['ruleImpact'])) {
            // @TODO Evaluate "break points" for these results
            $mobile = $json['formattedResults']['ruleResults']['SizeContentToViewport']['ruleImpact'] > 3
                   || $json['formattedResults']['ruleResults']['ConfigureViewport']['ruleImpact'] > 3
                      ? 'No'
                      : 'Yes';
          }
          $data[7] = $mobile;
        }
      }
      fputcsv($resultFile, $data, "#");
      $row++;
    }
    fclose($handle);
    fclose($resultFile);
  }
}

getStats();