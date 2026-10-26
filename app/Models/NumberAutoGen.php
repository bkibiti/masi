<?php


namespace App\Models;


class NumberAutoGen
{
    public function generateNumber()
    {
        $unique = strtoupper(substr(md5(microtime()), rand(0, 26), 8));
        return $unique;
    }
}
