<?php

use addon\ceping\app\command\ImportTests;
use addon\ceping\app\command\GenCovers;

return [
    'commands' => [
        'ceping:import-tests' => ImportTests::class,
        'ceping:gen-covers'   => GenCovers::class,
    ],
];
