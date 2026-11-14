<?php
/** Единый порядок разделов лагеря при отображении. Тексты и порядок записей CMS не изменяются. */
declare(strict_types=1);
function at_camp_section_titles(): array
{
    return ['description'=>'Описание','location'=>'Расположение','residence'=>'Размещение','nutrition'=>'Питание','infrastructure'=>'Инфраструктура','safety'=>'Безопасность и медицина','programm'=>'Программа','beach'=>'Пляж','transfer'=>'Трансфер','for_parents'=>'Документы'];
}
function at_camp_label(string $text): string
{
    $text = mb_strtolower(html_entity_decode($text,ENT_QUOTES|ENT_HTML5,'UTF-8'));
    $text = (string)preg_replace('~^[\s\x{00a0}]*(?:№\s*)?\d+[.)\-:]\s*~u','',$text);
    return (string)preg_replace('~^[\s\x{00a0}:.;–—]+|[\s\x{00a0}:.;–—]+$~u','',(string)preg_replace('~[\s\x{00a0}]+~u',' ',$text));
}
function at_camp_section_key(string $key, string $title = ''): string
{
    $map = ['description'=>['description','описание','описание лагеря','о лагере','общее описание'], 'location'=>['location','расположение','местоположение'],
        'residence'=>['residence','stay','accommodation','размещение','проживание','условия проживания'], 'nutrition'=>['nutrition','food','питание','организация питания'],
        'infrastructure'=>['infrastructure','инфраструктура','смены и инфраструктура'], 'safety'=>['safety','medicine','безопасность и медицина','безопасность','медицина','лечение','медицинские услуги','охрана и медобслуживание','медицинская помощь и охрана','медицинское обеспечение','медицинское обслуживание','охрана'],
        'programm'=>['programm','program','программа','программа лагеря','программа отдыха','распорядок дня'], 'beach'=>['beach','пляж'],
        'transfer'=>['transfer','otpravka_children','трансфер','организованная отправка детей','перелет и трансфер','перелёт и трансфер'], 'for_parents'=>['for_parents','documents','docs','документы','необходимые документы']];
    $details = str_ends_with($key,'_details'); $base = $details ? substr($key,0,-8) : $key;
    foreach ($map as $canonical=>$aliases) if (in_array(at_camp_label($base),$aliases,true) || (!$details && in_array(at_camp_label($title),$aliases,true))) return $canonical.($details?'_details':'');
    return $key;
}
function at_camp_content_present(string $html, string $key = ''): bool
{
    if (preg_match('~<(?:img|svg|video|audio|iframe|object|embed)\b~i',$html)) return true;
    $text = at_camp_label(strip_tags((string)preg_replace('~>\s*<~u','> <',$html)));
    if ($text === '') return false;
    $absent = ['нет','отсутствует','не предусмотрен','не предусмотрено','не предусмотрена','пляжа нет','пляж отсутствует','собственного пляжа нет','трансфера нет','трансфер отсутствует','трансфер не предусмотрен'];
    return !in_array($key,['beach','transfer'],true) || !in_array($text,$absent,true);
}
/** Recognize only an entire heading or an explicit leading bold label. */
function at_camp_heading(DOMNode $node): ?array
{
    if (!$node instanceof DOMElement) return null;
    $tag = strtolower($node->tagName); $text = trim($node->textContent); $key = at_camp_section_key('', $text);
    $media = (new DOMXPath($node->ownerDocument))->query('.//img|.//svg|.//video|.//audio|.//iframe',$node)->length > 0;
    if ($key !== '' && (preg_match('~^h[1-6]$~',$tag) || in_array($tag,['p','b','strong','div','span','em'],true))) return [$key,!$media];
    if (preg_match('~^h[1-6]$~',$tag) && preg_match('~^([^:]{1,70}):\s*\S~u',$text,$prefix)) {
        $key = at_camp_section_key('',$prefix[1]); if ($key !== '') return [$key,false];
    }
    if ($tag !== 'p' && !preg_match('~^h[1-6]$~',$tag)) return null;
    $first = $node->firstChild;
    while ($first && $first instanceof DOMText && trim(str_replace("\u{00a0}",' ',$first->textContent)) === '') $first = $first->nextSibling;
    while ($first instanceof DOMElement && in_array(strtolower($first->tagName),['span','em','font'],true)) $first = $first->firstChild;
    if (!$first instanceof DOMElement || !in_array(strtolower($first->tagName),['b','strong'],true)) return null;
    $key = at_camp_section_key('',trim($first->textContent));
    return $key === '' ? null : [$key,false]; // Keep the complete paragraph when text follows its label.
}
/** Split simple wrappers too, so at_rich() containers and inline formatting survive. */
function at_camp_split_description(string $html): array
{
    $previous = libxml_use_internal_errors(true); $doc = new DOMDocument('1.0','UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="at-camp-sections-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($previous); $root = $doc->getElementById('at-camp-sections-root');
    if (!$root) return [['skey'=>'description','html'=>$html]];
    $append = static function(array &$parts,string $key,string $body): void {
        $last = count($parts)-1;
        if ($last >= 0 && $parts[$last]['skey'] === $key) $parts[$last]['html'] .= $body;
        else $parts[] = ['skey'=>$key,'html'=>$body];
    };
    $walk = static function(DOMNode $parent,string &$current) use (&$walk,$append,$doc): array {
        $parts = [];
        foreach ($parent->childNodes as $node) {
            $heading = at_camp_heading($node);
            if ($heading) { $current = $heading[0]; if (!$heading[1]) $append($parts,$current,$doc->saveHTML($node)); continue; }
            if ($node instanceof DOMElement && in_array(strtolower($node->tagName),['div','section','article','blockquote','main','aside','span','em','font'],true)) {
                $fragments = $walk($node,$current); $first = true;
                foreach ($fragments as $fragment) {
                    $shell = $node->cloneNode(false); if (!$first) $shell->removeAttribute('id');
                    $shell->appendChild($doc->createComment('at-camp-fragment')); $first = false;
                    $append($parts,$fragment['skey'],str_replace('<!--at-camp-fragment-->',$fragment['html'],$doc->saveHTML($shell)));
                }
            } else $append($parts,$current,$doc->saveHTML($node));
        }
        return $parts;
    };
    $current = 'description'; return $walk($root,$current);
}
/** @param array<int,array<string,mixed>> $sections @return array<int,array<string,mixed>> */
function at_camp_order_sections(array $sections): array
{
    $titles = at_camp_section_titles(); $grouped = [];
    foreach ($sections as $row) {
        $key = at_camp_section_key((string)($row['skey'] ?? ''),(string)($row['title'] ?? ''));
        $parts = $key === 'description' ? at_camp_split_description((string)($row['html'] ?? '')) : [['skey'=>$key,'html'=>(string)($row['html'] ?? '')]];
        foreach ($parts as $part) {
            $key = $part['skey']; if (!at_camp_content_present($part['html'],$key)) continue;
            if (isset($grouped[$key])) { $grouped[$key]['html'] .= "\n".$part['html']; continue; }
            $grouped[$key] = array_replace($row,['skey'=>$key,'html'=>$part['html']]);
            if (isset($titles[$key])) $grouped[$key]['title'] = $titles[$key];
        }
    }
    // Orphan details must remain visible instead of disappearing in the template's nested attachments.
    foreach (array_keys($grouped) as $key) if (str_ends_with($key,'_details') && !isset($grouped[substr($key,0,-8)])) {
        $base = substr($key,0,-8); $grouped[$base] = array_replace($grouped[$key],['skey'=>$base,'title'=>$titles[$base] ?? $grouped[$key]['title']]); unset($grouped[$key]);
    }
    $out = [];
    foreach ($titles as $key=>$title) if (isset($grouped[$key])) { $out[] = $grouped[$key]; unset($grouped[$key]); }
    foreach ($grouped as $key=>$row) if (!str_ends_with($key,'_details')) $out[] = $row;
    foreach ($grouped as $key=>$row) if (str_ends_with($key,'_details')) $out[] = $row;
    return $out;
}
