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
    ],
    'copa' => [
        'repository' => env('CID_COPA_REPOSITORY', 'git@github.com:CrimeIsDown/copa-cases.git'),
        'clone_path' => env('CID_COPA_PATH', storage_path('app/copa-cases')),
    ],
    'youtube' => [
        'channel_id' => env('CID_CHANNEL_ID', 'UCUS-tAwCyxBrrA3FtSjH2NA'),
        'api_key' => env('YOUTUBE_API_KEY', null),
    ]
];