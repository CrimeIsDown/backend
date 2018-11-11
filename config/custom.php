<?php

return [
    'git' => [
        'user' => [
            'name' => env('CID_GIT_NAME', 'Eric Tendian'),
            'email' => env('CID_GIT_EMAIL', 'erictendian@gmail.com'),
        ],
    ],
    'directives' => [
        'repository' => env('CID_DIRECTIVES_REPOSITORY', 'git@github.com:CrimeIsDown/cpd-directives.git'),
        'clone_path' => env('CID_DIRECTIVES_PATH', storage_path('app/cpd-directives')),
        'public_path' => 'public/directives'
    ]
];