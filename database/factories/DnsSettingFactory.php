<?php

namespace Database\Factories;

use App\Models\DnsSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class DnsSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DnsSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $recordType = $this->faker->randomElement(['A', 'AAAA', 'CNAME', 'MX', 'TXT']);
        
        $value = match($recordType) {
            'A' => $this->faker->ipv4,
            'AAAA' => $this->faker->ipv6,
            'CNAME' => $this->faker->domainName,
            'MX' => 'mail.' . $this->faker->domainName,
            'TXT' => 'v=spf1 include:_spf.' . $this->faker->domainName . ' ~all',
            default => $this->faker->ipv4,
        };

        $name = $this->faker->randomElement(['@', 'www', 'mail', 'ftp']);

        return [
            'domain_id' => function () {
                return \App\Models\Domain::factory()->create()->id;
            },
            'record_type' => $recordType,
            'name' => $name,
            'value' => $value,
            'ttl' => $this->faker->randomElement([300, 600, 1800, 3600, 7200, 86400]),
            'priority' => $recordType === 'MX' ? $this->faker->numberBetween(10, 50) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

