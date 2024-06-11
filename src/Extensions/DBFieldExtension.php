<?php

namespace SilverStripe\Forager\Extensions;

use SilverStripe\Core\Extension;

class DBFieldExtension extends Extension
{

    public function getSearchValue(): array|string
    {
        $value = $this->owner->getValue() ?? '';

        if (is_array($value)) {
            array_map(
                function ($arrayItem) {
                    return preg_replace('/\s+/S', ' ', strip_tags($arrayItem));
                },
                $value
            );

            return $value;
        }

        return preg_replace('/\s+/S', ' ', strip_tags($value));
    }

}
