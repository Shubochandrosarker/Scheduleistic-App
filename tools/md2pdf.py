#!/usr/bin/env python3
"""
Dependency-free Markdown-subset -> PDF renderer.

Uses only PDF core fonts (Helvetica / Helvetica-Bold / Courier), so nothing
needs to be embedded. Supports: # ## ### headings, paragraphs, - bullets,
1. numbered lists, ``` fenced code blocks, **bold** inline (rendered bold for
the whole run when a line is wholly bold-ish — kept simple), --- horizontal
rule, and <<<PAGEBREAK>>>. Good enough for a clean technical guide.
"""
import sys, re

PAGE_W, PAGE_H = 612, 792
ML, MR, MT, MB = 54, 54, 60, 56
USABLE_W = PAGE_W - ML - MR

# Average glyph width as a fraction of font size (Helvetica ~0.5, Courier 0.6).
AVG = {"H": 0.50, "HB": 0.52, "C": 0.60}
FONT_RES = {"H": "F1", "HB": "F2", "C": "F3"}


def esc(s):
    return s.replace("\\", r"\\").replace("(", r"\(").replace(")", r"\)")


def wrap(text, font, size, width=USABLE_W):
    if not text:
        return [""]
    max_chars = max(8, int(width / (AVG[font] * size)))
    words, lines, cur = text.split(" "), [], ""
    for w in words:
        cand = w if not cur else cur + " " + w
        if len(cand) <= max_chars:
            cur = cand
        else:
            if cur:
                lines.append(cur)
            # hard-split very long tokens (URLs, tokens)
            while len(w) > max_chars:
                lines.append(w[:max_chars])
                w = w[max_chars:]
            cur = w
    if cur:
        lines.append(cur)
    return lines


