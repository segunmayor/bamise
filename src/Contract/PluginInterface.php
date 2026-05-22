<?php

declare(strict_types=1);

namespace Bamise\Contract;

interface PluginInterface
{
    public function register(PluginRegistryInterface $registry): void;
}
