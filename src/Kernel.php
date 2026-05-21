<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();
        
        // Set default timezone - adjust to your local timezone
        // Common options: 'Asia/Manila' (UTC+8), 'Asia/Bangkok' (UTC+7), 'Asia/Jakarta' (UTC+7)
        date_default_timezone_set('Asia/Manila');
    }
}