class PDF:
    def __init__(self):
        self.pages = []          # list of content-stream strings
        self.ops = []            # current page ops
        self.y = PAGE_H - MT

    def _newpage(self):
        if self.ops:
            self.pages.append("\n".join(self.ops))
        self.ops = []
        self.y = PAGE_H - MT

    def _space(self, h):
        if self.y - h < MB:
            self._newpage()
        self.y -= h

    def text(self, s, font, size, x=ML, color=(0, 0, 0), leading=None):
        leading = leading or size * 1.35
        for line in s.split("\n"):
            if self.y - leading < MB:
                self._newpage()
            self.y -= leading
            r, g, b = color
            self.ops.append(
                f"BT /{FONT_RES[font]} {size} Tf {r} {g} {b} rg "
                f"{x} {self.y:.1f} Td ({esc(line)}) Tj ET"
            )

    def rule(self):
        self._space(10)
        self.ops.append(f"0.8 0.8 0.8 RG 0.5 w {ML} {self.y:.1f} m {PAGE_W-MR} {self.y:.1f} l S")
        self._space(6)

    def codebox(self, lines):
        pad = 6
        size = 8.5
        lead = size * 1.4
        # background height
        wrapped = []
        for ln in lines:
            wrapped += wrap(ln, "C", size, USABLE_W - 2 * pad) or [""]
        h = lead * len(wrapped) + 2 * pad
        if self.y - h < MB:
            self._newpage()
        top = self.y
        self.ops.append(
            f"0.96 0.96 0.97 rg {ML} {top - h:.1f} {USABLE_W} {h:.1f} re f"
        )
        self.y = top - pad
        for ln in wrapped:
            self.y -= lead
            self.ops.append(
                f"BT /F3 {size} Tf 0.15 0.15 0.2 rg {ML+pad} {self.y:.1f} Td ({esc(ln)}) Tj ET"
            )
        self.y = top - h - 4

    def render_markdown(self, md):
        i, lines = 0, md.split("\n")
        while i < len(lines):
            ln = lines[i]
            if ln.strip() == "<<<PAGEBREAK>>>":
                self._newpage(); i += 1; continue
            if ln.startswith("```"):
                block = []
                i += 1
                while i < len(lines) and not lines[i].startswith("```"):
                    block.append(lines[i]); i += 1
                self.codebox(block); i += 1; self._space(4); continue
            if ln.strip() == "---":
                self.rule(); i += 1; continue
            if ln.startswith("# "):
                self._space(10)
                for w in wrap(ln[2:], "HB", 20):
                    self.text(w, "HB", 20, color=(0.16, 0.13, 0.55))
                self._space(4); i += 1; continue
            if ln.startswith("## "):
                self._space(12)
                for w in wrap(ln[3:], "HB", 15):
                    self.text(w, "HB", 15, color=(0.1, 0.1, 0.12))
                self._space(3); i += 1; continue
            if ln.startswith("### "):
                self._space(8)
                for w in wrap(ln[4:], "HB", 12):
                    self.text(w, "HB", 12, color=(0.2, 0.2, 0.25))
                self._space(2); i += 1; continue
            if re.match(r"^\s*[-*] ", ln):
                txt = re.sub(r"^\s*[-*] ", "", ln)
                txt = txt.replace("**", "")
                wr = wrap(txt, "H", 11, USABLE_W - 14)
                self.text("• " + wr[0], "H", 11, x=ML + 4)
                for cont in wr[1:]:
                    self.text("  " + cont, "H", 11, x=ML + 14)
                i += 1; continue
            m = re.match(r"^\s*(\d+)\. ", ln)
            if m:
                txt = re.sub(r"^\s*\d+\. ", "", ln).replace("**", "")
                wr = wrap(txt, "H", 11, USABLE_W - 18)
                self.text(f"{m.group(1)}. " + wr[0], "H", 11, x=ML + 4)
                for cont in wr[1:]:
                    self.text("   " + cont, "H", 11, x=ML + 18)
                i += 1; continue
            if ln.strip() == "":
                self._space(6); i += 1; continue
            # paragraph (strip ** and `)
            txt = ln.replace("**", "").replace("`", "")
            for w in wrap(txt, "H", 11):
                self.text(w, "H", 11)
            i += 1
        self._newpage()

    def build(self):
        objs = []
        # 1 catalog, 2 pages, fonts 3/4/5, then page objs + content objs
        n_pages = len(self.pages)
        first_page_obj = 6
        kids = [first_page_obj + 2 * k for k in range(n_pages)]
        objs.append(("1 0 obj", "<< /Type /Catalog /Pages 2 0 R >>"))
        kids_ref = " ".join(f"{k} 0 R" for k in kids)
        objs.append(("2 0 obj", f"<< /Type /Pages /Count {n_pages} /Kids [{kids_ref}] >>"))
        objs.append(("3 0 obj", "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"))
        objs.append(("4 0 obj", "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>"))
        objs.append(("5 0 obj", "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>"))
        for k, content in enumerate(self.pages):
            pobj = first_page_obj + 2 * k
            cobj = pobj + 1
            res = "<< /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >>"
            objs.append((f"{pobj} 0 obj",
                         f"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {PAGE_W} {PAGE_H}] "
                         f"/Resources {res} /Contents {cobj} 0 R >>"))
            stream = content.encode("latin-1", "replace")
            objs.append((f"{cobj} 0 obj",
                         f"<< /Length {len(stream)} >>\nstream\n{content}\nendstream"))
        out = b"%PDF-1.4\n"
        offsets = []
        for header, body in objs:
            offsets.append(len(out))
            out += f"{header}\n{body}\nendobj\n".encode("latin-1", "replace")
        xref_pos = len(out)
        n = len(objs) + 1
        out += f"xref\n0 {n}\n".encode()
        out += b"0000000000 65535 f \n"
        for off in offsets:
            out += f"{off:010d} 00000 n \n".encode()
        out += (f"trailer\n<< /Size {n} /Root 1 0 R >>\nstartxref\n{xref_pos}\n%%EOF").encode()
        return out


def main():
    src, dst = sys.argv[1], sys.argv[2]
    with open(src, encoding="utf-8") as f:
        md = f.read()
    pdf = PDF()
    pdf.render_markdown(md)
    with open(dst, "wb") as f:
        f.write(pdf.build())
    print(f"Wrote {dst}")


if __name__ == "__main__":
    main()
