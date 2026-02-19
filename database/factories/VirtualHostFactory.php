<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VirtualHost;
use Illuminate\Database\Eloquent\Factories\Factory;

class VirtualHostFactory extends Factory
{
    protected $model = VirtualHost::class;

    public function definition(): array
    {
        $hostname = $this->faker->unique()->domainName;

        return [
            'user_id'            => User::factory(),
            'domain_id'          => null,
            'server_id'          => null,
            'hostname'           => $hostname,
            'document_root'      => '/var/www/' . $hostname,
            'php_version'        => '8.3',
            'ssl_enabled'        => false,
            'ssl_certificate_id' => null,
            'letsencrypt_enabled'=> true,
            'nginx_config'       => null,
            'status'             => VirtualHost::STATUS_PENDING,
            'port'               => 80,
            'ipv4_address'       => null,
            'ipv6_address'       => null,
        ];
    }
}
