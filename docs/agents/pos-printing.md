# Counter receipt printing

How a slip gets from the register to an Xprinter (or any other ESC/POS unit), and
why it is built the way it is.

## The shape of it

The register never navigates away. `CompleteSaleController` flashes the whole
slip back (`justSold`), `Register.vue` renders `ReceiptPaper` **off-screen**,
photographs it and posts the bitmap, then confirms in a dialog over the basket.
The receipt page still exists, for reprints and as the no-printer fallback.

Off-screen means parked at `-10000px`, **not** `display:none` — html2canvas
measures real layout and `display:none` has none. `.receipt-offscreen` stands
down under `@media print` so the browser fallback still lands the slip on the page.

```
Register.vue ─┐
Receipt.vue  ─┴─html2canvas──▶  PNG  ──POST──▶  PrintReceiptController
                                                      │
                                            ReceiptPrinter::print()
                                                      │
                        ┌─────────────────────────────┴───────────────┐
                 driver=cups                                  everything else
                        │                                             │
              CupsDriverPrinter                        Raster ─▶ EscPosDocument
              (vendor filter makes                              │
               the ESC/POS)                              PrinterConnection
                        │                                network │ device │ command
                        └──▶ lp -d <queue> -o PageCutType=… -o CashDrawer1Setting=…
```

The browser rasterises the very `.receipt` node the customer-facing page renders;
Laravel wraps that bitmap in ESC/POS and pushes it down a wire.

The order page's **delivery slip** rides the same rails: `admin/orders/DeliverySlip`
is 80mm paper like the receipt, `usePosPrinting` rasterises it, and
`PrintDeliverySlipController` hands the bitmap to the same `ReceiptPrinter`. It
authorises on `orders.view` rather than `pos.use` — packing paperwork is not a
till action — and it never arms the drawer, whatever the order was paid with.

## Why a bitmap and not text runs

Composing the slip out of ESC/POS text commands means choosing a printer code
page, and **there is no code page that renders joined Arabic, Latin product names
and an LTR-isolated dinar amount on one line.** CP864 covers some Arabic
presentation forms but needs the shaping and bidi done by hand before it is sent,
and it cannot hold `Money::format()`'s `U+2066 … U+2069` isolate at all.

The browser has already solved all of that to draw the page. Rasterising there:

- Arabic joins and orders correctly, for free, in whatever font the shell loads.
- The slip cannot drift from the receipt page — they are literally the same markup.
- The printer is never told about a code page, so a firmware revision cannot change
  how a receipt looks.

Cost: ~30 KB over the wire per slip, and roughly a fifth of a second of GD time.
At a counter that is invisible.

## Configuration

Two sources, in this order:

1. **`.env` / `config/pos.php`** — explicit deployment config always wins, so a
   server can pin its printer and no database row can move it.
2. **The `settings` table**, written by `php artisan pos:printer`. This exists
   because the printer is the one part of the system bolted to a physical desk:
   it gets unplugged, moved to another till, replaced with a LAN model, and none
   of that should need a redeploy or shell access to edit `.env`.

`PrinterConfig` is the only thing that reads either — never call
`config('pos.printer.*')` directly, or stored settings will be silently ignored.

```
php artisan pos:printer                  # show what is wired up
php artisan pos:printer --queues         # what the OS spooler can see
php artisan pos:printer --driver=command --command="lp -d POS80 -o raw"
php artisan pos:printer --clear          # forget, fall back to .env
```

The precedence guard is on the **driver**, not per value: a half-set `.env` (a host
but no driver) must not blend with a stored setting and produce a printer nobody
configured.

**Leaving both empty is a supported state** — the register falls back to the
browser print dialog, which still produces a correct slip.

| Driver | Set | Use when |
| --- | --- | --- |
| **`cups`** | `POS_PRINTER_QUEUE` — a queue on the vendor PPD | **USB. The default choice.** We hand the vendor's own CUPS filter a rendered image and it makes the ESC/POS. See below for why this beats writing the bytes ourselves. |
| `network` | `POS_PRINTER_HOST`, `POS_PRINTER_PORT` (9100) | The printer has its own IP. The only option when PHP and the printer are on one LAN but not one machine. |
| `device` | `POS_PRINTER_DEVICE` — `/dev/usb/lp0`, or `\\PC\POS80` | USB printer on the same host as PHP, and the web user may write the node (on Linux: add it to `lp`). |
| `command` | `POS_PRINTER_COMMAND` — `lp -d <queue> -o raw` | Raw ESC/POS through a raw spooler queue. Works, but see the buffer warning below before choosing it for USB. |

