<?php
/** Presentation only: use each tour's own documents, never copy requirements between tours. */
declare(strict_types=1);
function at_tour_documents_label(string $text): string
{
    $text = mb_strtolower(html_entity_decode($text,ENT_QUOTES|ENT_HTML5,'UTF-8'));
    return (string)preg_replace('~^[\s\x{00a0}:]+|[\s\x{00a0}:]+$~u','',(string)preg_replace('~[\s\x{00a0}]+~u',' ',$text));
}
function at_tour_documents_group(string $label): string
{
    $label = at_tour_documents_label($label);
    if (in_array($label,['в лагерь','для лагеря','документы в лагерь','документы для лагеря'],true)
        || preg_match('~^документы в (?:мдц|вдц) [«"“][^»"”]+[»"”]$~u',$label)) return 'camp';
    return in_array($label,['в дорогу','для поездки','документы в дорогу','документы для поездки','документы для ж/д трансфера','документы для железнодорожного трансфера'],true) ? 'road' : '';
}
function at_tour_documents_heading(DOMNode $node): string
{
    if (!$node instanceof DOMElement) return '';
    $tag = strtolower($node->tagName); $group = at_tour_documents_group($node->textContent);
    if ($group === '' || !in_array($tag,['h1','h2','h3','h4','h5','h6','p','b','strong'],true)) return '';
    if ($tag === 'b' || $tag === 'strong') {
        $previous = $node->previousSibling;
        while ($previous instanceof DOMText && trim(str_replace("\u{00a0}",' ',$previous->textContent)) === '') $previous = $previous->previousSibling;
        if ($previous && (!$previous instanceof DOMElement || !in_array(strtolower($previous->tagName),['br','p','div','h1','h2','h3','h4','h5','h6','ul','ol'],true))) return '';
    }
    return $group;
}
function at_tour_documents_present(string $html): bool
{
    return preg_replace('~[\s\x{00a0}]+~u','',html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8')) !== '' || (bool)preg_match('~<(?:img|svg|video|audio|iframe|a)\b~i',$html);
}
/** Turn source-editor text lists into semantic lists without flattening links or emphasis. */
function at_tour_documents_normalize(DOMElement $parent): void
{
    $doc = $parent->ownerDocument;
    $run = [];
    $flush = static function() use (&$run, $doc, $parent): void {
        if (!$run) return;
        // Split inline content at source line breaks. Clone inline shells so formatting survives.
        $split = static function(DOMNode $node) use (&$split, $doc): array {
            if ($node instanceof DOMText) {
                $pieces = preg_split('~\R|(?<=\s)(?=[•●▪]\s)~u', $node->textContent);
                return array_map(static fn(string $text): array => [$doc->createTextNode($text)], $pieces);
            }
            if ($node instanceof DOMElement && strtolower($node->tagName) === 'br') return [[], []];
            // Link labels and media are atomic: do not duplicate URLs when splitting lines.
            if (!$node instanceof DOMElement || in_array(strtolower($node->tagName), ['a','img','svg'], true)) return [[$node->cloneNode(true)]];
            $lines = [[]];
            foreach ($node->childNodes as $child) {
                foreach ($split($child) as $i => $part) {
                    if ($i > 0) $lines[] = [];
                    array_push($lines[count($lines)-1], ...$part);
                }
            }
            return array_map(static function(array $children) use ($node): array {
                $shell = $node->cloneNode(false);
                foreach ($children as $child) $shell->appendChild($child);
                return [$shell];
            }, $lines);
        };
        $lines = [[]];
        foreach ($run as $node) {
            foreach ($split($node) as $i => $part) {
                if ($i > 0) $lines[] = [];
                array_push($lines[count($lines)-1], ...$part);
            }
        }
        $marker = '~^[\s\x{00a0}]*(?:[•●▪–—-]|\d{1,2}[.)])[\s\x{00a0}]+~u';
        $textOf = static fn(array $nodes): string => implode('', array_map(static fn(DOMNode $node): string => $node->textContent, $nodes));
        $hasSyntax = false;
        foreach ($lines as $line) {
            $text = $textOf($line);
            if (preg_match($marker, $text) || at_tour_documents_group($text) !== '') { $hasSyntax = true; break; }
        }
        if (!$hasSyntax) { $run = []; return; }
        $fragment = $doc->createDocumentFragment(); $list = null;
        foreach ($lines as $line) {
            $text = $textOf($line);
            if (preg_replace('~[\s\x{00a0}]+~u', '', $text) === '') {
                // Empty text does not mean empty content: keep photos, anchors and editor markers.
                foreach ($line as $node) if ($node instanceof DOMElement) { $list = null; $fragment->appendChild($node); }
                continue;
            }
            if (preg_match($marker, $text, $match)) {
                if (!$list) { $list = $doc->createElement('ol'); $fragment->appendChild($list); }
                $item = $doc->createElement('li');
                if (preg_match('~\d+~', $match[0], $number)) $item->setAttribute('value', $number[0]);
                foreach ($line as $node) $item->appendChild($node);
                // The prefix can span styled text nodes; remove only its characters.
                $remaining = mb_strlen($match[0]);
                foreach (iterator_to_array((new DOMXPath($doc))->query('.//text()', $item)) as $node) {
                    $length = mb_strlen($node->textContent);
                    $node->nodeValue = mb_substr($node->textContent, min($remaining, $length));
                    $remaining = max(0, $remaining - $length);
                    if (!$remaining) break;
                }
                $list->appendChild($item);
            } else {
                $list = null; $paragraph = $doc->createElement('p');
                foreach ($line as $node) $paragraph->appendChild($node);
                $fragment->appendChild($paragraph);
            }
        }
        $parent->insertBefore($fragment, $run[0]);
        foreach ($run as $node) $parent->removeChild($node);
        $run = [];
    };
    foreach (iterator_to_array($parent->childNodes) as $node) {
        if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['div','section','article','p','ul','ol','table','h1','h2','h3','h4','h5','h6','pre','blockquote'], true)) {
            $flush();
            if (in_array(strtolower($node->tagName), ['div','section','article','p'], true)) at_tour_documents_normalize($node);
        } else $run[] = $node;
    }
    $flush();
    // A source paragraph containing several lines cannot contain a new list/paragraph.
    if (strtolower($parent->tagName) === 'p' && (new DOMXPath($doc))->query('./ol|./p', $parent)->length) {
        $container = $doc->createElement('div');
        foreach ($parent->attributes as $attribute) $container->setAttribute($attribute->name, $attribute->value);
        while ($parent->firstChild) $container->appendChild($parent->firstChild);
        $parent->parentNode->replaceChild($container, $parent);
    }
}
function at_tour_documents_html(string $html,string $type = 'CAMP'): string
{
    if (!class_exists('DOMDocument') || !at_tour_documents_present($html)) return $html;
    $oldErrors = libxml_use_internal_errors(true); $doc = new DOMDocument('1.0','UTF-8');
    $loaded = $doc->loadHTML('<?xml encoding="UTF-8"><div id="tour-document-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($oldErrors); $root = $doc->getElementById('tour-document-root');
    if (!$loaded || !$root) return $html;
    $xp = new DOMXPath($doc);
    // The Art-Kvest layout (including its separate downloads) and our own output stay byte-for-byte intact.
    if ($xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," duo ")]/*[contains(concat(" ",normalize-space(@class)," ")," duo__item ")]')->length > 0) return $html;
    at_tour_documents_normalize($root);
    $normalizedHtml = '';
    foreach ($root->childNodes as $node) $normalizedHtml .= $doc->saveHTML($node);
    $append = static function(array &$parts,string $group,string $body): void {
        $last = count($parts)-1;
        if ($last >= 0 && $parts[$last]['group'] === $group) $parts[$last]['html'] .= $body;
        else $parts[] = ['group'=>$group,'html'=>$body];
    };
    $walk = static function(DOMNode $parent,string &$current) use (&$walk,$append,$doc): array {
        $parts = [];
        foreach ($parent->childNodes as $node) {
            $group = at_tour_documents_heading($node);
            // Keep the original label as well: named headings can contain important camp-specific information.
            if ($group !== '') {
                $current = $group;
                // Generic labels are already the card title; retain named source headings.
                if (!in_array(at_tour_documents_label($node->textContent), ['в лагерь','в дорогу'], true)) $append($parts,$current,$doc->saveHTML($node));
                continue;
            }
            if ($node instanceof DOMElement && in_array(strtolower($node->tagName),['div','section','article','p','span','em','strong','b','font'],true)) {
                $first = true;
                foreach ($walk($node,$current) as $fragment) {
                    $shell = $node->cloneNode(false); if (!$first) $shell->removeAttribute('id'); $first = false;
                    $shell->appendChild($doc->createComment('tour-document-fragment'));
                    $append($parts,$fragment['group'],str_replace('<!--tour-document-fragment-->',$fragment['html'],$doc->saveHTML($shell)));
                }
            } else $append($parts,$current,$doc->saveHTML($node));
        }
        return $parts;
    };
    $current = ''; $parts = $walk($root,$current); $groups = array_filter($parts,static fn(array $part): bool=>$part['group'] !== '' && at_tour_documents_present($part['html']));
    if (!$groups) $parts = [['group'=>$type === 'CAMP' ? 'camp' : ($type === 'TRANSFER' ? 'road' : 'neutral'),'html'=>$normalizedHtml]];
    $cards = []; $intro = '';
    foreach ($parts as $part) {
        if (!at_tour_documents_present($part['html'])) continue;
        if ($part['group'] === '') { $intro .= $part['html']; continue; }
        $heading = ['camp'=>'В лагерь','road'=>'В дорогу','neutral'=>'Документы для поездки'][$part['group']];
        $cards[] = '<div class="duo__item"><h3 class="h3 duo__title">'.$heading.'</h3><div class="tour-documents__body">'.$part['html'].'</div></div>';
    }
    return $intro.'<div class="duo tour-documents'.(count($cards) === 1 ? ' duo--one' : '').'">'.implode('',$cards).'</div>';
}
/** Keep existing anchor keys and nested *_details attachments unchanged. */
function at_tour_document_tabs(array $tabs,string $type): array
{
    foreach (['for_parents','docs','documents'] as $key) if (isset($tabs[$key]['html'])) $tabs[$key]['html'] = at_tour_documents_html((string)$tabs[$key]['html'],$type);
    return $tabs;
}
