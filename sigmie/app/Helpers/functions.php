<?php

declare(strict_types=1);

namespace App\Helpers {

    use Composer\InstalledVersions;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    if (!function_exists('get_gravatar')) {
        /**
         * Get a Gravatar URL for a specified email address.
         *
         * @param string $email The email address
         * @param int $s Size in pixels, defaults to 80px [ 1 - 2048 ]
         * @param string $d Default imageset to use [ 404 | mp | identicon | monsterid | wavatar ]
         * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
         */
        function get_gravatar($email, $s = 80, $d = 'mp', $r = 'g')
        {
            $url = 'https://www.gravatar.com/avatar/';
            $url .= md5(strtolower(trim($email)));
            $url .= "?s=$s&d=$d&r=$r";

            return $url;
        }
    }


    if (!function_exists('app_core_version')) {
        /**
         * @return string
         */
        function app_core_version(): string
        {
            return InstalledVersions::getVersion('sigmie/app-core');
        }
    }

    if (!function_exists('temp_file_path')) {
        /**
         * @return string
         */
        function temp_file_path()
        {
            $filesystem = Storage::disk('local');
            $filename = Str::random(40);
            $path = "temp/{$filename}";
            return $filesystem->path($path);
        }
    }

    if (!function_exists('is_json')) {
        /**
         * @return bool
         */
        function is_json($content)
        {
            if ($content === '') {
                return false;
            }

            json_decode($content);

            if (json_last_error()) {
                return false;
            }

            return true;
        }
    }
};
