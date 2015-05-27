<?php

require_once 'vendor/autoload.php';
require_once 'CliRunner.php';
require_once 'EntityGenerator.php';

$runner = new \App\CliRunner;
die($runner->run());