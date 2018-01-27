<?php

return [
    'google' => [
        
        'application_name' => 'My Application Name', //Use your own
        'client_id'       => 'some_code_here', //Use your own
        'client_secret'   => 'some_secret_here', //Use your own
        'redirect_uri'    => 'redirect_url_here', //Use your own
        'scopes'          => [
            Google_Service_Drive::DRIVE,
            Google_Service_Oauth2::USERINFO_EMAIL,
            Google_Service_Oauth2::USERINFO_PROFILE,
            Google_Service_Drive::DRIVE_READONLY,
            Google_Service_Drive::DRIVE_METADATA_READONLY,
            Google_Service_Drive::DRIVE_METADATA,
            Google_Service_Drive::DRIVE_FILE,
            Google_Service_Drive::DRIVE_SCRIPTS
        ],
        'access_type'     => 'offline',
        'approval_prompt' => 'force',
        'developer_key' => env('GOOGLE_DEVELOPER_KEY', ''),
    ],

    'user' => [
        'fileToken' =>  storage_path()."/savedtoken.json", //Token will be saved here as a file
        'isUnlimited' => false, //Storage capacity
    ],

    'service' => [
        'enable' => false, //For using service account
        'file' => storage_path()."/.credentials/servicetoken.json"  //Service token location if using service account
    ],

    'other' => [
        'dir_info' => 'directory.info' //Whatever is this
    ],
];
