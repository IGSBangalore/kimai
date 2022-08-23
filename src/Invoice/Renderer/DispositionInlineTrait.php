<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice\Renderer;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;

trait DispositionInlineTrait
{
    /**
     * @var string
     */
    private $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT;

    /**
     * @return string
     */
    public function getDisposition(): string
    {
        return $this->disposition;
    }

    /**
     * @param bool $useInlineDisposition
     * @return void
     */
    public function setDispositionInline(bool $useInlineDisposition): void
    {
        if ($useInlineDisposition) {
            $this->disposition = ResponseHeaderBag::DISPOSITION_INLINE;
        } else {
            $this->disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT;
        }
    }
}
