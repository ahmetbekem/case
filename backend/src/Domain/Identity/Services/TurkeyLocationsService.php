<?php

declare(strict_types=1);

namespace Domain\Identity\Services;

/**
 * ADR-018 — Türkiye il/ilçe statik veri kaynağı.
 *
 * Veri turkiyeapi.dev tabanlı; 81 il + 973 ilçe. JSON dosyası
 * resource_path('data/turkey-locations.json') altında, build'a dahil.
 * External API'ye runtime bağımlılık yok (offline çalışır + zero
 * latency).
 *
 * Singleton olarak service container'a register edildi (LocationsServiceProvider);
 * her constructor çağrısında JSON parse maliyeti olmasın.
 *
 * Lookup'lar büyük/küçük harf duyarsız (mb_strtolower) — frontend
 * dropdown ile aynı kanonik isim gönderildiği için case sensitivity
 * pratik bir sorun yaratmaz, ama defansif olalım.
 */
final class TurkeyLocationsService
{
    /** @var array<int, array{id: string, name: string, districts: array<int, string>}> */
    private array $cities;

    /** @var array<string, array<int, string>>  Lower-cased city name → districts. */
    private array $districtsByCityLower;

    public function __construct(string $dataPath)
    {
        $raw = @file_get_contents($dataPath);
        if ($raw === false) {
            throw new \RuntimeException(
                "TurkeyLocationsService: data file not found at {$dataPath}"
            );
        }
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !isset($decoded['cities']) || !is_array($decoded['cities'])) {
            throw new \RuntimeException(
                'TurkeyLocationsService: malformed data — expected {cities: [...]}'
            );
        }

        /** @var array<int, array{id: string, name: string, districts: array<int, string>}> $cities */
        $cities = [];
        $byLower = [];
        foreach ($decoded['cities'] as $entry) {
            if (!isset($entry['name'], $entry['districts']) || !is_array($entry['districts'])) {
                continue;
            }
            $name = (string) $entry['name'];
            $districts = array_values(array_map(
                static fn ($d) => (string) $d,
                $entry['districts'],
            ));
            $cities[] = [
                'id' => (string) ($entry['id'] ?? $name),
                'name' => $name,
                'districts' => $districts,
            ];
            $byLower[mb_strtolower($name, 'UTF-8')] = $districts;
        }
        $this->cities = $cities;
        $this->districtsByCityLower = $byLower;
    }

    /**
     * @return array<int, array{id: string, name: string}>  id+name (district sayısı yok — endpoint
     * payload'unu hafif tutar; districts için ayrı endpoint kullanılır)
     */
    public function getAllCities(): array
    {
        return array_map(
            static fn (array $c): array => ['id' => $c['id'], 'name' => $c['name']],
            $this->cities,
        );
    }

    /**
     * @return array<int, string>  Boş array → şehir bulunamadı (caller
     * 404 atmalı; Service exception fırlatmıyor — UI dropdown'unu
     * kırmamak için).
     */
    public function getDistrictsByCity(string $cityName): array
    {
        return $this->districtsByCityLower[mb_strtolower($cityName, 'UTF-8')] ?? [];
    }

    public function isValidCity(string $cityName): bool
    {
        return isset($this->districtsByCityLower[mb_strtolower($cityName, 'UTF-8')]);
    }

    public function isValidDistrict(string $cityName, string $districtName): bool
    {
        $districts = $this->getDistrictsByCity($cityName);
        if ($districts === []) {
            return false;
        }
        $needle = mb_strtolower($districtName, 'UTF-8');
        foreach ($districts as $d) {
            if (mb_strtolower($d, 'UTF-8') === $needle) {
                return true;
            }
        }
        return false;
    }
}
