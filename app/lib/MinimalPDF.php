<?php
/**
 * MinimalPDF - tiny PDF generator supporting:
 * - A4 page
 * - Basic text blocks (Helvetica)
 * - Embedding JPEG images (for signature)
 *
 * Not a full PDF implementation, but enough for a contract PDF.
 */
class MinimalPDF {
    private array $objects = [];
    private array $pages = [];
    private string $buffer = '';
    private int $objCount = 0;
    private array $images = []; // [id => ['w'=>, 'h'=>, 'data'=>, 'objN'=>, 'name'=>]]

    private function newObj(string $content): int {
        $this->objCount++;
        $this->objects[$this->objCount] = $content;
        return $this->objCount;
    }

    public function addJpegImage(string $jpegBinary, int $wPx, int $hPx): string {
        $name = 'Im' . (count($this->images) + 1);
        $this->images[$name] = ['data' => $jpegBinary, 'w' => $wPx, 'h' => $hPx, 'objN' => 0, 'name' => $name];
        return $name;
    }

    public function addPage(string $contentStream, array $resources = []): void {
        $this->pages[] = ['content' => $contentStream, 'resources' => $resources];
    }

    public function text(float $x, float $y, string $text, int $size = 11): string {
        $t = $this->escapeText($text);
        return "BT /F1 {$size} Tf {$x} {$y} Td ({$t}) Tj ET\n";
    }

    public function multiLineText(float $x, float $y, string $text, int $size = 11, float $leading = 14): string {
        $lines = preg_split("/\r\n|\n|\r/", $text);
        $out = "BT /F1 {$size} Tf {$x} {$y} Td\n";
        $first = true;
        foreach ($lines as $line) {
            $t = $this->escapeText($line);
            if (!$first) $out .= "0 -{$leading} Td\n";
            $out .= "({$t}) Tj\n";
            $first = false;
        }
        $out .= "ET\n";
        return $out;
    }

    public function image(string $imgName, float $x, float $y, float $wPt, float $hPt): string {
        // place image using cm matrix
        // PDF coordinate origin is bottom-left
        return "q {$wPt} 0 0 {$hPt} {$x} {$y} cm /{$imgName} Do Q\n";
    }

    public function output(): string {
        // PDF header
        $pdf = "%PDF-1.4\n";

        // Font object (Helvetica)
        $fontObj = $this->newObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");

        // Image objects
        foreach ($this->images as $k => &$img) {
            $len = strlen($img['data']);
            $stream = "<< /Type /XObject /Subtype /Image /Name /{$img['name']} /Filter /DCTDecode /Width {$img['w']} /Height {$img['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length {$len} >>\nstream\n{$img['data']}\nendstream";
            $img['objN'] = $this->newObj($stream);
        }
        unset($img);

        $pageObjs = [];
        $contentObjs = [];

        // Build pages
        foreach ($this->pages as $p) {
            $content = $p['content'];
            $contentLen = strlen($content);
            $contentObj = $this->newObj("<< /Length {$contentLen} >>\nstream\n{$content}\nendstream");
            $contentObjs[] = $contentObj;

            // Resources
            $xobjects = '';
            if (!empty($this->images)) {
                $pairs = [];
                foreach ($this->images as $img) {
                    $pairs[] = "/{$img['name']} {$img['objN']} 0 R";
                }
                $xobjects = "<< " . implode(' ', $pairs) . " >>";
            }
            $res = "<< /Font << /F1 {$fontObj} 0 R >>";
            if ($xobjects) $res .= " /XObject {$xobjects}";
            $res .= " >>";

            // A4 MediaBox: 595.28 x 841.89 points
            $pageObj = $this->newObj("<< /Type /Page /Parent 0 0 R /Resources {$res} /MediaBox [0 0 595.28 841.89] /Contents {$contentObj} 0 R >>");
            $pageObjs[] = $pageObj;
        }

        // Pages tree
        $kids = implode(' ', array_map(fn($n) => "{$n} 0 R", $pageObjs));
        $pagesObj = $this->newObj("<< /Type /Pages /Count " . count($pageObjs) . " /Kids [ {$kids} ] >>");

        // Fix Parent references in page objects (replace "0 0 R" with pagesObj ref)
        foreach ($pageObjs as $n) {
            $this->objects[$n] = str_replace("/Parent 0 0 R", "/Parent {$pagesObj} 0 R", $this->objects[$n]);
        }

        // Catalog
        $catalogObj = $this->newObj("<< /Type /Catalog /Pages {$pagesObj} 0 R >>");

        // xref
        $offsets = [];
        foreach ($this->objects as $i => $obj) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "{$i} 0 obj\n{$obj}\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($this->objCount + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i=1; $i<= $this->objCount; $i++) {
            $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size " . ($this->objCount + 1) . " /Root {$catalogObj} 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        return $pdf;
    }

    private function escapeText(string $t): string {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $t);
    }
}
