<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An email captured by the storefront footer's newsletter form. */
final class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'locale'];
}
