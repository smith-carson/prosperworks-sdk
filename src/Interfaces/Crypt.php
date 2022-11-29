<?php namespace ProsperWorks\Interfaces;

interface Crypt
{
    public function encryptBase64(string $string): string;
    public function decryptBase64(string $string): string;
}