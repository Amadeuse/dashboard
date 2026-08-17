<?php

declare(strict_types=1);

namespace App\Core;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Thin wrapper around mpdf/mpdf — this project's one Composer dependency
 * (see bootstrap.php; everything else here is dependency-free by design).
 *
 * mPDF's bundled fonts don't cover the Georgian Unicode block (U+10A0–U+10FF)
 * — text would render as tofu boxes without this. NotoSansGeorgian.ttf
 * (OFL-licensed, from Google's font repo) is registered as the default font
 * for every PDF this app generates, so callers never have to think about it.
 * Lives under public/assets/fonts/, same as every other font this project
 * ships (bpg-arial-caps, gb) — being under the docroot doesn't make it web
 * -routed to anything, mPDF just reads it as a plain file like any other
 * font here already is (bpg-arial-caps's own .ttf sits right next to its
 * @font-face CSS the same way).
 */
final class Pdf
{
    private const FONT_DIR     = ROOT_PATH . '/public/assets/fonts/noto-sans-georgian/fonts';
    private const BPG_FONT_DIR = ROOT_PATH . '/public/assets/fonts/bpg-arial-caps/fonts';

    /** @param array<string,mixed> $config extra/overriding Mpdf constructor options (e.g. page margins) */
    public static function make(array $config = []): Mpdf
    {
        $fontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new FontVariables())->getDefaults()['fontdata'];

        return new Mpdf($config + [
            'mode'         => 'utf-8',
            'format'       => 'A4',
            'fontDir'      => array_merge($fontDirs, [self::FONT_DIR, self::BPG_FONT_DIR]),
            'fontdata'     => $fontData + [
                'notosansgeorgian' => ['R' => 'NotoSansGeorgian.ttf'],
                'bpgarialcaps'     => ['R' => 'bpg-arial-caps-webfont.ttf'],
            ],
            'default_font' => 'notosansgeorgian',
        ]);
    }

    /**
     * Renders $html to a PDF and sends it to the browser as a download named
     * $filename. $footerHtml, when given, becomes a real running page footer
     * (Mpdf::SetHTMLFooter) instead of flowing HTML at the end of the body —
     * margin_footer (15mm) is mPDF's distance from the *page's bottom edge*
     * to the footer text, matching margin_top's default (16mm, untouched) on
     * the other end; margin_bottom (25mm) is where body content itself must
     * stop, leaving room above the footer so the two never overlap.
     */
    public static function download(string $html, string $filename, ?string $footerHtml = null): void
    {
        $mpdf = self::make($footerHtml !== null
            ? ['margin_bottom' => 25, 'margin_footer' => 10]
            : []);

        if ($footerHtml !== null) {
            $mpdf->SetHTMLFooter($footerHtml);
        }

        $mpdf->WriteHTML($html);
        $mpdf->Output($filename, Destination::DOWNLOAD);
    }
}
