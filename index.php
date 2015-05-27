<?php

require_once 'vendor/autoload.php';
require_once 'lib/CliRunner.php';
require_once 'lib/EntityGenerator.php';

$runner = new \App\CliRunner;
die($runner->run());