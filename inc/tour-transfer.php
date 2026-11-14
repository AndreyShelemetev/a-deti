<?php
/** Оформление условий трансфера без изменения цен, маршрутов и записей CMS. */
declare(strict_types=1);
function at_tour_transfer_html(string $html): string
{
    if (!class_exists('DOMDocument') || !at_tour_documents_present($html)) return $html;
    $oldErrors = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $loaded = $doc->loadHTML('<?xml encoding="UTF-8"><div id="tour-transfer-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($oldErrors);
    $root = $doc->getElementById('tour-transfer-root');
    if (!$loaded || !$root) return $html;
    $xp = new DOMXPath($doc);
    // Авторская карточка Арт-Квеста и уже оформленный вывод остаются как есть.
    if ($xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," transfer ")]')->length) return $html;
    $addClass = static function(DOMElement $node, string $class): void {
        $node->setAttribute('class', implode(' ', array_unique(array_filter(preg_split('~\s+~', $node->getAttribute('class').' '.$class)))));
    };
    foreach ($xp->query('//*[@class]') as $node) {
        $classes = preg_split('~\s+~', $node->getAttribute('class'));
        foreach (['ticket-kicker'=>'tour-transfer__title', 'ticket-price'=>'transfer__price', 'ticket-route'=>'transfer__route'] as $from=>$to) {
            if (in_array($from, $classes, true)) $addClass($node, $to);
        }
    }
    // Выделяем только отдельную строку цены; суммы внутри условий не вырываем из предложения.
    foreach ($xp->query('//p|//strong|//b|//div[not(.//p or .//div or .//img)]') as $node) {
        $text = trim((string)preg_replace('~[\s\x{00a0}]+~u', ' ', $node->textContent));
        if (!preg_match('~^(?<prefix>(?:стоимость|цена)[^:—–]*?[:—–-]\s*)?(?<value>(?:от\s+)?\d[\d ,.]*\s*(?:₽|р(?:уб(?:лей|ля|ль|\.)?)?\.?)[.* ]*)$~iu', $text, $price)) continue;
        if (($price['prefix'] ?? '') !== '') {
            // Меняем только текстовую строку: вложенные ссылки и форматирование не расплющиваем.
            if ($node->childNodes->length !== 1 || !$node->firstChild instanceof DOMText) continue;
            $node->removeChild($node->firstChild);
            $node->appendChild($doc->createTextNode($price['prefix']));
            $amount = $doc->createElement('span');
            $amount->setAttribute('class', 'transfer__price');
            $amount->appendChild($doc->createTextNode($price['value']));
            $node->appendChild($amount);
            $addClass($node, 'tour-transfer__price-line');
        } elseif (!$xp->query('.//*[contains(concat(" ",normalize-space(@class)," ")," transfer__price ")]', $node)->length) {
            $addClass($node, 'transfer__price');
        }
    }

    $append = static function(array &$parts, bool $included, string $body): void {
        $last = count($parts)-1;
        if ($last >= 0 && $parts[$last]['included'] === $included) $parts[$last]['html'] .= $body;
        else $parts[] = ['included'=>$included, 'html'=>$body];
    };
    $walk = static function(DOMNode $parent, bool &$inIncluded) use (&$walk, $append, $doc): array {
        $parts = [];
        foreach ($parent->childNodes as $node) {
            $text = at_tour_documents_label($node->textContent);
            $tag = $node instanceof DOMElement ? strtolower($node->tagName) : '';
            if (in_array($tag, ['div','section','article'], true)) {
                $first = true;
                foreach ($walk($node, $inIncluded) as $fragment) {
                    $shell = $node->cloneNode(false);
                    if (!$first) $shell->removeAttribute('id');
                    $first = false;
                    $shell->appendChild($doc->createComment('tour-transfer-fragment'));
                    $append($parts, $fragment['included'], str_replace('<!--tour-transfer-fragment-->', $fragment['html'], $doc->saveHTML($shell)));
                }
                continue;
            }
            if (preg_match('~^в стоимость[^.!?]*вход(?:ит|ят)\b~u', $text)) $inIncluded = true;
            $append($parts, $inIncluded, $doc->saveHTML($node));
        }
        return $parts;
    };
    $summary = ''; $included = ''; $inIncluded = false;
    foreach ($walk($root, $inIncluded) as $part) {
        if ($part['included']) $included .= $part['html'];
        else $summary .= $part['html'];
    }
    $twoColumns = at_tour_documents_present($summary) && at_tour_documents_present($included);
    return '<div class="transfer tile tile--sky tour-transfer'.($twoColumns ? '' : ' tour-transfer--one').'">'
         .($twoColumns ? '<div class="tour-transfer__summary">'.$summary.'</div><div class="tour-transfer__included">'.$included.'</div>'
            : '<div class="tour-transfer__summary">'.$summary.$included.'</div>').'</div>';
}
/** Единый вид служебных разделов для всех типов тура, в том числе нестандартных ключей CMS. */
function at_tour_service_tabs(array $tabs, string $type): array
{
    foreach ($tabs as $key=>&$tab) {
        if (str_ends_with((string)$key, '_details')) continue;
        $kind = at_camp_section_key((string)$key, (string)($tab['title'] ?? ''));
        if ($kind === 'transfer') $tab['html'] = at_tour_transfer_html((string)$tab['html']);
        if ($kind === 'for_parents') $tab['html'] = at_tour_documents_html((string)$tab['html'], $type);
    }
    unset($tab);
    return $tabs;
}
