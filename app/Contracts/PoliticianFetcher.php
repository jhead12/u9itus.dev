<?php

namespace App\Contracts;

interface PoliticianFetcher
{
    public function key(): string;

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array{source:string,row:array<string,mixed>}>
     */
    public function fetch(array $options = []): array;
}
