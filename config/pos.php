<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Receipt printer
    |--------------------------------------------------------------------------
    | The register prints through a raw ESC/POS stream rather than the browser's
    | print dialog: the cashier presses one key, the slip cuts itself and the
    | drawer pops. The slip is rasterised in the browser from the very markup
    | the customer-facing receipt page already renders, so Arabic, RTL and the
    | dinar's LTR isolate come out exactly as designed — no printer code page is
    | involved. What Laravel does here is wrap that bitmap in ESC/POS and hand
    | it to whichever transport the shop is wired for.
    |
    | Leave the driver as `null` and the register falls back to the browser
    | print dialog, which is still a working receipt — just not a silent one.
    */

    'printer' => [

        // cups | network | device | command | null
        //
        //   network — printer has its own IP (Xprinter LAN models). The only
        //             option when the app and the printer are on one LAN but
        //             not one machine.
        //   device  — write raw bytes to a device node the PHP process can
        //             open: /dev/usb/lp0 on Linux, a shared \\PC\POS80 queue
        //             on Windows.
        //   command — pipe the bytes through a spooler: `lp -d POS80 -o raw`.
        //             The right choice when the printer is USB-attached to the
        //             same host that runs PHP.
        //   cups    — hand the rendered receipt to the manufacturer's own CUPS
        //             queue and let its filter make the ESC/POS. The answer for
        //             a USB printer whose firmware garbles a tall image: the
        //             vendor filter knows the buffer limits we do not.
        'driver' => env('POS_PRINTER_DRIVER'),

        'host' => env('POS_PRINTER_HOST'),
        'port' => (int) env('POS_PRINTER_PORT', 9100),
        'device' => env('POS_PRINTER_DEVICE'),
        'command' => env('POS_PRINTER_COMMAND'),

        // With driver=cups: the queue name, and the head's burn strength (1-8).
        'queue' => env('POS_PRINTER_QUEUE'),
        'density' => (int) env('POS_PRINTER_DENSITY', 5),

        // Seconds to wait on the socket. A register cannot hang on a printer
        // someone unplugged, so this is deliberately short.
        'timeout' => (float) env('POS_PRINTER_TIMEOUT', 3),

        // Printable dots across the head. 80mm heads are 576; 58mm are 384.
        // Must be a multiple of 8 — the raster format packs 8 dots per byte.
        'width' => (int) env('POS_PRINTER_WIDTH', 576),

        // Below this luminance a pixel is burned black. Raise it if the slip
        // prints faint, lower it if greys come out as solid blocks.
        'threshold' => (int) env('POS_PRINTER_THRESHOLD', 160),

        // Blank lines fed past the head before the cut, so the cut lands below
        // the last line of text rather than through it.
        'feed' => (int) env('POS_PRINTER_FEED', 4),

        // Largest single write handed to the printer. A counter-top head has a
        // small buffer and poor flow control; 83KB in one go overruns it and it
        // prints the raster as gibberish, while ~8KB at a time is comfortable.
        // Raise it only if you have proved this printer can take more.
        'chunk_bytes' => (int) env('POS_PRINTER_CHUNK', 8192),

        // raster | column
        //
        //   raster — one `GS v 0` per 24-dot band. Fast, and what most printers
        //            prefer.
        //   column — `ESC *` strips with a feed after each, via mike42/escpos-php.
        //            Slower, marked experimental upstream, but the way out when
        //            firmware garbles a tall `GS v 0` image.
        'raster_mode' => env('POS_PRINTER_RASTER', 'raster'),

        'cut' => (bool) env('POS_PRINTER_CUT', true),

        // Pop the drawer on cash sales. Pin 0 is the `ESC p 0` connector, which
        // is the one Xprinter wires to RJ11.
        'cash_drawer' => (bool) env('POS_PRINTER_CASH_DRAWER', true),
        'cash_drawer_pin' => (int) env('POS_PRINTER_CASH_DRAWER_PIN', 0),
    ],

];
