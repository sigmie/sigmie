<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (!function_exists('get_gravatar')) {
    /**
     * Get a Gravatar URL for a specified email address.
     *
     * @param string $email The email address
     * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
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

if (!function_exists('temp_file_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param  string  $path
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
