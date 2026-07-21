<?php

namespace App\Models;

use App\DTO\CaldavSettingsDTO;
use App\DTO\WebdavSettingsDTO;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\DTO\MailSettingsDTO;

class SystemSettings extends Model
{
    /** @use HasFactory<\Database\Factories\SystemSettingsFactory> */
    use HasFactory;

    protected $fillable = [
        'mail_host',
        'mail_port',
        'mail',
        'mail_pass',
        'dav_url',
        'dav_user',
        'dav_pass',
        'webdav_user',
        'webdav_pass',
        'webdav_endpoint',
        'webdav_save_path',
        'timezone',
        'currency',
        'locale',
        // Plages horaires globales (La Pépite)
        'hourly_max_hours',
        'half_day_morning_start',
        'half_day_morning_end',
        'half_day_afternoon_start',
        'half_day_afternoon_end',
        'full_day_start',
        'full_day_end',
    ];

    public function mailSettings(): MailSettingsDTO {
        return app(SettingsService::class)->mail();
    }

    public function caldavSettings(): CaldavSettingsDTO {
        return app(SettingsService::class)->caldav();
    }
    public function webdavSettings(): WebdavSettingsDTO {
        return app(SettingsService::class)->webdav();
    }
    public function getTimezone(): string {
        return $this->timezone;
    }

    public function getCurrency(): string {
        return $this->currency;
    }

    public function getLocale(): string {
        return $this->locale;
    }

    protected $casts = [
        'mail_pass' => 'encrypted',
        'dav_pass' => 'encrypted',
        'webdav_pass' => 'encrypted',
    ];

    protected $hidden = [
        'mail_pass',
        'dav_pass',
        'webdav_pass',
    ];
}
