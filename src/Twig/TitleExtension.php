<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig;

use App\Configuration\SystemConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TitleExtension extends AbstractExtension
{
    private $translator;
    private $configuration;

    public function __construct(TranslatorInterface $translator, SystemConfiguration $configuration)
    {
        $this->translator = $translator;
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('get_title', [$this, 'generateTitle']),
        ];
    }

    public function generateTitle(?string $prefix = null, string $delimiter = ' – '): string
    {
        return ($prefix ?? '') . $this->configuration->getBrandingTitle() . $delimiter . $this->translator->trans('time_tracking', [], 'messages');
    }
}
