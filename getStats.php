<?php

require 'vendor/autoload.php';

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
      // Site inspector
      if(!empty($data[3]) && empty($data[6])) {

        // Run site inspector
        $json = shell_exec('site-inspector inspect ' . $data[3] . ' --json');
        if(!empty($json)) {
          $inspect = json_decode($json);
          // https
          $data[4] = !empty($data[4]) ? $data[4] : '';
          $data[5] = !empty($data[5]) ? $data[5] : '';
          $data[6] = !empty($inspect->https) 
                         ? 'Yes' 
                         : 'No';
          $data[7] = !empty($data[7]) ? $data[7] : '';
          $data[8] = !empty($inspect->canonical_endpoint->sniffer->framework)
                  ? $inspect->canonical_endpoint->sniffer->framework
                  : 'Unknown';
          $data[9] = !empty($data[9]) ? $data[9] : '';
          $data[10] = !empty($inspect->canonical_endpoint->dns->ipv6)
                  ? 'Yes'
                  : 'No';
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
        if(!$mobile) {
          $json = $response->json();
          $mobile = 'Unknown';
          if(isset($json['formattedResults']['ruleResults']['SizeContentToViewport']['ruleImpact'])) {
            // @TODO This 1 value is arbitrary... look into what it means
            $mobile = $json['formattedResults']['ruleResults']['SizeContentToViewport']['ruleImpact'] > 1
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