<?php

namespace App\Services;

use App\Models\Artifact;
use App\Models\Museum;

/** Dữ liệu có cấu trúc schema.org (SEO, dữ liệu mở): Museum và hiện vật (CreativeWork). */
class JsonLdBuilder
{
    public function __construct(private readonly ContentLocalizer $content)
    {
    }

    public function museum(Museum $museum): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Museum',
            'name' => $museum->name,
            'description' => $this->content->plain($museum->summary),
            'url' => localized_route('museum.show'),
            'sameAs' => $museum->website,
            'logo' => $museum->logo_url,
            'address' => array_filter(['@type' => 'PostalAddress', 'streetAddress' => $museum->address, 'addressLocality' => $museum->city, 'addressCountry' => $museum->country]),
            'geo' => $museum->lat ? ['@type' => 'GeoCoordinates', 'latitude' => $museum->lat, 'longitude' => $museum->lng] : null,
            'license' => 'https://creativecommons.org/licenses/by-sa/3.0/',
        ], fn ($v) => $v !== null && $v !== []);
    }

    public function artifact(Artifact $artifact): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'additionalType' => 'https://schema.org/Thing',
            'name' => $artifact->title,
            'alternateName' => $artifact->scientific_name,
            'identifier' => $artifact->catalog_number,
            'description' => $this->content->plain($artifact->summary),
            'url' => localized_route('artifacts.show', ['artifact' => $artifact->slug]),
            'image' => $artifact->coverImage()?->url,
            'material' => $artifact->material,
            'sameAs' => $artifact->source_url,
            'temporalCoverage' => $artifact->period?->name,
            'locationCreated' => $artifact->origin_locality ? ['@type' => 'Place', 'name' => $artifact->origin_locality] : null,
            'provider' => ['@type' => 'Museum', 'name' => $artifact->museum?->name ?? 'Naturalis Biodiversity Center'],
        ], fn ($v) => $v !== null);
    }
}
