<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig;

use Symfony\Component\Intl\Intl;
use App\Entity\Timesheet;
use Twig\TwigFilter;

/**
 * Multiple Twig extensions: filters and functions
 */
class Extensions extends \Twig_Extension
{
    /**
     * @var string[]
     */
    private $locales;

    /**
     * Extensions constructor.
     * @param string $locales
     */
    public function __construct($locales)
    {
        $this->locales = explode('|', $locales);
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('duration', array($this, 'duration')),
            new TwigFilter('durationForEntry', array($this, 'durationForEntry')),
            new TwigFilter('money', array($this, 'money')),
            new TwigFilter('currency', array($this, 'currency')),
            new TwigFilter('country', array($this, 'country')),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('locales', [$this, 'getLocales']),
        ];
    }

    /**
     * Returns the formatted duration for a Timesheet entry.
     *
     * @param Timesheet $entry
     * @param bool $includeSeconds
     * @return string
     */
    public function durationForEntry(Timesheet $entry, $includeSeconds = false)
    {
        return $this->duration($entry->getDuration(), $includeSeconds);
    }

    /**
     * Transforms seconds into a duration string.
     *
     * @param $seconds
     * @param bool $includeSeconds
     * @return string
     */
    public function duration($seconds, $includeSeconds = false)
    {
        $hour = floor($seconds / 3600);
        $minute = floor(($seconds / 60) % 60);

        $hour = $hour > 9 ? $hour : '0' . $hour;
        $minute = $minute > 9 ? $minute : '0' . $minute;

        if (!$includeSeconds) {
            return $hour . ':' . $minute . ' h';
        }

        $second = $seconds % 60;
        $second = $second > 9 ? $second : '0' . $second;

        return $hour . ':' . $minute  . ':' . $second . ' h';
    }

    /**
     * @param string $currency
     * @return string
     */
    public function currency($currency)
    {
        return Intl::getCurrencyBundle()->getCurrencySymbol($currency);
    }

    /**
     * @param string $country
     * @return string
     */
    public function country($country)
    {
        return Intl::getRegionBundle()->getCountryName($country);
    }

    /**
     * @param float $amount
     * @param string $currency
     * @return string
     */
    public function money($amount, $currency = null)
    {
        $result = number_format(round($amount, 2), 2);
        if ($currency !== null) {
            $result .= ' ' . Intl::getCurrencyBundle()->getCurrencySymbol($currency);
        }
        return $result;
    }

    /**
     * Takes the list of codes of the locales (languages) enabled in the
     * application and returns an array with the name of each locale written
     * in its own language (e.g. English, Français, Español, etc.)
     *
     * @return array
     */
    public function getLocales()
    {
        $locales = [];
        foreach ($this->locales as $locale) {
            $locales[] = ['code' => $locale, 'name' => Intl::getLocaleBundle()->getLocaleName($locale, $locale)];
        }

        return $locales;
    }
}
