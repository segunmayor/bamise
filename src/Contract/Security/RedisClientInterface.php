<?php

declare(strict_types=1);

namespace Bamise\Contract\Security;

interface RedisClientInterface
{
    /**
     * Evaluates a Lua script atomically on the Redis server.
     *
     * Maps directly to the Redis EVAL command.
     * Consumer adapters should wrap \Redis::eval() or \Predis\Client::eval().
     *
     * @param list<string>           $keys KEYS[] available to the script
     * @param list<int|string|float> $args ARGV[] available to the script
     */
    public function evalScript(string $script, array $keys, array $args): mixed;
}
