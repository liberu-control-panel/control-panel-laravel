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
        $recordTypes = ['A', 'CNAME', 'MX', 'TXT'];

        return [
            'domain_id' => function () {
                return \App\Models\Domain::factory()->create()->id;
            },
            'record_type' => $this->faker->randomElement($recordTypes),
            'name' => $this->faker->domainName,
            'value' => $this->faker->ipv4,
            'ttl' => $this->faker->numberBetween(300, 86400), // TTL between 300 seconds (5 minutes) and 86400 seconds (24 hours)
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

