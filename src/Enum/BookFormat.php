<?php

namespace App\Enum;

/**
 * Book physical/digital format.
 */
enum BookFormat: string
{
    case HARDCOVER = 'hardcover';
    case PAPERBACK = 'paperback';
    case EBOOK = 'ebook';
    case AUDIOBOOK = 'audiobook';
}
