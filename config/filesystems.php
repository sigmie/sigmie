<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3", "rackspace"
    |
    */

    'disks' => [

        'gcs' => [
            'driver' => 'gcs',
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID', 'sigmie'),
            'key_file' => [
                'type' => "service_account",
                'private_key_id' => '5ad76ef8542173bd5fd6d6a27842c49eb8cd2d62',
                'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC5TtXvzrlu8mfD\nVDBALTkLL+Mh+Ng+WR7ZbPOZlG1nBmVKwSU6mtzGReCyaOWYCZr1G+hc+72wIFVs\njlrVE9ifXl7LbIUlGGi+9yhFVB3v8zuDr39dO0htXGt14vYAbnCvRcwn0lTKPDa7\nDIZYiIw79VslbvAIJSndK8p2sFpVGAL/+240SSdP9vNLmTVgBHWNkBHFp5Ndu/uX\nC3HlJhUB9ahIC3EBUNPtoVwsqkPRyUqE3ZLr9dQMI24QxrDSDkT4sTbbFhekYw+6\nYwDdNAe8rqxJpq5igh/MDl0VhIbN/rcpKKPtnVhkz1mvFIUTYXnY09asJJLlRmre\nJ+gV0c6BAgMBAAECggEAFYe93p9/tw7OFIlPE93NP2oAc12EznWvWmbIxOLUdUBu\ne2mGiFEe7qCG02PRrUEMcoccD9Wl+/U9x5+gmRo1tIqT7AG/VIiJ/wobcTatRchW\ndV3qJ+xv5Vj0GV3V5EXwrMSozEWpWVf2qfPyFg5DcOH4hZhmpiOfQhXI79JcpH8z\nXw/R0PfJLct5fhXBTp/ZAGqS1PnugRLc0zJC3EjJeCtP7kDKfTdqZq+KxPuQSO9o\nSQBTyWB/ats6yGD21S0AgrhBFLjdO/km7Gl5PDJD3+yBwav0vhafW8VAEK6PH1Er\nfOLnvNJbiZSjtp+rS/92vJMiOmyivLjTlFlQNH/5wwKBgQDvcJ7mfFYcUMXs8ZE4\nJGWgbVnnfaBL8PwQGsTxJDEE6OwinqAOs8/lgKGYXqNW2rzkSFaW+XqKr63XZXPs\nUmeDDKsr1htqSCvDJcJymVcANZW0ad+KqYTyuBjfPSOcpR9Lr6SmNn8j9M59I/ZK\nSFOJT75P1oQeg8jVyEYGxwcCGwKBgQDGH8lt/VIqfwKEwtmqE+Sj7kiDbFzkphwo\n8OI6q9J1lNm2BaYPRxoq6/NLF1MMnk1DCg9NydNLQ/7r/koR1j7U5J5ICmkjK589\nMn5gQBTE4ur8m8p81MOArYghiyp5O+ifGK00giV4tIPed710++D7TzYlELxFELum\n1LryPeRbkwKBgGpQPA2kyWs7Jhalz8/4wTLxOskBS6pMbu8Lo8RaSHWgojBg9Dpy\n9m01pwqLgsTuzI4j3GaekR2BEZQhFM6qbBGRD3OgVn3jW6MDYgoWMb4DDNyVYc8k\no8ZNnaWJdQV5f6LQcy/l5J/nc8O2swWahZTjVmIGopUV+JeLnCQoPOFfAoGAVHMy\nKgcDogeXbD2eCCgImnO0RwjGo9PGRxE+bSHWSLZVAohv2y1Eky0V/FkJ1mQXrM65\n7T9tKDTokXFH40h+acK1USHW5N3wN2axhZdrAu1ympBRhFowp0XQs/Oc/CY0JrSf\nB7W+ATB40Tga8qV2pciC3NPAXHQgeKFOunj1QK8CgYEAyfpEICAToB6GMeHxVddp\nl8+GXfx6G7plDmYtuHWsymChU1Td9PwYz62tg80eC3FJZ8nMXY2nY2faTFrlzG5I\ne/Yt7osMC1VryrSx22atQZkRRntBZ8Xe0flVwv1/PSDPRz8l8M0IimDkAq1ZH1jo\ngRMf4AV8TEVXSYDFNzZ40PI=\n-----END PRIVATE KEY-----\n",
                'client_email' => "storage-manager@sigmie.iam.gserviceaccount.com",
                'client_id' => "100199798978721426384",
                'auth_uri' => "https://accounts.google.com/o/oauth2/auth",
                'token_uri' => "https://oauth2.googleapis.com/token",
                'auth_provider_x509_cert_url' => "https://www.googleapis.com/oauth2/v1/certs",
                'client_x509_cert_url' => "https://www.googleapis.com/robot/v1/metadata/x509/storage-manager%40sigmie.iam.gserviceaccount.com",
            ],
            'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET', 'sigmie_backups'),
            'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', ''),
            'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI', null),
            'visibility' => 'private',
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
        ],

    ],

];
