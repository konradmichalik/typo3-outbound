<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Outbound Petstore (test fixture)',
    'description' => 'Frontend plugin calling the Swagger Petstore through typo3_outbound.',
    'category' => 'plugin',
    'state' => 'test',
    'version' => '1.0.0',
    'constraints' => ['depends' => ['typo3' => '13.4.0-14.99.99', 'typo3_outbound' => '']],
];
