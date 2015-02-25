<?php
require dirname(__FILE__).'/vendor/autoload.php';
ini_set('memory_limit', '-1');
$dateFrom = date('Y-m-d',strtotime("now - 1 month"));
$dateTo = date('Y-m-d',strtotime("now"));
$vk = new WallAnalyzer();
$vk->collectData($dateFrom,$dateTo)->saveData()->reportSender();