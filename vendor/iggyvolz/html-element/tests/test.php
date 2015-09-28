<?php 

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload

use htmlElement\htmlElement;

$html=new htmlElement("html");
$html->toggle();