`POS_PRINTER_WIDTH` is printable **dots**, not millimetres: 576 for an 80mm head,
384 for 58mm. It must be a multiple of 8 — the raster format packs 8 dots per byte
and `Raster` throws rather than silently truncating a column.

## Proving the hardware

```
php artisan pos:test-print --drawer
```

Prints a slip that a receipt cannot test: edge ticks at both margins (both
missing an edge means the head width is wrong), and an eight-step grey ramp (how
much survives tells you where the threshold sits).

**Watch the clock as much as the paper.** A receipt that takes tens of seconds is
a symptom, not a slow printer: it means the stream is being paced a job at a time.
Through the vendor driver a full slip is about three seconds.

## The USB setup that is actually in use (macOS, 2026-09-10)

The shop's unit is an **Xprinter POS-80, firmware 3.027PR6M, USB only** — no LAN
port. 48 characters per line confirms a 576-dot head.

It runs on `driver=cups` through a queue built on the **manufacturer's PPD**:

```
lpadmin -p XP80 -E -v "usb://Printer/POS-80?serial=1C7419D10000" \
  -P /Library/Printers/PPDs/Contents/Resources/XP-80.ppd
php artisan pos:printer --driver=cups --queue=XP80 --density=5
```

`XP-80.ppd` comes from Xprinter's macOS driver package (`net.xprinter.drv`). It
declares `*cupsFilter: application/vnd.cups-raster 0 rastertosnailep-xprinter`,
so CUPS rasterises our PNG and the vendor filter turns it into ESC/POS.

### Why the vendor driver, when we can generate ESC/POS ourselves

We can, and the code still does for the other three transports. On this printer
over USB it does not survive contact:

| What was sent | Result |
| --- | --- |
| ESC/POS text | clean |
| `GS v 0` raster, 13KB | clean |
| `GS v 0` raster, 83KB (a real receipt) | **a foot of GB18030 gibberish** |

That is a **buffer overrun**. The controller holds far less than a slip, does not
apply flow control, and once it loses command sync it prints the rest of the
raster as text. It is a hardware limit, not a bug in the byte stream — the stream
was verified correct (`1B 40`, `1D 76 30 00`, `48 00` = 72 bytes/row, exact cut
and drawer tail).

Pacing it ourselves **does** work — that is what `chunk_bytes` is for — but over
CUPS each chunk costs a separate job, and one receipt took **88 seconds**. Useless
at a till. Through the vendor filter the same slip takes **3 seconds**, because it
streams inside a single job and knows its own printer's limits.

Nothing is given up. The PPD exposes what we were sending by hand:

| We used to emit | The PPD option |
| --- | --- |
| `GS V B` partial cut | `PageCutType=1PartialCutPage` |
| `ESC p` drawer pulse | `CashDrawer1Setting=2CashDrawer1AfterPrinting` |
| head density | `PrintDensity=1..8` |

And the page is cut to each receipt's own height via `*VariablePaperSize`
(`PageSize=Custom.204x<points>`); left at the PPD's 210mm default, every slip
would waste most of a foot of roll.

### Things that were tried and are not the answer

- **`mike42/escpos-php`.** Installed, and used for the `column` raster mode. Its
  `CupsPrintConnector` runs `lp -d <printer> <tmpfile>` — the same single-job pipe,
  so it does not change the transport. Its `bitImageColumnFormat` even ships a
  commented-out `sleep(0.1)` noting it "reduces the amount of trouble that a
  TM-U220 has keeping up with large images" — the same overrun, acknowledged
  upstream and unfixed.
- **Driving `/usr/libexec/cups/backend/usb` directly.** It delivers byte-for-byte,
  but it fights `cupsd` for the device and shreds whatever else is printing. Do
  not do this while a queue for the same printer is enabled.

## The blank strip at the top of every slip — do not chase it

It is **20.00 mm, it is mechanical, and no code change removes it.** Measured, not
guessed:

