<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Export\Base;

/**
 * @deprecated remove me in 2.0
 */
interface DispositionInlineInterface
{
    /**
     * @param bool $useInlineDisposition
     * @return void
     */
    public function setDispositionInline(bool $useInlineDisposition): void;
}
