<?php

namespace App\Support;

use Illuminate\Translation\MessageSelector;

final class LocaleMessageSelector extends MessageSelector
{
    public function getPluralIndex($locale, $number)
    {
        // Laravel knows Serbian plural rules under sr, but not its Latin script tag.
        return parent::getPluralIndex($locale === 'sr_Latn' ? 'sr' : $locale, $number);
    }
}
