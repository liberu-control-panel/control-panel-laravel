<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhpConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'php_version',
        'memory_limit',
        'upload_max_filesize',
        'post_max_size',
        'max_execution_time',
        'max_input_time',
        'max_input_vars',
        'display_errors',
        'short_open_tag',
        'error_reporting',
        'session_save_path',
        'custom_settings',
    ];

    protected $casts = [
        'memory_limit'        => 'integer',
        'upload_max_filesize' => 'integer',
        'post_max_size'       => 'integer',
        'max_execution_time'  => 'integer',
        'max_input_time'      => 'integer',
        'max_input_vars'      => 'integer',
        'display_errors'      => 'boolean',
        'short_open_tag'      => 'boolean',
        'custom_settings'     => 'array',
    ];

    /**
     * Supported PHP versions (mirrors Virtualmin's selection).
     */
    public static function getSupportedVersions(): array
    {
        return ['7.4', '8.0', '8.1', '8.2', '8.3'];
    }

    /**
     * Get the domain that owns this PHP configuration.
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Convert the configuration to an array of php.ini directives.
     */
    public function toIniDirectives(): array
    {
        $directives = [
            'memory_limit'        => $this->memory_limit . 'M',
            'upload_max_filesize' => $this->upload_max_filesize . 'M',
            'post_max_size'       => $this->post_max_size . 'M',
            'max_execution_time'  => $this->max_execution_time,
            'max_input_time'      => $this->max_input_time,
            'max_input_vars'      => $this->max_input_vars,
            'display_errors'      => $this->display_errors ? 'On' : 'Off',
            'short_open_tag'      => $this->short_open_tag ? 'On' : 'Off',
            'error_reporting'     => $this->error_reporting,
        ];

        if ($this->session_save_path) {
            $directives['session.save_path'] = $this->session_save_path;
        }

        if (!empty($this->custom_settings)) {
            $directives = array_merge($directives, $this->custom_settings);
        }

        return $directives;
    }

    /**
     * Render the configuration as a php.ini file string.
     */
    public function toIniString(): string
    {
        $lines = ["; PHP configuration for domain: {$this->domain->domain_name}"];
        foreach ($this->toIniDirectives() as $key => $value) {
            $lines[] = "{$key} = {$value}";
        }
        return implode("\n", $lines) . "\n";
    }
}
