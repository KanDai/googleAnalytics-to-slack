<?php

// ----------------------------------------------------
// Require Setting / Library
// ----------------------------------------------------

// Load Config
require_once './config.php';

// Load the Google API PHP Client Library.
require_once './vendor/autoload.php';


// ----------------------------------------------------
// Execute Functions
// ----------------------------------------------------

// Get Analytics Profile
$analytics = initializeAnalytics();
$profile   = getFirstProfileId($analytics);

// 曜日を取得
$date_week = date("w");
$date_day  = date("d");

// 日毎レポート配信
if ( D_REPORT == true ) {
  $result = getReport($analytics, $profile, 'daily');
  postToSlack($result);
}

// 日毎ランキング配信
if ( D_RANKING == true ) {
  $result = getRanking($analytics, $profile, 'daily');
  postToSlack($result);
}

// 週間レポート配信
if ( W_REPORT == true && $date_week == W_REPORT_TIME ) {
  $result = getReport($analytics, $profile, 'weekly');
  postToSlack($result);
}

// 週間レポート配信
if ( W_RANKING == true && $date_week == W_RANKING_TIME ) {
  $result = getRanking($analytics, $profile, 'weekly');
  postToSlack($result);
}

// 月間レポート配信
if ( M_REPORT == true && $date_day == M_REPORT_TIME ) {
  $result = getReport($analytics, $profile, 'monthly');
  postToSlack($result);
}

// 月間レポート配信
if ( M_RANKING == true && $date_day == M_RANKING_TIME ) {
  $result = getRanking($analytics, $profile, 'monthly');
  postToSlack($result);
}


// ----------------------------------------------------
// Set Functions
// ----------------------------------------------------

// Creates and returns the Analytics Reporting service object.
function initializeAnalytics() {
  // Use the developers console and download your service account
  // credentials in JSON format. Place them in this directory or
  // change the key file location if necessary.
  $KEY_FILE_LOCATION = KEY_FILE;

  // Create and configure a new client object.
  $client = new Google_Client();
  $client->setApplicationName(APP_NAME);
  $client->setAuthConfig($KEY_FILE_LOCATION);
  $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
  $analytics = new Google_Service_Analytics($client);

  return $analytics;
}

