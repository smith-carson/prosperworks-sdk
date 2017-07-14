<?php namespace ProsperWorks\Interfaces;

interface Cache
{
    public function get($key):mixed;
    public function save(string $key, mixed $data, int $lifetime = null);
}