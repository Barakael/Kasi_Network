<?php

declare(strict_types=1);

namespace App\Domain\Router;

/**
 * IEEE OUI prefixes for devices commonly bound on a hotspot: TVs, consoles,
 * printers. Unknown prefixes return null so the portal can fall back to the MAC.
 */
final class OuiLookup
{
    /**
     * @var array<string, string>
     */
    private const array VENDORS = [
        '000C29' => 'VMware',
        '001A11' => 'Google',
        '001D43' => 'Cisco',
        '002248' => 'Microsoft',
        '00232A' => 'eero',
        '0024E4' => 'Withings',
        '0030BD' => 'Belkin',
        '04021F' => 'HUAWEI',
        '04D4C4' => 'ASUSTek',
        '08ED9D' => 'TCL',
        '0C96E6' => 'Nintendo',
        '104738' => 'Sony',
        '18B430' => 'Nest',
        '1C5A3E' => 'Samsung Electronics',
        '20579E' => 'Hunan Onsky',
        '28C2DD' => 'AzureWave',
        '2C8A72' => 'HTC',
        '38F9D3' => 'Apple',
        '3C5A37' => 'Samsung Electronics',
        '40B076' => 'ASUSTek',
        '48A195' => 'Apple',
        '4C3275' => 'Apple',
        '50CCF8' => 'Samsung Electronics',
        '58A023' => 'Intel',
        '5C514F' => 'Intel',
        '60A4D0' => 'Samsung Electronics',
        '64B0A6' => 'Apple',
        '6C4008' => 'Apple',
        '70B3D5' => 'IEEE Registration Authority',
        '74D02B' => 'ASUSTek',
        '78CA83' => 'IEEE Registration Authority',
        '7C2EBD' => 'Google',
        '80EA96' => 'Apple',
        '88C9E8' => 'Sony',
        '8C8590' => 'Apple',
        '90CD1F' => 'Quectel',
        '94E979' => 'Liteon',
        '9C8E99' => 'Hewlett Packard',
        'A0D365' => 'Intel',
        'A4C138' => 'Telink Semiconductor',
        'AC37EA' => 'LG Electronics',
        'B0E892' => 'Seiko Epson',
        'B4E62D' => 'Espressif',
        'B8D7AF' => 'Murata',
        'BC83A7' => 'SHENZHEN CHUANGWEI',
        'C8D083' => 'Apple',
        'CC46D6' => 'Cisco',
        'D0E140' => 'Apple',
        'D8A98B' => 'Texas Instruments',
        'DC0C5C' => 'Apple',
        'E0CB4E' => 'ASUSTek',
        'E8ED6D' => 'TP-LINK',
        'F0D5BF' => 'Intel',
        'F4F5D8' => 'Google',
        'F8E61A' => 'Samsung Electronics',
        'FCDB21' => 'Samsara',
    ];

    public function vendor(string $macHexOrFormatted): ?string
    {
        $hex = strtoupper((string) preg_replace('/[^0-9A-F]/', '', strtoupper($macHexOrFormatted)));

        if (strlen($hex) < 6) {
            return null;
        }

        return self::VENDORS[substr($hex, 0, 6)] ?? null;
    }
}
