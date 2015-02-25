<?php
require dirname(__FILE__).'/vendor/autoload.php';
$dateFrom = date('Y-m-d',strtotime("first day of September 2014"));
$dateTo = date('Y-m-d',strtotime("first day of October 2014"));
$vk = new WallAnalyzer();
$vk->collectData($dateFrom,$dateTo)->saveData();