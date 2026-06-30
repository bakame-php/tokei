<?php

if (PHP_VERSION_ID < 80600) {
    enum SortDirection
    {
        case Ascending;
        case Descending;
    }
}