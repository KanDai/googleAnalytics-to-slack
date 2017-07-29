<?php

// Load Config
require_once './config.php';

// Load the Google API PHP Client Library.
require_once './vendor/autoload.php';

// Get Analytics Profile
$analytics = initializeAnalytics($KEY_FILE, $APP_NAME);
$profile   = getFirstProfileId($analytics);

$results   = getResults($analytics, $profile, $entryData);
outputResults($results, $path);


function initializeAnalytics($KEY_FILE, $APP_NAME) {
  // Creates and returns the Analytics Reporting service object.

  // Use the developers console and download your service account
  // credentials in JSON format. Place them in this directory or
  // change the key file location if necessary.
  $KEY_FILE_LOCATION = $KEY_FILE;

  // Create and configure a new client object.
  $client = new Google_Client();
  $client->setApplicationName($APP_NAME);
  $client->setAuthConfig($KEY_FILE_LOCATION);
  $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
  $analytics = new Google_Service_Analytics($client);

  return $analytics;
}

function getFirstProfileId($analytics) {
  // Get the user's first view (profile) ID.

  // Get the list of accounts for the authorized user.
  $accounts = $analytics->management_accounts->listManagementAccounts();

  if (count($accounts->getItems()) > 0) {
    $items = $accounts->getItems();
    $firstAccountId = $items[0]->getId();

    // Get the list of properties for the authorized user.
    $properties = $analytics->management_webproperties
        ->listManagementWebproperties($firstAccountId);

    if (count($properties->getItems()) > 0) {
      $items = $properties->getItems();
      $firstPropertyId = $items[0]->getId();

      // Get the list of views (profiles) for the authorized user.
      $profiles = $analytics->management_profiles
          ->listManagementProfiles($firstAccountId, $firstPropertyId);

      if (count($profiles->getItems()) > 0) {
        $items = $profiles->getItems();

        // Return the first view (profile) ID.
        return $items[0]->getId();

      } else {
        throw new Exception('No views (profiles) found for this user.');
      }
    } else {
      throw new Exception('No properties found for this user.');
    }
  } else {
    throw new Exception('No accounts found for this user.');
  }
}


function getResults($analytics, $profileId, $entryData) {

  // ランキング生成設定
  $start  = date('Y-m-d', strtotime('-1 week')); // 取得開始する日付
  $end    = date('Y-m-d', strtotime('-1 day'));  // 取得終了する日付
  $length = '10';                                // 取得件数

  // APIからデータ取得
  $results = $analytics->data_ga->get(
      'ga:' . $profileId,
      $start,
      $end,
      'ga:pageviews',
      array(
          'dimensions'  => 'ga:pageTitle,ga:pagePath',
          'sort'        => '-ga:pageviews',
          'max-results' => $length,
      )
  );

  return $results->rows;
}

function outputResults($results) {

  var_dump( $results );
}