| Stage | Result |
| --- | --- |
| `cgimagetopdf` output | `MediaBox [0 0 204 71]`, image placed at `0 0.167` — fills the page |
| CUPS raster handed to the vendor filter | 576 x 200 at 203dpi — exactly the source image, no padding |
| Vendor filter's ESC/POS | first `GS v 0` band has **0 leading blank rows** |
| End of the same stream | `ESC J 160` — a 160-dot feed = **20.00 mm** |

Nothing in the chain pads the top. The strip is the consequence of that trailing
feed and where the cutter sits: the head is ~20mm upstream of the blade, so the
feed pushes the last printed line down to the blade, the cut lands flush against
it, and the fresh paper tip is left 20mm ahead of the head. That 20mm is the top
of the *next* receipt, and the head can never reach it — it is already past.

The paper between head and cutter has to be spent at one end or the other. Feed
less and the blade cuts through the last lines instead.

The PPD exposes no control over the distance (`FeedCutAfterJobEnd` only *adds*
to it), and the 160 is hardcoded in `rastertosnailep-xprinter`. If it ever has to
change it is a printer-side setting — Xprinter's own utility, or DIP switches —
not this codebase.

**Also established while measuring:** Apple's CG filters ignore `ppi`,
`position` and the `page-*` margins. On macOS only `PageSize` steers geometry.
Those options are still passed for Linux, where they do work.

## Moving the printer to another machine

The `command` and `device` drivers run **where PHP runs**, not where the browser
runs. So what has to be redone depends on what moved:

| What changed | What to do |
| --- | --- |
| Printer unplugged and back into the *same* machine | Nothing. The queue survives; the USB serial is in the device URI. |
| Printer moved to a *different* machine, app moves with it | Install the vendor driver there, create the queue on its PPD, then `php artisan pos:printer --driver=cups --queue=<queue>`. |
| Printer moved to a different machine, app stays put | **Neither `command` nor `device` can reach it.** Nothing PHP does on this host touches another host's USB bus. Buy the LAN variant and use `network`, or add a QZ Tray bridge. |
| Second till added | Both tills print through the one server, so they share whatever this config points at. Per-till printers would need the printer keyed to the cashier's shift — not built. |

## The one thing that will trip you up

**If Laravel runs on a remote VPS and the printer is USB-attached at the shop,
none of these three drivers can reach it.** The server has no route to the
cashier's USB bus. That deployment needs a local bridge — QZ Tray on the register,
fed the same bytes — which is not built. Either put the app on the shop LAN, or
buy the printer's LAN variant and use the `network` driver.

## Deliberate decisions

- **The drawer pin is read from the order, never from the request.** `ESC p` is
  cash handling; nothing a browser posts should be able to pop a drawer on a
  transfer sale. See `PrintReceiptController::__invoke()`.
- **The drawer is kicked after the slip, before the cut.** A drawer that opens
  while the head is still printing invites a hand into the cutter.
- **Rasters are banded at 128 rows.** `GS v 0` can address 65,535 rows, but the
  controllers in counter-top printers hold far less than a slip in RAM and drop
  the tail of an oversized image. Banding prints seamlessly.
- **The endpoint answers JSON and never blocks.** By the time the receipt page
  exists the money is in the drawer. A printer failure downgrades to the browser
  dialog; it never fails a sale.
- **The drawer never opens on a non-cash sale**, on either path — `openDrawer` is
  read from the order's payment method, never from the request.
- **`escpos-php` is present but is not the backbone.** It supplies the `column`
  (`ESC *`) raster mode only. Generating init/raster/cut/kick ourselves is four
  commands of three to five bytes each, identical across every ESC/POS clone, and
  keeps the code-page layer out of the design.
- **Verify a printer by its output, not by its queue.** An early version of this
  work was reported working on the strength of `lpstat` showing jobs completing.
  Jobs complete when CUPS hands the bytes over; that says nothing about what the
  head printed. Every claim here is backed by a slip someone read.

## Testing it

`tests/Feature/Pos/ReceiptPrintingTest.php` binds `NullPrinter` over
`PrinterConnection` and asserts on the bytes, so the stream is verified without a
printer. `Raster` is tested against a known bit pattern (left half black on an
8×1 image is `0xf0`).
