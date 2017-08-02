<?php

// Analytics Setting
const KEY_FILE = './service-account-credentials.json';
const APP_NAME = 'Hello Analytics';

// レポートを送るかどうか
const D_REPORT  = false;
const D_RANKING = false;
const W_REPORT  = true;
const W_RANKING = true;
const M_REPORT  = true;
const M_RANKING = true;

// ランキング何位まで出すか
const D_RANKING_LENGTH = 10;
const W_RANKING_LENGTH = 10;
const M_RANKING_LENGTH = 20;

// タイトル内で削除したい文字列
const RANKING_REPLACE_TEXT = '';

// レポートを送るタイミング
const W_REPORT_TIME  = 1;
const W_RANKING_TIME = 1;
const M_REPORT_TIME  = 1;
const M_RANKING_TIME = 1;

// SLACKの設定
const SLACK_URL      = 'https://slack.com/api/chat.postMessage';
const SLACK_TOKEN    = "";
const SLACK_CHANNEL  = "";
const SLACK_USERNAME = "";
const SLACK_ICON     = "";