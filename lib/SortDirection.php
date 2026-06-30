<?php

if (PHP_VERSION_ID < 80600 && !enum_exists('SortDirection', false)) {
    enum SortDirection
    {
        case Ascending;
        case Descending;
    }
}