// Get the user's first view (profile) ID.
function getFirstProfileId($analytics) {
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

// レポート取得
// -------------------------

function getReport($analytics, $profile, $term){

    // 日付を取得
    if ($term == 'daily'){
        $start_this_term = date('Y-m-d', strtotime('-1 day'));
        $end_this_term   = date('Y-m-d', strtotime('-1 day'));
        $start_last_term = date('Y-m-d', strtotime('-2 day'));
        $end_last_term   = date('Y-m-d', strtotime('-2 day'));
    }
    if ($term == 'weekly'){
        $start_this_term = date('Y-m-d', strtotime('-1 week'));
        $end_this_term   = date('Y-m-d', strtotime('-1 day'));
        $start_last_term = date('Y-m-d', strtotime('-2 week'));
        $end_last_term   = date('Y-m-d', strtotime('-1 week - 1 day'));
    }
    if ($term == 'monthly'){
        $start_this_term = date('Y-m-d', strtotime(date('Y-m-01') . '-1 month'));
        $end_this_term   = date('Y-m-d', strtotime(date('Y-m-01') . '-1 day'));
        $start_last_term = date('Y-m-d', strtotime(date('Y-m-01') . '-2 month'));
        $end_last_term   = date('Y-m-d', strtotime(date('Y-m-01') . '-1 month -1 day'));
    }

    // セッション・PV・平均閲覧ページ数・平均セッション時間・直帰率を取得
    $results_this_term = $analytics->data_ga->get(
        'ga:' . $profile,
        $start_this_term,
        $end_this_term,
        'ga:sessions,ga:pageviews,ga:pageviewsPerSession,ga:avgSessionDuration,ga:bounceRate'
    );

    $results_last_term = $analytics->data_ga->get(
        'ga:' . $profile,
        $start_last_term,
        $end_last_term,
        'ga:sessions,ga:pageviews,ga:pageviewsPerSession,ga:avgSessionDuration,ga:bounceRate'
    );

    // 取得したデータから必要な部分を抽出
    $this_term_data = $results_this_term->rows;
    $last_term_data = $results_last_term->rows;

    // 先週と今週のレポートを比較して増減を計算
    // 直帰率だけ増減の矢印を逆に
    function calcReport($this, $last, $bounce_rate){
        $result = round( $this - $last, 2 );
        if($result > 0){
            $print = ' (+' . $result . ') ';
            $print .= ($bounce_rate == 0) ? '↗' : '↘';
            return $print;
        } elseif($result < 0) {
            $print = ' (' . $result . ') ';
            $print .= ($bounce_rate == 1) ? '↗' : '↘';
            return $print;
        } else {
            return ' (0) →';
        }
    }

    // データを見やすく整形
    $report = $start_this_term;
    if ($term != 'daily') {
      $report .= '〜' . $end_this_term;
    }
    $report .= 'のレポート' . "\n";
    $report .= '訪問数 : ' . $this_term_data[0][0] . calcReport( $this_term_data[0][0], $last_term_data[0][0], 0 ) . "\n";
    $report .= '合計PV : ' . $this_term_data[0][1] . calcReport( $this_term_data[0][1], $last_term_data[0][1], 0 ) . "\n";
    $report .= '平均閲覧ページ数 : ' . round( $this_term_data[0][2], 2 ) . calcReport( $this_term_data[0][2], $last_term_data[0][2], 0 ) . "\n";
    $report .= '平均滞在時間 : ' . ceil( $this_term_data[0][3] ) . '秒' . calcReport( $this_term_data[0][3], $last_term_data[0][3], 0 ) . "\n";
    $report .= '直帰率 : ' . round( $this_term_data[0][4], 1 ) . '%' . calcReport( $this_term_data[0][4], $last_term_data[0][4], 1 ) .  "\n";

    return $report;
}

// 人気記事を取得
// -------------------------

function getRanking($analytics, $profile, $term){

    // 日付を取得
    if ($term == 'daily'){
        $start  = date('Y-m-d', strtotime('-1 day'));
        $end    = date('Y-m-d', strtotime('-1 day'));
        $length = D_RANKING_LENGTH;
    }
    if ($term == 'weekly'){
        $start  = date('Y-m-d', strtotime('-1 week'));
        $end    = date('Y-m-d', strtotime('-1 day'));
        $length = W_RANKING_LENGTH;
    }
    if ($term == 'monthly'){
        $start  = date('Y-m-d', strtotime(date('Y-m-01') . '-1 month'));
        $end    = date('Y-m-d', strtotime(date('Y-m-01') . '-1 day'));
        $length = M_RANKING_LENGTH;
    }

    $results = $analytics->data_ga->get(
        'ga:' . $profile,
        $start,
        $end,
        'ga:pageviews',
        array(
            'dimensions'  => 'ga:pageTitle',  // データの区切り
            'sort'        => '-ga:pageviews', // ページビューでソート
            'max-results' => $length,         // 取得件数
        )
    );

    // 取得したデータから必要な部分を抽出
    $data = $results->rows;

    // 配列で取得したデータをループで回してランキングに
    $ranking = $start;
    if ($term != 'daily') {
      $ranking .= '〜' . $end;
    }
    $ranking .= 'の記事ランキング' . "\n";

    foreach ($data as $key => $row) {
        $title = str_replace(RANKING_REPLACE_TEXT, '', $row[0]);
        $ranking .= ($key + 1) . '.' . $title . ' ' . $row[1] . 'PV' . "\n";
    }

    return $ranking;
}


// Slackに送信する仕組み
// -------------------------

function postToSlack($text){

    $args = array(
        'token'      => SLACK_TOKEN,
        'channel'    => SLACK_CHANNEL,
        'text'       => $text,
        'username'   => SLACK_USERNAME,
        'icon_emoji' => SLACK_ICON,
    );

    $content = http_build_query($args);

    $header = [
        "Content-Type: application/x-www-form-urlencoded",
        "Content-Length: ".strlen($content)
    ];
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $header),
            'content' => $content,
        ]
    ];
    $ret = file_get_contents(SLACK_URL, false, stream_context_create($options));
    return;
}