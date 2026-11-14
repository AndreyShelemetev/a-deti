<?php
/** Presentation of source tables; the CMS data and genuine table cells stay intact. */
declare(strict_types=1);

function at_tour_table_scroll(DOMDocument $doc, string $extraClass = ''): void
{
    $xp = new DOMXPath($doc);
    foreach (iterator_to_array($doc->getElementsByTagName('table')) as $table) {
        if ($xp->query('./tr|./tbody/tr|./thead/tr|./tfoot/tr', $table)->length < 2) continue;
        if ($xp->query('ancestor::table', $table)->length > 0) continue;
        $parent = $table->parentNode;
        if ($parent instanceof DOMElement && in_array('camp-table-scroll', preg_split('~\s+~', $parent->getAttribute('class')), true)) continue;
        $scroll = $doc->createElement('div');
        $scroll->setAttribute('class', trim('camp-table-scroll ' . $extraClass));
        $scroll->setAttribute('role', 'region');
        $scroll->setAttribute('aria-label', 'Таблица раздела');
        $scroll->setAttribute('tabindex', '0');
        $parent->replaceChild($scroll, $table);
        $scroll->appendChild($table);
    }
}

/** One-cell tables used only as an outer container must not squeeze the actual schedule. */
function at_tour_tables_html(string $html): string
{
    if (stripos($html, '<table') === false) return $html;
    $doc = at_camp_dom($html); $xp = new DOMXPath($doc);
    foreach (iterator_to_array($doc->getElementsByTagName('table')) as $table) {
        $rows = $xp->query('./tr|./tbody/tr', $table);
        if ($rows->length !== 1 || $xp->query('./caption|./thead|./tfoot', $table)->length) continue;
        $cells = $xp->query('./td|./th', $rows->item(0));
        if ($cells->length !== 1 || $cells->item(0)->getElementsByTagName('table')->length === 0) continue;
        $container = $doc->createElement('div');
        if ($table->hasAttribute('id')) $container->setAttribute('id', $table->getAttribute('id'));
        $cell = $cells->item(0);
        while ($cell->firstChild) $container->appendChild($cell->firstChild);
        $table->parentNode->replaceChild($container, $table);
    }
    // Source editors use repeated non-breaking spaces for alignment; let the table align cells.
    foreach ($xp->query('//table//text()') as $text) $text->nodeValue = str_replace("\u{00a0}", ' ', $text->nodeValue);
    at_tour_table_scroll($doc, 'camp-table-scroll--schedule');
    $body = '';
    foreach ($doc->getElementById('camp-content')->childNodes as $node) $body .= $doc->saveHTML($node);
    return $body;
}

/** Photo/contact-sheet tables are not schedules. Keep each original photo with its caption. */
function at_camp_photo_tables(DOMDocument $doc): array
{
    $xp = new DOMXPath($doc); $result = [];
    foreach ($doc->getElementsByTagName('table') as $table) {
        if ($table->getElementsByTagName('table')->length || $xp->query('.//*[@rowspan>1 or @colspan>1]', $table)->length) continue;
        $rows = $xp->query('./tr|./tbody/tr', $table);
        if ($rows->length !== 2) continue;
        $photos = $xp->query('./td', $rows->item(0)); $captions = $xp->query('./td', $rows->item(1));
        if ($photos->length < 2 || $photos->length !== $captions->length) continue;
        $pairs = [];
        foreach ($photos as $i => $cell) {
            $images = $cell->getElementsByTagName('img'); $caption = $captions->item($i);
            $text = trim((string)preg_replace('~[\s\x{00a0}]+~u', ' ', $cell->textContent));
            $label = trim((string)preg_replace('~[\s\x{00a0}]+~u', ' ', $caption->textContent));
            if ($images->length !== 1 || $text !== '' || $label === '' || mb_strlen($label) > 100 || $caption->getElementsByTagName('img')->length) { $pairs = []; break; }
            $src = trim($images->item(0)->getAttribute('src'));
            if (!preg_match('~^(?:/(?!/)|https?://)~i', $src)) { $pairs = []; break; }
            $pairs[] = ['src'=>$src, 'label'=>$label];
        }
        if ($pairs) $result[] = ['table'=>$table, 'pairs'=>$pairs];
    }
    return $result;
}
