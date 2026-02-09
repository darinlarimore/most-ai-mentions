<?php

namespace App\Enums;

enum SiteCategory: string
{
    case Marketing = 'marketing';
    case Company = 'company';
    case Tech = 'tech';
    case Software = 'software';
    case Saas = 'saas';
    case Agency = 'agency';
    case Startup = 'startup';
    case Enterprise = 'enterprise';
    case Consulting = 'consulting';
    case Ecommerce = 'ecommerce';
    case Finance = 'finance';
    case Healthcare = 'healthcare';
    case Education = 'education';
    case Media = 'media';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Marketing => 'Marketing',
            self::Company => 'Company',
            self::Tech => 'Tech',
            self::Software => 'Software',
            self::Saas => 'SaaS',
            self::Agency => 'Agency',
            self::Startup => 'Startup',
            self::Enterprise => 'Enterprise',
            self::Consulting => 'Consulting',
            self::Ecommerce => 'E-commerce',
            self::Finance => 'Finance',
            self::Healthcare => 'Healthcare',
            self::Education => 'Education',
            self::Media => 'Media',
            self::Other => 'Other',
        };
    }
}
