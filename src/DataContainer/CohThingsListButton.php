<?php

namespace PbdKn\ContaoContaohabBundle\DataContainer;

class CohThingsListButton
{
    public static function generateCustomButton($row, $href, $label, $title, $icon, $attributes): string
    {
        return '<a href="' . $href . '&id=' . $row['id'] . '" title="' . $title . '"' . $attributes . '>' .
               '<img src="' . $icon . '" alt="' . $label . '"> ' . $label . '</a>';
    }
}
