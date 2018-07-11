<?php
namespace Orolyn\GenericPHP;

final class ClassName
{
    public static function create(string $name): string
    {
        $name = preg_replace_callback(
            '/([\w\d_]+)\<([^\<]+?)\>/',
            function ($match) {
                print_r($match);
                die();
            },
            $name
        );
    }

    private function serialize()
    {

    }
}
